<?php

declare(strict_types=1);

// Testa política CORS no servidor HTTP descartável do CI.
function corsHeaders(string $origin, string $method = 'GET'): array
{
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => "Origin: {$origin}\r\nAccess-Control-Request-Method: POST\r\nAccess-Control-Request-Headers: Content-Type, Authorization\r\n",
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $response = file_get_contents('http://127.0.0.1:8282/api/health', false, $context);
    if ($response === false) throw new RuntimeException('Falha HTTP ao conferir CORS.');
    $headers = [];
    foreach ($http_response_header as $header) {
        if (str_contains($header, ':')) {
            [$name, $value] = explode(':', $header, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
    }
    return $headers;
}

$allowed = corsHeaders('http://localhost:5173', 'OPTIONS');
if (($allowed['access-control-allow-origin'] ?? null) !== 'http://localhost:5173') {
    throw new RuntimeException('CORS bloqueou a origem de desenvolvimento autorizada.');
}
if (!str_contains($allowed['access-control-allow-headers'] ?? '', 'Authorization')) {
    throw new RuntimeException('Preflight não autoriza cabeçalho de autenticação.');
}
$blocked = corsHeaders('https://example.invalid', 'OPTIONS');
if (isset($blocked['access-control-allow-origin'])) {
    throw new RuntimeException('CORS autorizou uma origem desconhecida.');
}
echo "CORS: origem autorizada e origem desconhecida verificadas.\n";
