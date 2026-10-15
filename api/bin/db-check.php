<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Soni\Database;
use Soni\SiteRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

try {
    $pdo = Database::connect();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $site = SiteRepository::readPublic($pdo);

    echo 'Conexão PDO: OK' . PHP_EOL;
    echo 'Banco: ' . ($_ENV['DB_NAME'] ?? '?') . PHP_EOL;
    echo 'Servidor: ' . $version . PHP_EOL;
    echo 'Site institucional: ' . $site['settings']['site_title'] . PHP_EOL;
    echo 'Blocos publicados: ' . count($site['blocks']) . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Erro ao consultar banco. Confira api/.env, pdo_mysql e migrações.' . PHP_EOL);
    exit(1);
}
