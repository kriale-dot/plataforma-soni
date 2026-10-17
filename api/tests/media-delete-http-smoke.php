<?php

declare(strict_types=1);

// DESTRUTIVO: exclusivo do banco MariaDB descartável do GitHub Actions.
// NUNCA executar no banco local do proprietário nem no Droplet de produção.
use Dotenv\Dotenv;
use Soni\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function mediaCheck(bool $pass, string $message): void
{
    if (!$pass) throw new RuntimeException('Falha: ' . $message);
    echo 'OK: ' . $message . PHP_EOL;
}

function mediaHttp(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    $headers = ['Accept: application/json'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 25,
    ]]);
    $raw = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $ctx);
    if ($raw === false) throw new RuntimeException('Requisição HTTP falhou');
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), json_decode($raw, true)];
}

$db = Database::connect();
[$code, $login] = mediaHttp('POST', '/admin/login', ['email'=>'ci@example.test', 'password'=>'ci-password']);
mediaCheck($code === 200 && isset($login['token']), 'JWT de teste disponível');
$jwt = $login['token'];
$root = dirname(__DIR__) . '/storage/site_media';
$fixture = function (string $kind, string $extension, bool $missing = false) use ($db, $root): array {
    $random = bin2hex(random_bytes(16));
    $relative = substr($random, 0, 2) . '/' . $random . '.' . $extension;
    $full = $root . '/' . $relative;
    if (!$missing) {
        if (!is_dir(dirname($full)) && !mkdir(dirname($full), 0770, true)) throw new RuntimeException('Falha ao criar pasta');
        file_put_contents($full, 'ci-media-delete-fixture-' . $kind);
    }
    $stmt = $db->prepare('INSERT INTO site_media (kind, original_name, storage_path, mime_type, size_bytes) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$kind, 'fixture-' . $random . '.' . $extension, $relative,
        $kind === 'DOCUMENT' ? 'application/pdf' : ($kind === 'VIDEO' ? 'video/mp4' : 'image/png'), 40]);
    return [(int) $db->lastInsertId(), $full];
};

[$imageId, $imagePath] = $fixture('IMAGE', 'png');
[$documentId, $documentPath] = $fixture('DOCUMENT', 'pdf');
[$videoId, $videoPath] = $fixture('VIDEO', 'mp4');
[$orphanId, $orphanPath] = $fixture('IMAGE', 'png');
[$missingId, $missingPath] = $fixture('IMAGE', 'png', true);

$block = $db->prepare("INSERT INTO site_blocks (type, title, media_id, publication_status) VALUES ('IMAGEM','Bloco CI apagar mídia',?,'PUBLISHED')");
$block->execute([$imageId]);
$blockId = (int) $db->lastInsertId();
$db->prepare('UPDATE site_settings SET logo_media_id = ? WHERE id = 1')->execute([$imageId]);

$categoryId = (int) $db->query('SELECT id FROM quiz_categories ORDER BY id LIMIT 1')->fetchColumn();
$db->prepare("INSERT INTO quizzes (category_id, title, publication_status) VALUES (?, 'Teste CI apagar mídia', 'PUBLISHED')")
   ->execute([$categoryId]);
$quizId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO quiz_questions (quiz_id, image_media_id, prompt_text) VALUES (?, ?, 'Questão CI')")
   ->execute([$quizId, $imageId]);
$questionId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO quiz_options (question_id, audio_media_id, option_text, is_correct) VALUES (?, ?, 'Opção CI', 1)")
   ->execute([$questionId, $imageId]);
$optionId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO quiz_attempts (quiz_id, total_questions, correct_answers, score_percent) VALUES (?, 1, 1, 100)')
   ->execute([$quizId]);
$attemptId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO quiz_attempt_answers (attempt_id, question_id, option_id, was_correct) VALUES (?, ?, ?, 1)')
   ->execute([$attemptId, $questionId, $optionId]);

$docCategory = (int) $db->query('SELECT id FROM document_categories LIMIT 1')->fetchColumn();
$db->prepare("INSERT INTO documents (category_id, media_id, title, publication_status) VALUES (?, ?, 'Documento CI', 'PUBLISHED')")
   ->execute([$docCategory, $documentId]);
$videoCategory = (int) $db->query('SELECT id FROM video_categories LIMIT 1')->fetchColumn();
$db->prepare("INSERT INTO videos (category_id, media_id, source_type, title, publication_status) VALUES (?, ?, 'UPLOAD', 'Vídeo CI', 'PUBLISHED')")
   ->execute([$videoCategory, $videoId]);

