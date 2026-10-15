<?php

declare(strict_types=1);

// Teste HTTP SOMENTE no banco descartável do CI. Não executar no banco local.
use Dotenv\Dotenv;
use Soni\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function checkFeedback(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Falha: ' . $message);
    echo 'OK: ' . $message . PHP_EOL;
}

function feedbackHttp(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    $headers = ['Accept: application/json'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $raw = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $ctx);
    if ($raw === false) throw new RuntimeException('Falha HTTP: ' . $method . ' ' . $path);
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $matches);
    return [(int) ($matches[1] ?? 0), json_decode($raw, true, 512, JSON_THROW_ON_ERROR), $raw];
}

[$status, $login] = feedbackHttp('POST', '/admin/login', [
    'email' => 'ci@example.test', 'password' => 'ci-password',
]);
checkFeedback($status === 200 && is_string($login['token'] ?? null), 'Autenticação administrativa');
$token = $login['token'];
[$status, $categories] = feedbackHttp('GET', '/admin/quizzes', null, $token);
$categoryId = (int) $categories['categories'][0]['id'];

[$status, $quiz] = feedbackHttp('POST', '/admin/quizzes', [
    'category_id' => $categoryId,
    'title' => 'Teste de faixas CI',
    'description' => 'Teste automático de devolutivas',
], $token);
checkFeedback($status === 200 && isset($quiz['id']), 'Criação de teste');
$id = (int) $quiz['id'];
checkFeedback(count($quiz['feedback_ranges'] ?? []) === 5, 'Cinco faixas padrão ao criar teste');
checkFeedback($quiz['feedback_ranges'][0]['min_percent'] === '0.00' &&
    $quiz['feedback_ranges'][4]['max_percent'] === '100.00', 'Cobertura inicial de zero a cem');

$custom = [
    ['min_percent' => '0.00', 'max_percent' => '49.99', 'label' => 'Revisar',
        'feedback_text' => 'Estude mais os conceitos.', 'study_recommendation' => 'Revise o módulo básico.'],
    ['min_percent' => '50.00', 'max_percent' => '99.99', 'label' => 'Em progresso',
        'feedback_text' => 'Metade ou mais dos itens foi compreendida.', 'study_recommendation' => 'Pratique no equipamento.'],
    ['min_percent' => '100.00', 'max_percent' => '100.00', 'label' => 'Domínio excelente',
        'feedback_text' => 'Todas as questões corretas!', 'study_recommendation' => 'Ajude sua equipe.'],
];

[$status] = feedbackHttp('PUT', '/admin/quizzes/' . $id . '/feedback-ranges', ['ranges' => $custom]);
checkFeedback($status === 401, 'Sem JWT não altera faixas');

$gapped = $custom;
$gapped[1]['min_percent'] = '50.01';
[$status] = feedbackHttp('PUT', '/admin/quizzes/' . $id . '/feedback-ranges', ['ranges' => $gapped], $token);
checkFeedback($status === 422, 'Lacuna de 0,01% rejeitada');

$overlap = $custom;
$overlap[1]['min_percent'] = '49.99';
[$status] = feedbackHttp('PUT', '/admin/quizzes/' . $id . '/feedback-ranges', ['ranges' => $overlap], $token);
checkFeedback($status === 422, 'Faixas sobrepostas rejeitadas');

$invalid = $custom;
$invalid[1]['max_percent'] = '99.999';
[$status] = feedbackHttp('PUT', '/admin/quizzes/' . $id . '/feedback-ranges', ['ranges' => $invalid], $token);
checkFeedback($status === 422, 'Mais de duas casas decimais rejeitado');

[$status, $ranges] = feedbackHttp('PUT', '/admin/quizzes/' . $id . '/feedback-ranges', ['ranges' => $custom], $token);
checkFeedback($status === 200 && count($ranges) === 3 && $ranges[1]['label'] === 'Em progresso',
    'Faixas personalizadas gravadas');

$questionIds = [];
$correctIds = [];
$wrongIds = [];
for ($i = 0; $i < 2; $i++) {
    [$status, $created] = feedbackHttp('POST', '/admin/quizzes/' . $id . '/questions', [
        'prompt_text' => 'Questão de teste ' . ($i + 1),
        'options' => [
            ['option_text' => 'Certa', 'is_correct' => true],
            ['option_text' => 'Errada', 'is_correct' => false],
        ],
    ], $token);
    checkFeedback($status === 200, 'Questão ' . ($i + 1) . ' cadastrada');
    $questionIds[] = (int) $created['id'];
    $correctIds[] = (int) $created['options'][0]['id'];
    $wrongIds[] = (int) $created['options'][1]['id'];
}

[$status, $published] = feedbackHttp('PUT', '/admin/quizzes/' . $id, [
    'publication_status' => 'PUBLISHED',
], $token);
checkFeedback($status === 200, 'Teste com faixas válidas publicado');

[$status, $public, $raw] = feedbackHttp('GET', '/quizzes/' . $id);
checkFeedback($status === 200 && !isset($public['feedback_ranges']) &&
    !str_contains($raw, 'study_recommendation') &&
    !str_contains($raw, 'is_correct'), 'Devolutivas e gabarito não aparecem antes da finalização');

$scores = [
    [[false, false], 0, 'Revisar', 'Estude mais os conceitos.'],
    [[true, false], 50, 'Em progresso', 'Metade ou mais dos itens foi compreendida.'],
    [[true, true], 100, 'Domínio excelente', 'Todas as questões corretas!'],
];
$oldAttempt = null;
foreach ($scores as [$choices, $percent, $label, $message]) {
    $answers = [];
    foreach ($questionIds as $i => $qid) {
        $answers[] = [
            'question_id' => $qid,
            'option_id' => $choices[$i] ? $correctIds[$i] : $wrongIds[$i],
        ];
    }
    [$status, $result] = feedbackHttp('POST', '/quizzes/' . $id . '/attempts', [
        'answers' => $answers,
    ]);
    checkFeedback($status === 201 && (int) $result['score_percent'] === $percent &&
        ($result['performance_feedback']['label'] ?? '') === $label &&
        ($result['performance_feedback']['feedback_text'] ?? '') === $message &&
        isset($result['performance_feedback']['study_recommendation']),
        "Devolutiva correta aos {$percent}%");
    if ($percent === 50) $oldAttempt = (int) $result['attempt_id'];
}

$db = Database::connect();
$query = $db->prepare('SELECT label, feedback_text FROM quiz_attempt_feedback WHERE attempt_id = ?');
$query->execute([$oldAttempt]);
$snapshot = $query->fetch();
checkFeedback($snapshot['label'] === 'Em progresso' &&
    $snapshot['feedback_text'] === 'Metade ou mais dos itens foi compreendida.',
    'Devolutiva original registrada na tentativa');

$revised = $custom;
$revised[1]['feedback_text'] = 'Texto atualizado para as próximas tentativas.';
[$status] = feedbackHttp('PUT', '/admin/quizzes/' . $id . '/feedback-ranges', ['ranges' => $revised], $token);
checkFeedback($status === 200, 'É possível editar faixas mesmo após tentativas');
$query->execute([$oldAttempt]);
$snapshotAfter = $query->fetch();
checkFeedback($snapshotAfter === $snapshot, 'Alteração não modifica histórico de tentativas');

echo "Testes de faixas e devolutivas personalizados aprovados.\n";
