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
$syntheticIds = [];
$analyticsPdo = null;
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

    // Inserir visitas históricas fictícias somente no banco descartável do CI.
    // Isto verifica que as janelas de 7, 30 e 90 dias realmente alteram os números.
    $before = [];
    foreach ([7, 30, 90] as $period) {
        [$code, $result] = requestAnalytics('GET', '/admin/analytics?days=' . $period, null, $jwt);
        analyticsAssert($code === 200, 'Consulta inicial de ' . $period . ' dias');
        $before[$period] = $result;
    }

    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $analyticsPdo = \Soni\Database::connect();
    $localToday = new \DateTimeImmutable('today', new \DateTimeZone('America/Sao_Paulo'));
    foreach ([14 => '/documentos', 60 => '/videos'] as $age => $path) {
        $atUtc = $localToday->modify('-' . $age . ' days')->setTime(12, 0)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $key = hash('sha256', random_bytes(24));
        $analyticsPdo->prepare(
            'INSERT INTO analytics_sessions (session_key, started_at, last_seen_at, pageviews) VALUES (?, ?, ?, 1)'
        )->execute([$key, $atUtc, $atUtc]);
        $id = (int) $analyticsPdo->lastInsertId();
        $syntheticIds[] = $id;
        $analyticsPdo->prepare(
            'INSERT INTO analytics_pageviews (session_id, page_path, viewed_at) VALUES (?, ?, ?)'
        )->execute([$id, $path, $atUtc]);
    }

    foreach ([7 => 0, 30 => 1, 90 => 2] as $period => $expectedExtra) {
        [$code, $after] = requestAnalytics('GET', '/admin/analytics?days=' . $period, null, $jwt);
        analyticsAssert($code === 200 && count($after['series']) === $period, 'Série diária de ' . $period . ' dias');
        analyticsAssert(
            (int) $after['totals']['visits_period'] === (int) $before[$period]['totals']['visits_period'] + $expectedExtra,
            'Visitas filtradas em ' . $period . ' dias'
        );
        analyticsAssert(
            (int) $after['totals']['pageviews_period'] === (int) $before[$period]['totals']['pageviews_period'] + $expectedExtra,
            'Visualizações filtradas em ' . $period . ' dias'
        );
        analyticsAssert(
            array_sum(array_column($after['series'], 'visits')) === (int) $after['totals']['visits_period'],
            'Gráfico e cartão de visitas concordam no período de ' . $period . ' dias'
        );
        analyticsAssert(
            array_sum(array_column($after['series'], 'pageviews')) === (int) $after['totals']['pageviews_period'],
            'Gráfico e cartão de visualizações concordam no período de ' . $period . ' dias'
        );
        analyticsAssert(isset($after['totals']['attempts_period'], $after['start_date'], $after['end_date']),
            'Indicadores e datas do intervalo presentes');
    }

    [$status] = requestAnalytics('GET', '/admin/analytics?days=5', null, $jwt);
    analyticsAssert($status === 422, 'Período inválido rejeitado');
} finally {
    if ($analyticsPdo !== null && count($syntheticIds) > 0) {
        $delete = $analyticsPdo->prepare('DELETE FROM analytics_sessions WHERE id = ?');
        foreach ($syntheticIds as $id) $delete->execute([$id]);
    }
    requestAnalytics('PUT', '/admin/site/settings', $settings, $jwt);
}
[$status, $public] = requestAnalytics('GET', '/site');
analyticsAssert($status === 200 && !array_key_exists('visit_count', $public['settings']), 'Total ocultado quando desativado');
echo "Analytics HTTP aprovado.\n";
