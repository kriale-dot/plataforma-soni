<?php

declare(strict_types=1);

// Impede a execucao acidental fora do GitHub Actions.
if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('CI') !== 'true') {
    fwrite(STDERR, "BLOQUEADO: teste destrutivo permitido apenas no GitHub Actions.\n");
    exit(1);
}

// DESTRUTIVO: executar apenas no banco DESCARTÁVEL do GitHub Actions.
// Nunca executar na base local do responsável nem no servidor de produção.
use Dotenv\Dotenv;
use Soni\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function questionCheck(bool $ok, string $description): void
{
    if (!$ok) throw new RuntimeException('Falhou: ' . $description);
    echo 'OK: ' . $description . PHP_EOL;
}

function questionApi(string $method, string $path, ?array $body = null, ?string $token = null): array
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
    if ($raw === false) throw new RuntimeException('Falha HTTP ' . $method . ' ' . $path);
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $matches);
    return [(int) ($matches[1] ?? 0), json_decode($raw, true)];
}

function questionCount(PDO $db, string $table, string $column, int $id): int
{
    // Os nomes de tabelas e colunas são constantes controladas pelo teste.
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
    $stmt->execute([$id]);
    return (int) $stmt->fetchColumn();
}

$db = Database::connect();
[$status, $login] = questionApi('POST', '/admin/login', ['email' => 'ci@example.test', 'password' => 'ci-password']);
questionCheck($status === 200 && isset($login['token']), 'Autenticação administrativa');
$token = $login['token'];
[$status, $catalog] = questionApi('GET', '/admin/quizzes', null, $token);
questionCheck($status === 200 && isset($catalog['categories'][0]['id']), 'Categoria de teste disponível');
$categoryId = (int) $catalog['categories'][0]['id'];
$mediaBefore = (int) $db->query('SELECT COUNT(*) FROM site_media')->fetchColumn(); // Não alterar arquivos de mídia.

function createQuestionTest(int $categoryId, string $title, string $token): array
{
    [$status, $quiz] = questionApi('POST', '/admin/quizzes', [
        'title' => $title, 'category_id' => $categoryId,
    ], $token);
    questionCheck($status === 200 && isset($quiz['id']), 'Teste criado: ' . $title);
    return $quiz;
}

function addQuestion(int $quizId, string $text, string $token): array
{
    [$status, $question] = questionApi('POST', '/admin/quizzes/' . $quizId . '/questions', [
        'prompt_text' => $text,
        'options' => [
            ['option_text' => 'Correta', 'is_correct' => true],
            ['option_text' => 'Incorreta', 'is_correct' => false],
        ],
    ], $token);
    questionCheck($status === 200 && isset($question['id'], $question['options'][0]['id']), 'Questão criada: ' . $text);
    return $question;
}

$quizA = createQuestionTest($categoryId, 'CI Questão - excluir histórico', $token);
$idA = (int) $quizA['id'];
$q1 = addQuestion($idA, 'Primeira questão CI', $token);
$q2 = addQuestion($idA, 'Segunda questão CI', $token);
[$status] = questionApi('PUT', '/admin/quizzes/' . $idA, ['publication_status' => 'PUBLISHED'], $token);
questionCheck($status === 200, 'Teste A publicado');
[$status, $attempt] = questionApi('POST', '/quizzes/' . $idA . '/attempts', ['answers' => [
    ['question_id' => (int) $q1['id'], 'option_id' => (int) $q1['options'][0]['id']],
    ['question_id' => (int) $q2['id'], 'option_id' => (int) $q2['options'][0]['id']],
]]);
questionCheck($status === 201 && isset($attempt['attempt_id']), 'Histórico do teste A criado');
$attemptA = (int) $attempt['attempt_id'];
questionCheck(questionCount($db, 'quiz_attempt_answers', 'attempt_id', $attemptA) === 2, 'Duas respostas gravadas no teste A');
questionCheck(questionCount($db, 'quiz_attempt_feedback', 'attempt_id', $attemptA) === 1, 'Devolutiva do teste A gravada');

$quizB = createQuestionTest($categoryId, 'CI Questão - preservar outro teste', $token);
$idB = (int) $quizB['id'];
$qB = addQuestion($idB, 'Questão do outro teste', $token);
[$status] = questionApi('PUT', '/admin/quizzes/' . $idB, ['publication_status' => 'PUBLISHED'], $token);
questionCheck($status === 200, 'Teste B publicado');
[$status, $otherAttempt] = questionApi('POST', '/quizzes/' . $idB . '/attempts', [
    'answers' => [['question_id' => (int) $qB['id'], 'option_id' => (int) $qB['options'][0]['id']]],
]);
questionCheck($status === 201 && isset($otherAttempt['attempt_id']), 'Resultado do teste B registrado');
$otherAttemptId = (int) $otherAttempt['attempt_id'];

