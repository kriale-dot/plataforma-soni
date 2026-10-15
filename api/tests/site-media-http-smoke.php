<?php

declare(strict_types=1);

// Apenas para banco temporário do GitHub Actions. NUNCA executar com dados reais.
function hit(string $method, string $path, ?string $body = null, ?string $token = null, ?string $contentType = null, array $extra = []): array
{
    $headers = ['Accept: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    if ($contentType !== null) $headers[] = 'Content-Type: ' . $contentType;
    foreach ($extra as $value) $headers[] = $value;
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body ?? '',
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $raw = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $context);
    if ($raw === false) throw new RuntimeException('Falha HTTP: ' . $method . ' ' . $path);
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $matches);
    return [(int) ($matches[1] ?? 0), $raw, $http_response_header];
}

function checked(bool $test, string $message): void
{
    if (!$test) throw new RuntimeException('Falhou: ' . $message);
    echo "OK: {$message}\n";
}

function jsonRequest(string $method, string $path, ?array $input = null, ?string $token = null): array
{
    [$status, $raw] = hit(
        $method, $path,
        $input === null ? null : json_encode($input, JSON_THROW_ON_ERROR),
        $token, $input === null ? null : 'application/json'
    );
    return [$status, json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
}

function multipartFile(string $name, string $contents): array
{
    $boundary = 'soniCI' . bin2hex(random_bytes(8));
    $file = "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$name}\"\r\nContent-Type: image/png\r\n\r\n" .
        $contents . "\r\n--{$boundary}--\r\n";
    return [$file, 'multipart/form-data; boundary=' . $boundary];
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLtuAAAAABJRU5ErkJggg==', true);
checked(is_string($png) && strlen($png) > 30, 'Arquivo de imagem preparado');

[$status, $login] = jsonRequest('POST', '/admin/login', ['email' => 'ci@example.test', 'password' => 'ci-password']);
checked($status === 200 && isset($login['token']), 'Admin autenticado');
$token = $login['token'];
[$status, $created] = jsonRequest('POST', '/admin/site/blocks', [
    'type' => 'IMAGEM', 'title' => 'Imagem de teste CI', 'publication_status' => 'DRAFT',
], $token);
checked($status === 200 && isset($created['id']), 'Bloco IMAGEM criado em rascunho');
$id = (int) $created['id'];

try {
    [$body, $type] = multipartFile('teste.png', $png);
    [$status] = hit('POST', '/admin/site/media', $body, null, $type);
    checked($status === 401, 'Upload anônimo bloqueado');
    [$status, $raw] = hit('POST', '/admin/site/media', $body, $token, $type);
    $uploaded = json_decode($raw, true);
    checked($status === 201 && isset($uploaded['id']) && $uploaded['kind'] === 'IMAGE', 'Upload PNG com MIME válido');
    $mediaId = (int) $uploaded['id'];

    [$status] = hit('GET', '/site/media/' . $mediaId);
    checked($status === 404, 'Upload sem publicação não fica público');
    [$status] = hit('GET', '/admin/site/media/' . $mediaId);
    checked($status === 401, 'Prévia protegida por JWT');
    [$status, $privateBytes] = hit('GET', '/admin/site/media/' . $mediaId, null, $token);
    checked($status === 200 && $privateBytes === $png, 'Prévia privada mantém bytes originais');

    [$status] = jsonRequest('PUT', '/admin/site/blocks/' . $id, [
        'media_id' => $mediaId, 'publication_status' => 'PUBLISHED',
    ], $token);
    checked($status === 200, 'Bloco associado e publicado');

    [$status, $data] = jsonRequest('GET', '/site');
    $found = array_values(array_filter($data['blocks'], static fn ($b) => (int) $b['id'] === $id));
    checked($status === 200 && count($found) === 1 &&
        $found[0]['media_url'] === '/api/site/media/' . $mediaId, 'API pública aponta para mídia');

    [$status, $publicBytes] = hit('GET', '/site/media/' . $mediaId);
    checked($status === 200 && $publicBytes === $png, 'Mídia publicada acessível');

    [$status, $rangeBytes] = hit('GET', '/site/media/' . $mediaId, null, null, null, ['Range: bytes=0-7']);
    checked($status === 206 && $rangeBytes === substr($png, 0, 8), 'Intervalo HTTP de oito bytes');

    [$status] = jsonRequest('PATCH', '/admin/site/blocks/' . $id . '/publication', ['is_visible' => false], $token);
    checked($status === 200, 'Bloco oculto');
    [$status] = hit('GET', '/site/media/' . $mediaId);
    checked($status === 404, 'Mídia oculta não é servida');

    [$badBody, $badType] = multipartFile('malicioso.php', $png);
    [$status] = hit('POST', '/admin/site/media', $badBody, $token, $badType);
    checked($status === 422, 'Extensão proibida rejeitada');

    [$status] = jsonRequest('PUT', '/admin/site/blocks/' . $id, ['type' => 'AUDIO', 'media_id' => $mediaId], $token);
    checked($status === 422, 'Tipo de mídia incompatível rejeitado');
} finally {
    jsonRequest('DELETE', '/admin/site/blocks/' . $id, null, $token);
}
echo "Mídias seguras validadas.\n";
