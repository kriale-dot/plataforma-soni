<?php

declare(strict_types=1);

// Teste HTTP end-to-end apenas em banco efêmero do CI. NÃO executar em produção.
function request(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    $headers = ["Accept: application/json"];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $json = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $context);
    if ($json === false) {
        throw new RuntimeException("Falha HTTP em {$method} {$path}");
    }
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/HTTP\/\S+\s+(\d{3})/', $statusLine, $matches);
    return [(int) ($matches[1] ?? 0), json_decode($json, true, 512, JSON_THROW_ON_ERROR)];
}

function expect(bool $pass, string $label): void
{
    if (!$pass) {
        throw new RuntimeException('FALHOU: ' . $label);
    }
    echo 'OK: ' . $label . PHP_EOL;
}

$token = null;
$id = null;
$originalSettings = null;

try {
    [$status] = request('GET', '/admin/site');
    expect($status === 401, 'Admin sem JWT retorna 401');

    [$status] = request('POST', '/admin/site/blocks', ['type' => 'TEXTO']);
    expect($status === 401, 'Escrita sem JWT retorna 401');

    [$status, $login] = request('POST', '/admin/login', [
        'email' => 'ci@example.test', 'password' => 'ci-password',
    ]);
    expect($status === 200 && is_string($login['token'] ?? null), 'Login de teste');
    $token = $login['token'];

    [$status, $admin] = request('GET', '/admin/site', null, $token);
    expect($status === 200 && count($admin['blocks']) === 5, 'Admin lista blocos');
    $originalSettings = $admin['settings'];

    [$status] = request('POST', '/admin/site/blocks', [
        'type' => 'TEXTO', 'button_url' => 'javascript:alert(1)',
    ], $token);
    expect($status === 422, 'URL perigosa rejeitada');

    [$status, $created] = request('POST', '/admin/site/blocks', [
        'type' => 'TEXTO', 'title' => 'Teste CI',
        'body_text' => 'Bloco em rascunho', 'is_visible' => true,
    ], $token);
    expect($status === 200 && (int) ($created['id'] ?? 0) > 0, 'Criar bloco');
    $id = (int) $created['id'];

    [$status, $public] = request('GET', '/site');
    expect($status === 200 && count($public['blocks']) === 5, 'Rascunho fora do público');

    [$status] = request('PUT', '/admin/site/blocks/' . $id, [
        'publication_status' => 'PUBLISHED', 'title' => 'Bloco publicado CI',
    ], $token);
    expect($status === 200, 'Publicar bloco');

    [$status, $public] = request('GET', '/site');
    expect($status === 200 && count($public['blocks']) === 6, 'Bloco publicado aparece no público');

    [$status] = request('PATCH', '/admin/site/blocks/' . $id . '/publication', ['is_visible' => false], $token);
    expect($status === 200, 'Ocultar bloco');

    [$status, $public] = request('GET', '/site');
    expect($status === 200 && count($public['blocks']) === 5, 'Bloco oculto fora do público');

    [$status] = request('PATCH', '/admin/site/blocks/' . $id . '/order', ['direction' => 'up'], $token);
    expect($status === 200, 'Reordenar bloco');

    [$status] = request('PUT', '/admin/site/settings', array_merge($originalSettings, [
        'site_title' => 'SONI Teste CI',
    ]), $token);
    expect($status === 200, 'Editar configurações');

    [$status, $public] = request('GET', '/site');
    expect($status === 200 && ($public['settings']['site_title'] ?? '') === 'SONI Teste CI', 'Configurações refletidas na API');
} finally {
    if ($token !== null && $originalSettings !== null) {
        request('PUT', '/admin/site/settings', $originalSettings, $token);
    }
    if ($token !== null && $id !== null) {
        request('DELETE', '/admin/site/blocks/' . $id, null, $token);
    }
}

[$status, $public] = request('GET', '/site');
expect($status === 200 && count($public['blocks']) === 5, 'Restauração do estado inicial');
echo "CRUD CMS HTTP aprovado.\n";
