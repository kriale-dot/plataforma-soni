<?php

declare(strict_types=1);

// DESTRUTIVO: executar SOMENTE em banco descartável do GitHub Actions.
// NÃO executar no banco do usuário ou na produção.
use Dotenv\Dotenv;
use Soni\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function demandDeletion(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Falhou: ' . $message);
    echo 'OK: ' . $message . PHP_EOL;
}

function deleteRequest(string $method, string $path, ?array $data = null, ?string $token = null): array
{
    $headers = ['Accept: application/json'];
    if ($data !== null) $headers[] = 'Content-Type: application/json';
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $data === null ? '' : json_encode($data, JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $raw = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $context);
    if ($raw === false) throw new RuntimeException('Falha HTTP ' . $method . ' ' . $path);
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $matches);
    return [(int) ($matches[1] ?? 0), json_decode($raw, true)];
}

$db = Database::connect();
[$status, $login] = deleteRequest('POST', '/admin/login', [
    'email' => 'ci@example.test', 'password' => 'ci-password',
]);
demandDeletion($status === 200 && isset($login['token']), 'Admin autenticado');
$token = $login['token'];

[$status, $catalog] = deleteRequest('GET', '/admin/quizzes', null, $token);
demandDeletion($status === 200 && isset($catalog['categories'][0]['id']), 'Categoria disponível');
$categoryId = (int) $catalog['categories'][0]['id'];
$mediaCount = (int) $db->query('SELECT COUNT(*) FROM site_media')->fetchColumn();

[$status, $created] = deleteRequest('POST', '/admin/quizzes', [
    'title' => 'Teste com tentativas CI', 'category_id' => $categoryId,
], $token);
demandDeletion($status === 200 && isset($created['id']), 'Teste criado');
$id = (int) $created['id'];

[$status, $question] = deleteRequest('POST', '/admin/quizzes/' . $id . '/questions', [
    'prompt_text' => 'Teste de exclusão', 'explanation' => 'Resposta correta: A',
    'options' => [
        ['option_text' => 'A', 'is_correct' => true],
        ['option_text' => 'B', 'is_correct' => false],
    ],
], $token);
demandDeletion($status === 200 && isset($question['id']), 'Questão criada');
$questionId = (int) $question['id'];
$optionId = (int) $question['options'][0]['id'];

[$status] = deleteRequest('PUT', '/admin/quizzes/' . $id, ['publication_status' => 'PUBLISHED'], $token);
demandDeletion($status === 200, 'Teste publicado');
[$status, $attempt] = deleteRequest('POST', '/quizzes/' . $id . '/attempts', [
    'answers' => [['question_id' => $questionId, 'option_id' => $optionId]],
]);
demandDeletion($status === 201 && isset($attempt['attempt_id']), 'Tentativa concluída');
$attemptId = (int) $attempt['attempt_id'];

[$status] = deleteRequest('DELETE', '/admin/quizzes/' . $id);
demandDeletion($status === 401, 'Exclusão sem autenticação recusada');
$stmt = $db->prepare('SELECT COUNT(*) FROM quizzes WHERE id = ?');
$stmt->execute([$id]);
demandDeletion((int) $stmt->fetchColumn() === 1, 'Teste intacto após acesso negado');

[$status, $result] = deleteRequest('DELETE', '/admin/quizzes/' . $id, null, $token);
demandDeletion($status === 200 && ($result['deleted'] ?? false) === true
    && (int) ($result['attempts_deleted'] ?? -1) === 1, 'Teste respondido excluído');
foreach ([
    ['quizzes', 'id', $id],
    ['quiz_questions', 'id', $questionId],
    ['quiz_options', 'id', $optionId],
    ['quiz_attempts', 'id', $attemptId],
    ['quiz_attempt_answers', 'attempt_id', $attemptId],
    ['quiz_attempt_feedback', 'attempt_id', $attemptId],
    ['quiz_feedback_ranges', 'quiz_id', $id],
] as [$table, $column, $value]) {
    // Tabelas e colunas são constantes definidas pelo teste, nunca input externo.
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
    $stmt->execute([$value]);
    demandDeletion((int) $stmt->fetchColumn() === 0, 'Sem registros restantes: ' . $table);
}
[$status] = deleteRequest('GET', '/quizzes/' . $id);
demandDeletion($status === 404, 'Teste não aparece para visitante');
demandDeletion((int) $db->query('SELECT COUNT(*) FROM site_media')->fetchColumn() === $mediaCount,
    'Biblioteca de mídias preservada');

[$status, $draft] = deleteRequest('POST', '/admin/quizzes', [
    'title' => 'Rascunho a excluir CI', 'category_id' => $categoryId,
], $token);
demandDeletion($status === 200 && isset($draft['id']), 'Rascunho criado');
[$status, $result] = deleteRequest('DELETE', '/admin/quizzes/' . $draft['id'], null, $token);
demandDeletion($status === 200 && ($result['deleted'] ?? false) === true
    && (int) ($result['attempts_deleted'] ?? -1) === 0, 'Rascunho excluído');

echo "Exclusão permanente de testes respondidos e rascunhos aprovada.\n";
