<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Soni\Database;
use Soni\SiteMaintenance;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

try {
    $days = 30;
    $limit = 25;
    $backup = null;
    $apply = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--quarantine') $apply = true;
        elseif (str_starts_with($arg, '--backup=')) $backup = substr($arg, 9);
        elseif (str_starts_with($arg, '--days=')) $days = (int) substr($arg, 7);
        elseif (str_starts_with($arg, '--limit=')) $limit = (int) substr($arg, 8);
        else throw new RuntimeException('Argumento inválido: ' . $arg);
    }
    if (!$apply && $backup !== null) throw new RuntimeException('--backup só deve ser usado com --quarantine.');
    $db = Database::connect();
    $orphaned = SiteMaintenance::orphans($db, $days, $limit);
    echo 'Mídias sem referência e mais antigas que ' . $days . ' dias: ' . count($orphaned) . PHP_EOL;
    foreach ($orphaned as $row) {
        echo 'ID ' . $row['id'] . ' | ' . $row['created_at'] . ' | ' . $row['original_name'] . PHP_EOL;
    }
    if (!$apply) {
        echo 'Simulação: nenhum registro ou arquivo foi modificado.' . PHP_EOL;
        echo 'Para colocar em quarentena, faça backup recente e execute --quarantine --backup=CAMINHO_DO_BACKUP.' . PHP_EOL;
    } else {
        if ($backup === null || $backup === '') {
            throw new RuntimeException('Quarentena exige --backup=CAMINHO de um backup recente e íntegro.');
        }
        $results = SiteMaintenance::quarantine($db, $backup, $days, $limit);
        echo count($results) . ' mídia(s) movida(s) para a pasta de quarentena; sem exclusão permanente.' . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Auditoria de mídias interrompida: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
