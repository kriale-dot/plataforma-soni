<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Soni\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function assertEar(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Teste de ouvido 01: ' . $message);
    }
}

$db = Database::connect();
$title = 'Teste de Ouvido 01 — Identificação de Frequências';
$statement = $db->prepare('SELECT id, publication_status FROM quizzes WHERE title = ?');
$statement->execute([$title]);
$quiz = $statement->fetch(PDO::FETCH_ASSOC);
assertEar(is_array($quiz), 'teste não foi criado');
assertEar($quiz['publication_status'] === 'DRAFT', 'o padrão deve ser rascunho');
$id = (int) $quiz['id'];

$questions = $db->prepare(
    'SELECT qq.id, qq.audio_media_id, qq.explanation, '
    . '(SELECT COUNT(*) FROM quiz_options o WHERE o.question_id = qq.id) AS nopts, '
    . '(SELECT COUNT(*) FROM quiz_options o WHERE o.question_id = qq.id AND is_correct=1) AS ncorrect '
    . 'FROM quiz_questions qq WHERE qq.quiz_id = ? ORDER BY qq.sort_order, qq.id'
);
$questions->execute([$id]);
$rows = $questions->fetchAll(PDO::FETCH_ASSOC);
assertEar(count($rows) === 12, 'esperadas 12 questões');
foreach ($rows as $i => $question) {
    assertEar((int) $question['nopts'] === 3, 'a questão ' . ($i + 1) . ' deve ter 3 alternativas');
    assertEar((int) $question['ncorrect'] === 1, 'a questão ' . ($i + 1) . ' deve ter uma resposta correta');
    assertEar(trim((string) $question['explanation']) !== '', 'explicação vazia');
    if ($i < 6) {
        assertEar($question['audio_media_id'] !== null, 'as 6 primeiras questões exigem áudio');
    } else {
        assertEar($question['audio_media_id'] === null, 'questões comparativas usam áudio nas opções');
    }
}
$optionMedia = $db->prepare(
    'SELECT COUNT(*) FROM quiz_options qo '
    . 'JOIN quiz_questions qq ON qq.id = qo.question_id '
    . 'WHERE qq.quiz_id = ? AND qo.audio_media_id IS NOT NULL'
);
$optionMedia->execute([$id]);
assertEar((int) $optionMedia->fetchColumn() === 18, 'faltam áudios nas 18 opções comparativas');

$feedback = $db->prepare('SELECT COUNT(*) FROM quiz_feedback_ranges WHERE quiz_id = ?');
$feedback->execute([$id]);
assertEar((int) $feedback->fetchColumn() === 5, 'faltam faixas de desempenho');

$media = $db->prepare(
    "SELECT DISTINCT m.storage_path, m.size_bytes, m.mime_type FROM site_media m "
    . "JOIN (SELECT audio_media_id AS mid FROM quiz_questions WHERE quiz_id = ? "
    . "UNION SELECT o.audio_media_id AS mid FROM quiz_options o "
    . "JOIN quiz_questions q ON q.id=o.question_id WHERE q.quiz_id=?) ids ON ids.mid=m.id "
    . "WHERE m.kind = 'AUDIO'"
);
$media->execute([$id, $id]);
$files = $media->fetchAll(PDO::FETCH_ASSOC);
assertEar(count($files) === 11, 'esperados 11 WAVs distintos');
foreach ($files as $file) {
    assertEar($file['mime_type'] === 'audio/x-wav', 'MIME inválido');
    $path = dirname(__DIR__) . '/storage/site_media/' . $file['storage_path'];
    assertEar(is_file($path), 'áudio não foi gravado');
    $content = file_get_contents($path, false, null, 0, 44);
    assertEar($content !== false && strlen($content) === 44, 'cabeçalho WAV incompleto');
    assertEar(substr($content, 0, 4) === 'RIFF' && substr($content, 8, 4) === 'WAVE', 'WAV inválido');
    assertEar(substr($content, 12, 4) === 'fmt ' && substr($content, 36, 4) === 'data', 'blocos WAV inválidos');
    assertEar(filesize($path) === 176444, 'áudio deve ter 2 segundos PCM mono 44.1 kHz 16-bit');
    assertEar((int) $file['size_bytes'] === filesize($path), 'tamanho no banco diverge');
}

echo 'Teste de ouvido 01: 12 questões, 36 opções, 11 áudios WAV, rascunho e devolutivas OK.' . PHP_EOL;
