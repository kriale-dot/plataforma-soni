<?php

declare(strict_types=1);

// Teste isolado: utiliza credenciais fictícias geradas durante a execução.
// Não lê arquivos .env locais nem realiza requisições externas.

require dirname(__DIR__) . '/vendor/autoload.php';

use Soni\AdminAuth;

$_ENV['ADMIN_EMAIL'] = 'admin@example.test';
$_ENV['ADMIN_PASSWORD_HASH'] = password_hash('SenhaDeTeste!123', PASSWORD_DEFAULT);
$_ENV['JWT_SECRET'] = bin2hex(random_bytes(32));

function check(bool $condition, string $description): void
{
    if (!$condition) {
        fwrite(STDERR, "FALHOU: {$description}\n");
        exit(1);
    }
    echo "OK: {$description}\n";
}

check(AdminAuth::configured(), 'Configuração fictícia válida');

check(
    AdminAuth::login('admin@example.test', 'senha-errada') === null,
    'Senha incorreta é rejeitada'
);

check(
    AdminAuth::login('outro@example.test', 'SenhaDeTeste!123') === null,
    'E-mail incorreto é rejeitado'
);

$token = AdminAuth::login('admin@example.test', 'SenhaDeTeste!123');
check(is_string($token) && $token !== '', 'Credenciais corretas geram JWT');

check(AdminAuth::isValidBearer("Bearer {$token}"), 'JWT correto é validado');
check(!AdminAuth::isValidBearer('Bearer token-invalido'), 'JWT inválido é rejeitado');
check(!AdminAuth::isValidBearer(''), 'Ausência de token é rejeitada');

$parts = explode('.', $token);
check(count($parts) === 3, 'JWT tem três segmentos');
$signature = $parts[2];
$parts[2] = ($signature[0] === 'A' ? 'B' : 'A') . substr($signature, 1);
check(!AdminAuth::isValidBearer('Bearer ' . implode('.', $parts)), 'JWT alterado é rejeitado');

echo "Todos os testes iniciais de autenticação passaram.\n";
