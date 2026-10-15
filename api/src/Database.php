<?php

declare(strict_types=1);

namespace Soni;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(): PDO
    {
        $host = trim((string) ($_ENV['DB_HOST'] ?? 'localhost'));
        $port = trim((string) ($_ENV['DB_PORT'] ?? '3306'));
        $name = trim((string) ($_ENV['DB_NAME'] ?? ''));
        $user = trim((string) ($_ENV['DB_USER'] ?? ''));
        $password = (string) ($_ENV['DB_PASSWORD'] ?? '');

        if ($host === '' || $name === '' || $user === '' ||
            !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535 ||
            !preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
            throw new RuntimeException('Variáveis DB_* inválidas ou incompletas no api/.env.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }
}
