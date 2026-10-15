<?php

declare(strict_types=1);

// TESTE DESTRUTIVO SOMENTE NO BANCO DESCARTÁVEL DO CI.
// NÃO EXECUTAR NO BANCO DO RESPONSÁVEL.
use Dotenv\Dotenv;
use Soni\Database;
use Soni\SiteMaintenance;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function ensure(bool $pass, string $message): void
{
    if (!$pass) throw new RuntimeException('Falhou: ' . $message);
    echo 'OK: ' . $message . PHP_EOL;
}

$db = Database::connect();
$root = dirname(__DIR__) . '/storage/site_media';
$files = [];
$ids = [];
$blockId = (int) $db->query('SELECT id FROM site_blocks ORDER BY id LIMIT 1')->fetchColumn();
ensure($blockId > 0, 'Bloco de teste encontrado');
$stmt = $db->prepare('SELECT media_id FROM site_blocks WHERE id = ?');
$stmt->execute([$blockId]);
$originalMediaId = $stmt->fetchColumn();
$originalMediaId = $originalMediaId === null ? null : (int) $originalMediaId;

try {
    foreach (['orphan', 'in-use'] as $kind) {
        $random = bin2hex(random_bytes(16));
        $relative = substr($random, 0, 2) . '/' . $random . '.png';
        $full = $root . '/' . $relative;
        if (!is_dir(dirname($full))) mkdir(dirname($full), 0770, true);
        $bytes = 'test-fixture-' . $kind;
        ensure(file_put_contents($full, $bytes) === strlen($bytes), 'Arquivo de fixture criado');
        $files[$kind] = ['full' => $full, 'relative' => $relative, 'bytes' => $bytes];
        $stmt = $db->prepare(
            "INSERT INTO site_media (kind, original_name, storage_path, mime_type, size_bytes, created_at)
             VALUES ('IMAGE', ?, ?, 'image/png', ?, DATE_SUB(NOW(), INTERVAL 45 DAY))"
        );
        $stmt->execute([$kind . '.png', $relative, strlen($bytes)]);
        $ids[$kind] = (int) $db->lastInsertId();
    }
    $stmt = $db->prepare('UPDATE site_blocks SET media_id = ? WHERE id = ?');
    $stmt->execute([$ids['in-use'], $blockId]);

    $candidates = SiteMaintenance::orphans($db, 30, 25);
    $candidateIds = array_map('intval', array_column($candidates, 'id'));
    ensure(in_array($ids['orphan'], $candidateIds, true), 'Mídia sem vínculo é candidata');
    ensure(!in_array($ids['in-use'], $candidateIds, true), 'Mídia em uso protegida');
    ensure(is_file($files['orphan']['full']), 'Simulação não move o arquivo');

    $backup = SiteMaintenance::backup($db);
    $manifest = SiteMaintenance::verify($backup);
    ensure(is_file($backup . '/database.sql'), 'Backup SQL gerado');
    ensure(isset($manifest['files']['media/' . $files['orphan']['relative']]), 'Backup de mídia órfã');
    ensure(isset($manifest['files']['media/' . $files['in-use']['relative']]), 'Backup de mídia em uso');

    $moved = SiteMaintenance::quarantine($db, $backup, 30, 25);
    $movedIds = array_map('intval', array_column($moved, 'id'));
    ensure(in_array($ids['orphan'], $movedIds, true), 'Órfã movida para quarentena');
    ensure(!in_array($ids['in-use'], $movedIds, true), 'Arquivo em uso nunca é movido');
    ensure(!is_file($files['orphan']['full']), 'Órfã fora da pasta de publicação');
    ensure(is_file($files['in-use']['full']), 'Arquivo referenciado preservado');
    ensure(is_file($backup . '/media/' . $files['orphan']['relative']), 'Arquivo original preservado no backup');
    $stmt = $db->prepare('SELECT COUNT(*) FROM site_media WHERE id = ?');
    $stmt->execute([$ids['orphan']]);
    ensure((int) $stmt->fetchColumn() === 0, 'Registro órfão removido com segurança');

    echo 'Backup e quarentena aprovados em MariaDB temporário.' . PHP_EOL;
} finally {
    $stmt = $db->prepare('UPDATE site_blocks SET media_id = ? WHERE id = ?');
    $stmt->execute([$originalMediaId, $blockId]);
}
