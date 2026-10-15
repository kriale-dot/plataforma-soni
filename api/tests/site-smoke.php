<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Soni\Database;
use Soni\SiteRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$pdo = Database::connect();
$site = SiteRepository::readPublic($pdo);

function verify(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FALHOU: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

verify(count($site['blocks']) === 5, 'Cinco blocos publicados na migração inicial');
verify(($site['settings']['site_title'] ?? '') === 'SONI | Sonorização de Igrejas', 'Configuração inicial persistida');

$id = (int) $site['blocks'][0]['id'];
$pdo->prepare("UPDATE site_blocks SET is_visible = 0 WHERE id = ?")->execute([$id]);
try {
    verify(count(SiteRepository::readPublic($pdo)['blocks']) === 4, 'Blocos ocultos não aparecem na API pública');
    $pdo->prepare("UPDATE site_blocks SET publication_status = 'DRAFT' WHERE id = ?")->execute([$id]);
    $pdo->prepare("UPDATE site_blocks SET is_visible = 1 WHERE id = ?")->execute([$id]);
    verify(count(SiteRepository::readPublic($pdo)['blocks']) === 4, 'Blocos em rascunho não aparecem na API pública');
} finally {
    $pdo->prepare("UPDATE site_blocks SET is_visible = 1, publication_status = 'PUBLISHED' WHERE id = ?")->execute([$id]);
}

verify(count(SiteRepository::readPublic($pdo)['blocks']) === 5, 'Estado inicial restaurado');
echo "Validação da API pública concluída.\n";