$deletePath = '/admin/quizzes/' . $idA . '/questions/' . (int) $q1['id'];
[$status] = questionApi('DELETE', $deletePath, ['confirmation' => 'EXCLUIR']);
questionCheck($status === 401, 'Exclusão sem JWT recusada');
[$status] = questionApi('DELETE', $deletePath, null, $token);
questionCheck($status === 422, 'Exclusão sem confirmação recusada');
[$status] = questionApi('DELETE', $deletePath, ['confirmation' => 'APAGAR'], $token);
questionCheck($status === 422, 'Confirmação incorreta recusada');
questionCheck(questionCount($db, 'quiz_attempts', 'quiz_id', $idA) === 1 &&
    questionCount($db, 'quiz_questions', 'id', (int) $q1['id']) === 1, 'Falhas não alteraram questões nem notas');
[$status] = questionApi('DELETE', '/admin/quizzes/' . $idB . '/questions/' . (int) $q1['id'],
    ['confirmation' => 'EXCLUIR'], $token);
questionCheck($status === 422, 'Questão não pode ser apagada através de outro teste');
questionCheck(questionCount($db, 'quiz_attempts', 'quiz_id', $idB) === 1, 'Histórico do outro teste permanece intacto');

[$status, $result] = questionApi('DELETE', $deletePath, ['confirmation' => 'EXCLUIR'], $token);
questionCheck($status === 200 && ($result['deleted'] ?? false) === true &&
    (int) ($result['attempts_deleted'] ?? -1) === 1 && ($result['unpublished'] ?? false) === true,
    'Exclusão de questão publicada com 1 tentativa concluída');
questionCheck(questionCount($db, 'quiz_questions', 'id', (int) $q1['id']) === 0 &&
    questionCount($db, 'quiz_options', 'question_id', (int) $q1['id']) === 0,
    'Questão excluída com suas alternativas');
questionCheck(questionCount($db, 'quiz_questions', 'id', (int) $q2['id']) === 1 &&
    questionCount($db, 'quiz_options', 'question_id', (int) $q2['id']) === 2,
    'Outra questão do mesmo teste foi preservada');
questionCheck(questionCount($db, 'quiz_attempts', 'quiz_id', $idA) === 0 &&
    questionCount($db, 'quiz_attempt_answers', 'attempt_id', $attemptA) === 0 &&
    questionCount($db, 'quiz_attempt_feedback', 'attempt_id', $attemptA) === 0,
    'Todas as notas, respostas e devolutivas do teste A foram apagadas');
$stmt = $db->prepare('SELECT publication_status FROM quizzes WHERE id = ?');
$stmt->execute([$idA]);
questionCheck($stmt->fetchColumn() === 'DRAFT', 'Teste A saiu da publicação para revisão');
questionCheck(questionCount($db, 'quiz_attempts', 'id', $otherAttemptId) === 1 &&
    questionCount($db, 'quiz_attempt_answers', 'attempt_id', $otherAttemptId) === 1,
    'Histórico e notas do teste B preservados');
[$status, $publicB] = questionApi('GET', '/quizzes/' . $idB);
questionCheck($status === 200 && count($publicB['questions'] ?? []) === 1,
    'Outro teste continua publicado');

[$status, $newQuestion] = questionApi('POST', '/admin/quizzes/' . $idA . '/questions', [
    'prompt_text' => 'Nova questão após remover histórico',
    'options' => [
        ['option_text' => 'Correta', 'is_correct' => true],
        ['option_text' => 'Incorreta', 'is_correct' => false],
    ],
], $token);
questionCheck($status === 200 && isset($newQuestion['id']),
    'Teste A pode voltar a ser editado depois da exclusão das tentativas');
[$status, $result] = questionApi('DELETE', '/admin/quizzes/' . $idA . '/questions/' . (int) $q2['id'],
    ['confirmation' => 'EXCLUIR'], $token);
questionCheck($status === 200 && (int) ($result['attempts_deleted'] ?? -1) === 0 &&
    ($result['unpublished'] ?? true) === false, 'Questão excluída em rascunho sem tentativas');
[$status] = questionApi('DELETE', $deletePath, ['confirmation' => 'EXCLUIR'], $token);
questionCheck($status === 422, 'Exclusão repetida de questão é recusada');

// O teste trabalha apenas com registros CI; descarte-os antes de encerrar.
[$status] = questionApi('DELETE', '/admin/quizzes/' . $idA, null, $token);
questionCheck($status === 200, 'Teste A descartável removido');
[$status] = questionApi('DELETE', '/admin/quizzes/' . $idB, null, $token);
questionCheck($status === 200, 'Teste B descartável removido');
questionCheck((int) $db->query('SELECT COUNT(*) FROM site_media')->fetchColumn() === $mediaBefore, 'Biblioteca de mídias preservada');
echo "Exclusão individual de questões e limpeza do histórico aprovadas em banco descartável.\n";
