<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Soni\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

try {
    $pdo = Database::connect();
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(180) PRIMARY KEY,
        checksum CHAR(64) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $files = glob(dirname(__DIR__) . '/migrations/*.sql');
    if ($files === false) {
        throw new RuntimeException('Não foi possível localizar os arquivos de migração.');
    }
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $version = basename($file);
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Migração vazia ou ilegível: ' . $version);
        }

        $checksum = hash('sha256', $sql);
        $stmt = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
        $stmt->execute([$version]);
        $saved = $stmt->fetchColumn();
        if ($saved !== false) {
            if (!hash_equals((string) $saved, $checksum)) {
                throw new RuntimeException('Migração já aplicada foi modificada: ' . $version);
            }
            echo 'Já aplicada: ' . $version . PHP_EOL;
            continue;
        }

        // Somente SQL simples (sem procedures/triggers ou ; embutido em texto).
        $statements = preg_split('/;\s*(?:\r\n|\r|\n|$)/', trim($sql));
        if ($statements === false) {
            throw new RuntimeException('Erro ao interpretar: ' . $version);
        }
        foreach ($statements as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec(trim($statement));
            }
        }

        $insert = $pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)');
        $insert->execute([$version, $checksum]);
        echo 'Aplicada: ' . $version . PHP_EOL;
    }
    echo 'Migrações concluídas.' . PHP_EOL;
} catch (Throwable $error) {
    // DDL pode realizar commits implícitos; não afirmar que houve rollback.
    fwrite(STDERR, 'Migração falhou: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
