<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Soni\Database;
use Soni\SiteMaintenance;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

try {
    if ($argc > 2) throw new RuntimeException('Uso: php bin/backup.php [--verify=CAMINHO]');
    if (isset($argv[1])) {
        if (!str_starts_with($argv[1], '--verify=')) throw new RuntimeException('Argumento inválido.');
        $snapshot = substr($argv[1], strlen('--verify='));
        $manifest = SiteMaintenance::verify($snapshot);
        echo 'Backup íntegro: ' . count($manifest['files']) . ' arquivo(s) verificados.' . PHP_EOL;
    } else {
        $path = SiteMaintenance::backup(Database::connect());
        echo 'Backup concluído e verificado.' . PHP_EOL;
        echo 'Diretório: ' . $path . PHP_EOL;
        echo 'IMPORTANTE: copie esse diretório para outro disco/dispositivo seguro.' . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Backup não concluído: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