[$code] = mediaHttp('GET', '/admin/media');
mediaCheck($code === 401, 'Listagem de mídias exige JWT');
[$code, $mediaList] = mediaHttp('GET', '/admin/media?limit=100&offset=0', null, $jwt);
mediaCheck($code === 200 && isset($mediaList['items']), 'Listagem autenticada');
$imageRow = current(array_filter($mediaList['items'], fn ($r) => (int) $r['id'] === $imageId));
mediaCheck(is_array($imageRow) && (int) $imageRow['used_count'] >= 4, 'Referências de mídias em uso identificadas');
[$code] = mediaHttp('DELETE', '/admin/media/' . $imageId, ['confirmation' => 'EXCLUIR']);
mediaCheck($code === 401, 'Exclusão sem JWT rejeitada');
[$code] = mediaHttp('DELETE', '/admin/media/' . $imageId, [], $jwt);
mediaCheck($code === 422 && is_file($imagePath), 'Exclusão sem confirmação rejeitada sem afetar arquivo');

[$code, $deleted] = mediaHttp('DELETE', '/admin/media/' . $imageId, ['confirmation' => 'EXCLUIR'], $jwt);
mediaCheck($code === 200 && ($deleted['deleted'] ?? false) === true && ($deleted['physical_deleted'] ?? false) === true, 'Imagem em uso excluída');
clearstatcache(true, $imagePath);
mediaCheck(!is_file($imagePath), 'Bytes removidos do disco');
mediaCheck((int) ($deleted['blocks_hidden'] ?? 0) === 1 && (int) ($deleted['quizzes_unpublished'] ?? 0) === 1, 'Bloco ocultado e teste movido para rascunho');
$stmt = $db->prepare('SELECT media_id, is_visible FROM site_blocks WHERE id = ?');
$stmt->execute([$blockId]);
$storedBlock = $stmt->fetch();
mediaCheck($storedBlock['media_id'] === null && (int) $storedBlock['is_visible'] === 0, 'Bloco sem referência e oculto');
mediaCheck($db->query('SELECT logo_media_id FROM site_settings WHERE id=1')->fetchColumn() === null, 'Referência ao logotipo removida');
$stmt = $db->prepare('SELECT publication_status FROM quizzes WHERE id=?');
$stmt->execute([$quizId]);
mediaCheck($stmt->fetchColumn() === 'DRAFT', 'Quiz relacionado saiu do público');
$stmt = $db->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE id=?');
$stmt->execute([$attemptId]);
mediaCheck((int) $stmt->fetchColumn() === 1, 'Nota anterior preservada');
$stmt = $db->prepare('SELECT image_media_id FROM quiz_questions WHERE id=?');
$stmt->execute([$questionId]);
mediaCheck($stmt->fetchColumn() === null, 'Questão desvinculada');
$stmt = $db->prepare('SELECT audio_media_id FROM quiz_options WHERE id=?');
$stmt->execute([$optionId]);
mediaCheck($stmt->fetchColumn() === null, 'Alternativa desvinculada');

foreach ([[$documentId, $documentPath, 'documents'], [$videoId, $videoPath, 'videos']] as [$id, $path, $table]) {
    [$code, $out] = mediaHttp('DELETE', '/admin/media/' . $id, ['confirmation'=>'EXCLUIR'], $jwt);
    mediaCheck($code === 200 && $out['physical_deleted'] === true && !is_file($path), 'Arquivo em uso removido: ' . $table);
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE media_id = ?");
    $stmt->execute([$id]);
    mediaCheck((int) $stmt->fetchColumn() === 0, 'Cadastro dependente removido: ' . $table);
}
[$code, $out] = mediaHttp('DELETE', '/admin/media/' . $orphanId, ['confirmation'=>'EXCLUIR'], $jwt);
mediaCheck($code === 200 && $out['physical_deleted'] === true && !is_file($orphanPath), 'Arquivo sem uso removido');
[$code] = mediaHttp('DELETE', '/admin/media/' . $orphanId, ['confirmation'=>'EXCLUIR'], $jwt);
mediaCheck($code === 422, 'Exclusão repetida rejeitada');
[$code] = mediaHttp('DELETE', '/admin/media/' . $missingId, ['confirmation'=>'EXCLUIR'], $jwt);
mediaCheck($code === 503, 'Arquivo físico ausente impede operação');
$stmt = $db->prepare('SELECT COUNT(*) FROM site_media WHERE id = ?');
$stmt->execute([$missingId]);
mediaCheck((int) $stmt->fetchColumn() === 1, 'Banco preservado após erro no arquivo');

echo "Exclusão de mídias em uso concluída no banco descartável do CI.\n";
