<?php

declare(strict_types=1);

namespace Soni;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class AdminAuth
{
    public static function configured(): bool
    {
        return filter_var($_ENV['ADMIN_EMAIL'] ?? '', FILTER_VALIDATE_EMAIL) !== false
            && password_get_info((string) ($_ENV['ADMIN_PASSWORD_HASH'] ?? ''))['algo'] !== null
            && strlen((string) ($_ENV['JWT_SECRET'] ?? '')) >= 32
            && !str_starts_with((string) ($_ENV['JWT_SECRET'] ?? ''), 'SUBSTITUIR_');
    }

    public static function login(string $email, string $password): ?string
    {
        if (!self::configured()) {
            return null;
        }

        $adminEmail = (string) $_ENV['ADMIN_EMAIL'];
        $hash = (string) $_ENV['ADMIN_PASSWORD_HASH'];

        if (!hash_equals(strtolower($adminEmail), strtolower($email)) || !password_verify($password, $hash)) {
            return null;
        }

        $now = time();
        return JWT::encode([
            'iss' => 'plataforma-soni',
            'sub' => 'admin',
            'iat' => $now,
            'exp' => $now + 3600,
        ], (string) $_ENV['JWT_SECRET'], 'HS256');
    }

    public static function isValidBearer(string $header): bool
    {
        if (!self::configured() || !preg_match('/^Bearer\s+(.+)$/i', $header, $match)) {
            return false;
        }

        try {
            $claims = JWT::decode($match[1], new Key((string) $_ENV['JWT_SECRET'], 'HS256'));
            return ($claims->iss ?? '') === 'plataforma-soni'
                && ($claims->sub ?? '') === 'admin';
        } catch (Throwable) {
            return false;
        }
    }
}
