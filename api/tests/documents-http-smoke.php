<?php

declare(strict_types=1);

// Executar SOMENTE no MariaDB efêmero do GitHub Actions. Cria documentos e arquivos.
function docHttp(string $method, string $path, ?string $body = null, ?string $token = null, ?string $type = null): array
{
    $headers = ['Accept: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    if ($type !== null) $headers[] = 'Content-Type: ' . $type;
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body ?? '',
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $raw = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $ctx);
    if ($raw === false) throw new RuntimeException('Não foi possível conectar à API: ' . $path);
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $match);
    return [(int) ($match[1] ?? 0), $raw, $http_response_header];
}

function docJson(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    [$status, $raw] = docHttp($method, $path, $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR), $token,
        $body === null ? null : 'application/json');
    return [$status, json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
}

function docMultipart(string $filename, string $content, string $mime): array
{
    $boundary = 'sonidoc' . bin2hex(random_bytes(8));
    $data = "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\nContent-Type: {$mime}\r\n\r\n" .
        $content . "\r\n--{$boundary}--\r\n";
    return [$data, 'multipart/form-data; boundary=' . $boundary];
}

function expectDoc(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('Falhou: ' . $label);
    echo 'OK: ' . $label . PHP_EOL;
}

function validPdf(): string
{
    $pdf = "%PDF-1.4\n";
    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>\nendobj\n",
        "4 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n",
    ];
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 5\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
    $pdf .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    return $pdf;
}

[$status, $login] = docJson('POST', '/admin/login', [
    'email' => 'ci@example.test', 'password' => 'ci-password',
]);
expectDoc($status === 200 && isset($login['token']), 'Administrador autenticado');
$token = $login['token'];

[$status] = docJson('GET', '/admin/documents');
expectDoc($status === 401, 'Listagem administrativa bloqueia anônimo');
[$status] = docJson('POST', '/admin/documents', ['title' => 'Proibido']);
expectDoc($status === 401, 'Cadastro bloqueia anônimo');

[$status, $admin] = docJson('GET', '/admin/documents', null, $token);
expectDoc($status === 200 && count($admin['categories']) === 1, 'Categoria padrão criada pela migração 004');
$catId = (int) $admin['categories'][0]['id'];

[$status, $empty] = docJson('GET', '/documents');
expectDoc($status === 200 && count($empty['documents']) === 0, 'Catálogo começa sem documentos publicados');

$pdf = validPdf();
[$formBody, $formMime] = docMultipart('manual.pdf', $pdf, 'application/pdf');
[$status, $raw] = docHttp('POST', '/admin/site/media', $formBody, $token, $formMime);
$uploaded = json_decode($raw, true);
expectDoc($status === 201 && isset($uploaded['id']) && $uploaded['kind'] === 'DOCUMENT',
    'Upload de PDF permitido com verificação MIME (HTTP ' . $status . ')');
$pdfId = (int) $uploaded['id'];

[$status] = docHttp('GET', '/site/media/' . $pdfId);
expectDoc($status === 404, 'PDF enviado sem publicação permanece privado');

[$status, $raw] = docHttp('GET', '/admin/site/media/' . $pdfId, null, $token);
expectDoc($status === 200 && $raw === $pdf, 'Prévia privada do PDF acessível por JWT');

[$status, $created] = docJson('POST', '/admin/documents', [
    'category_id' => $catId, 'media_id' => $pdfId,
    'title' => 'Guia de Microfones', 'description' => 'Guia didático de operação',
], $token);
expectDoc($status === 200 && $created['publication_status'] === 'DRAFT', 'Documento cadastrado como rascunho');
$id = (int) $created['id'];

[$status, $results] = docJson('GET', '/documents');
expectDoc($status === 200 && count($results['documents']) === 0, 'Rascunho não aparece no catálogo');
[$status] = docJson('GET', '/documents/' . $id);
expectDoc($status === 404, 'Metadados privados retornam 404');

[$status, $saved] = docJson('PUT', '/admin/documents/' . $id, [
    'publication_status' => 'PUBLISHED', 'is_visible' => true,
], $token);
expectDoc($status === 200 && $saved['publication_status'] === 'PUBLISHED', 'Documento publicado');

[$status, $found] = docJson('GET', '/documents?q=Microfones&category=' . $catId);
expectDoc($status === 200 && count($found['documents']) === 1, 'Busca por título e filtro por categoria');
[$status, $notFound] = docJson('GET', '/documents?q=contrabaixo');
expectDoc($status === 200 && count($notFound['documents']) === 0, 'Filtro exclui termos divergentes');

[$status, $detail] = docJson('GET', '/documents/' . $id);
expectDoc($status === 200 && (int) $detail['media_id'] === $pdfId &&
    $detail['mime_type'] === 'application/pdf', 'Documento publicado recuperável');

[$status, $file] = docHttp('GET', '/site/media/' . $pdfId);
expectDoc($status === 200 && $file === $pdf, 'PDF publicado servido com bytes preservados');

[$status, $range] = docHttp('GET', '/site/media/' . $pdfId, null, null, null); // Requisitado completo novamente
expectDoc($status === 200 && str_starts_with($range, '%PDF-'), 'PDF entregue como arquivo');
[$status] = docJson('POST', '/admin/documents', [
    'category_id' => $catId, 'media_id' => $pdfId, 'title' => 'Duplicado',
], $token);
expectDoc($status === 422, 'Arquivo não pode ser duplicado em outro documento');

[$status] = docJson('PUT', '/admin/documents/' . $id, ['is_visible' => false], $token);
expectDoc($status === 200, 'Documento ocultado');
[$status] = docHttp('GET', '/site/media/' . $pdfId);
expectDoc($status === 404, 'PDF oculto não fica público');

[$status] = docJson('DELETE', '/admin/documents/' . $id, null, $token);
expectDoc($status === 200, 'Cadastro excluído sem exclusão física');
[$status, $private] = docHttp('GET', '/admin/site/media/' . $pdfId, null, $token);
expectDoc($status === 200 && $private === $pdf, 'Original mantido após exclusão do cadastro');

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMwq50AAAIwAUQw5njCAAAAAElFTkSuQmCC', true);
[$formBody, $formMime] = docMultipart('diagrama.png', $png, 'image/png');
[$status, $raw] = docHttp('POST', '/admin/site/media', $formBody, $token, $formMime);
$uploaded = json_decode($raw, true);
expectDoc($status === 201 && $uploaded['kind'] === 'IMAGE', 'PNG continua funcionando sem regressão');
$pngId = (int) $uploaded['id'];
[$status, $pngDoc] = docJson('POST', '/admin/documents', [
    'category_id' => $catId, 'media_id' => $pngId, 'title' => 'Diagrama de sinal',
    'publication_status' => 'PUBLISHED',
], $token);
expectDoc($status === 200, 'Documento PNG publicado');
[$status, $bytes] = docHttp('GET', '/site/media/' . $pngId);
expectDoc($status === 200 && $bytes === $png, 'PNG publicado acessível');
echo "Biblioteca de documentos PDF e PNG validada em MariaDB descartável.\n";
