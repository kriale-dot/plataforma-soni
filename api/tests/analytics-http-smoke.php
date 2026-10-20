<?php

declare(strict_types=1);

// Somente no banco descartável do GitHub Actions. Não executar em produção.
function requestAnalytics(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    $headers = ['Accept: application/json'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $response = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $context);
    if ($response === false) throw new RuntimeException("Falha HTTP: {$path}");
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $match);
    return [(int) ($match[1] ?? 0), json_decode($response, true, 512, JSON_THROW_ON_ERROR)];
}

function analyticsAssert(bool $ok, string $description): void
{
    if (!$ok) throw new RuntimeException('FALHOU: ' . $description);
    echo 'OK: ' . $description . PHP_EOL;
}

[$status] = requestAnalytics('GET', '/admin/analytics');
analyticsAssert($status === 401, 'Relatório exige autenticação');
[$status] = requestAnalytics('GET', '/admin/analytics?days=400');
analyticsAssert($status === 401, 'Filtro não contorna autenticação');
[$status] = requestAnalytics('POST', '/analytics/visit', ['page' => '/', 'session_id' => 'incorrect']);
analyticsAssert($status === 422, 'Identificador inválido rejeitado');

[$status, $login] = requestAnalytics('POST', '/admin/login', [
    'email' => 'ci@example.test', 'password' => 'ci-password',
]);
analyticsAssert($status === 200, 'Autenticação de teste');
$jwt = $login['token'];

[$status, $report] = requestAnalytics('GET', '/admin/analytics?days=30', null, $jwt);
analyticsAssert($status === 200 && count($report['series']) === 30, 'Relatório inicial com 30 dias');
$baseVisits = (int) $report['totals']['visits_all'];
$baseViews = (int) $report['totals']['pageviews_all'];

[$status, $admin] = requestAnalytics('GET', '/admin/site', null, $jwt);
analyticsAssert($status === 200, 'Configuração de exibição disponível');
$settings = $admin['settings'];
$session = '12345678-1234-4abc-8def-' . substr(bin2hex(random_bytes(6)), 0, 12);
try {
    [$status, $visit] = requestAnalytics('POST', '/analytics/visit', ['page' => '/', 'session_id' => $session]);
    analyticsAssert($status === 200 && $visit['tracked'] === true && $visit['visit_count'] === null, 'Contador desativado não expõe total');

    [$status] = requestAnalytics('POST', '/analytics/visit', ['page' => '/', 'session_id' => $session]);
    [$status] = requestAnalytics('POST', '/analytics/visit', ['page' => '/testes', 'session_id' => $session]);
    analyticsAssert($status === 200, 'Segunda página do mesmo visitante');

    [$status, $report] = requestAnalytics('GET', '/admin/analytics?days=7', null, $jwt);
    analyticsAssert(
        $status === 200 &&
        (int) $report['totals']['visits_all'] === $baseVisits + 1 &&
        (int) $report['totals']['pageviews_all'] === $baseViews + 2,
        'Visita única e deduplicação da visualização'
    );

    [$status] = requestAnalytics('PUT', '/admin/site/settings', array_merge($settings, ['show_visit_counter' => 1]), $jwt);
    analyticsAssert($status === 200, 'Ativar contador no editor');
    [$status, $public] = requestAnalytics('GET', '/site');
    analyticsAssert($status === 200 && (int) $public['settings']['visit_count'] === $baseVisits + 1, 'Total aparece no site quando ativado');

    [$status] = requestAnalytics('GET', '/admin/analytics?days=5', null, $jwt);
    analyticsAssert($status === 422, 'Período inválido rejeitado');
} finally {
    requestAnalytics('PUT', '/admin/site/settings', $settings, $jwt);
}
[$status, $public] = requestAnalytics('GET', '/site');
analyticsAssert($status === 200 && !array_key_exists('visit_count', $public['settings']), 'Total ocultado quando desativado');
echo "Analytics HTTP aprovado.\n";
