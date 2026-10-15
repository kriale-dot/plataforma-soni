<?php

declare(strict_types=1);

// TESTE INTEGRADO DESTRUTIVO: executar só no MariaDB descartável do GitHub Actions.
function videoHttp(string $method, string $path, ?string $body = null, ?string $token = null, ?string $type = null): array
{
    $headers = ['Accept: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    if ($type !== null) $headers[] = 'Content-Type: ' . $type;
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '',
        'ignore_errors' => true, 'timeout' => 20,
    ]]);
    $raw = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $ctx);
    if ($raw === false) throw new RuntimeException('HTTP indisponível: ' . $path);
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), $raw, $http_response_header];
}

function videoJson(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    [$status, $raw] = videoHttp($method, $path, $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        $token, $body === null ? null : 'application/json');
    return [$status, json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
}

function videoCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('Falhou: ' . $label);
    echo 'OK: ' . $label . PHP_EOL;
}

function videoFile(string $filename, string $bytes, string $mime): array
{
    $boundary = 'sonivideo' . bin2hex(random_bytes(8));
    $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\nContent-Type: {$mime}\r\n\r\n"
        . $bytes . "\r\n--{$boundary}--\r\n";
    return [$body, 'multipart/form-data; boundary=' . $boundary];
}

[$status, $login] = videoJson('POST', '/admin/login', [
    'email' => 'ci@example.test', 'password' => 'ci-password',
]);
videoCheck($status === 200 && is_string($login['token'] ?? null), 'Login administrativo');
$token = $login['token'];
[$status] = videoJson('GET', '/admin/videos');
videoCheck($status === 401, 'Listagem administrativa exige JWT');

[$status, $admin] = videoJson('GET', '/admin/videos', null, $token);
videoCheck($status === 200 && count($admin['categories']) >= 1, 'Migração 005 criou categoria');
$categoryId = (int) $admin['categories'][0]['id'];

[$status, $createdCategory] = videoJson('POST', '/admin/video-categories', [
    'title' => 'Microfones e microfonia', 'description' => 'Aulas e demonstrações práticas',
], $token);
videoCheck($status === 200 && isset($createdCategory['id']), 'Categoria personalizada criada');

[$status, $empty] = videoJson('GET', '/videos');
videoCheck($status === 200 && count($empty['videos']) === 0, 'Catálogo público vazio inicialmente');

[$status] = videoJson('POST', '/admin/videos', [
    'title' => 'Link perigoso', 'category_id' => $categoryId, 'source_type' => 'YOUTUBE',
    'youtube_url' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
], $token);
videoCheck($status === 422, 'Domínio falso do YouTube rejeitado');

[$status] = videoJson('POST', '/admin/videos', [
    'title' => 'Origem insegura', 'category_id' => $categoryId, 'source_type' => 'YOUTUBE',
    'youtube_url' => 'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
], $token);
videoCheck($status === 422, 'HTTP sem TLS rejeitado');

[$status, $video] = videoJson('POST', '/admin/videos', [
    'title' => 'Entenda a microfonia', 'description' => 'Vídeo didático de microfones',
    'category_id' => $categoryId, 'source_type' => 'YOUTUBE',
    'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
], $token);
videoCheck($status === 200 && $video['source_type'] === 'YOUTUBE' &&
    $video['publication_status'] === 'DRAFT' &&
    $video['youtube_id'] === 'dQw4w9WgXcQ', 'Vídeo do YouTube cadastrado em rascunho');
$id = (int) $video['id'];

[$status, $public] = videoJson('GET', '/videos');
videoCheck($status === 200 && count($public['videos']) === 0, 'Rascunho não aparece ao público');
[$status] = videoJson('GET', '/videos/' . $id);
videoCheck($status === 404, 'Rascunho não acessível por URL direta');

[$status, $video] = videoJson('PUT', '/admin/videos/' . $id, [
    'publication_status' => 'PUBLISHED', 'is_visible' => 1,
], $token);
videoCheck($status === 200 && $video['publication_status'] === 'PUBLISHED', 'Publicação do YouTube');

[$status, $public] = videoJson('GET', '/videos?q=microfonia&category=' . $categoryId);
videoCheck($status === 200 && count($public['videos']) === 1, 'Pesquisa e filtro da categoria');
[$status, $public] = videoJson('GET', '/videos?q=contrabaixo');
videoCheck($status === 200 && count($public['videos']) === 0, 'Busca sem coincidência retorna vazio');
[$status, $item] = videoJson('GET', '/videos/' . $id);
videoCheck($status === 200 && $item['youtube_id'] === 'dQw4w9WgXcQ', 'Dados públicos do player');

[$status] = videoJson('PUT', '/admin/videos/' . $id, ['is_visible' => false], $token);
videoCheck($status === 200, 'Ocultar vídeo via admin');
[$status] = videoJson('GET', '/videos/' . $id);
videoCheck($status === 404, 'Vídeo oculto inacessível');

$mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";
[$form, $type] = videoFile('aula.mp4', $mp4, 'video/mp4');
[$status, $raw] = videoHttp('POST', '/admin/site/media', $form, $token, $type);
$uploaded = json_decode($raw, true);
videoCheck($status === 201 && ($uploaded['kind'] ?? '') === 'VIDEO', 'MP4 aceito por verificação MIME');
$mediaId = (int) $uploaded['id'];

[$status] = videoHttp('GET', '/site/media/' . $mediaId);
videoCheck($status === 404, 'Upload MP4 isolado permanece privado');
[$status, $private] = videoHttp('GET', '/admin/site/media/' . $mediaId, null, $token);
videoCheck($status === 200 && $private === $mp4, 'Prévia de MP4 privada funciona com JWT');

[$status, $uploadVideo] = videoJson('POST', '/admin/videos', [
    'title' => 'Aula gravada', 'category_id' => $categoryId,
    'source_type' => 'UPLOAD', 'media_id' => $mediaId,
    'publication_status' => 'PUBLISHED',
], $token);
videoCheck($status === 200 && (int) $uploadVideo['media_id'] === $mediaId, 'MP4 publicado na biblioteca');
$uploadId = (int) $uploadVideo['id'];

[$status, $data] = videoHttp('GET', '/site/media/' . $mediaId);
videoCheck($status === 200 && $data === $mp4, 'MP4 publicado reproduzível');
[$status] = videoJson('POST', '/admin/videos', [
    'title' => 'Vídeo repetido', 'category_id' => $categoryId,
    'source_type' => 'UPLOAD', 'media_id' => $mediaId,
], $token);
videoCheck($status === 422, 'Mesmo arquivo não pode ser cadastrado duas vezes');

[$status] = videoJson('PUT', '/admin/videos/' . $uploadId, ['publication_status' => 'DRAFT'], $token);
videoCheck($status === 200, 'Vídeo pode voltar para rascunho');
[$status] = videoHttp('GET', '/site/media/' . $mediaId);
videoCheck($status === 404, 'MP4 volta a ser privado ao despublicar');
[$status] = videoJson('DELETE', '/admin/videos/' . $uploadId, null, $token);
videoCheck($status === 200, 'Exclusão do cadastro');
[$status, $kept] = videoHttp('GET', '/admin/site/media/' . $mediaId, null, $token);
videoCheck($status === 200 && $kept === $mp4, 'Arquivo físico preservado após exclusão');

echo "Biblioteca de vídeos, YouTube e upload privado/publicado validados.\n";
