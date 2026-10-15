<?php

declare(strict_types=1);

namespace Soni;

use PDO;
use RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Operações exclusivamente CLI. Backups completos são privados, fora de api/public.
 * Não executa restaurações ou exclusões irreversíveis.
 */
final class SiteMaintenance
{
    private static function storage(): string
    {
        return dirname(__DIR__) . '/storage';
    }

    private static function backups(): string
    {
        return self::storage() . '/backups';
    }

    private static function credential(string $key, string $fallback = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) ? $value : $fallback;
    }

    private static function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar uma pasta de manutenção.');
        }
        if (is_link($dir) || !is_writable($dir)) {
            throw new RuntimeException('Pasta de manutenção insegura ou não gravável.');
        }
    }

    public static function verify(string $directory): array
    {
        $base = realpath(self::backups());
        $target = realpath($directory);
        if ($base === false || $target === false ||
            !str_starts_with(str_replace('\\', '/', $target), rtrim(str_replace('\\', '/', $base), '/') . '/') ||
            !is_dir($target) || is_link($target)) {
            throw new RuntimeException('Selecione uma cópia existente dentro de api/storage/backups.');
        }
        $raw = file_get_contents($target . '/manifest.json');
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'soni-backup-v1' ||
            !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('Manifesto de backup ausente ou inválido.');
        }
        $actual = [];
        foreach ($manifest['files'] as $relative => $sha) {
            if (!is_string($relative) || !preg_match('~^(database\.sql|media/[a-zA-Z0-9_./-]+)$~D', $relative) ||
                str_contains($relative, '..') || !is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D', $sha)) {
                throw new RuntimeException('Caminho ou hash inválido no backup.');
            }
            $path = $target . '/' . $relative;
            if (!is_file($path) || is_link($path) || hash_file('sha256', $path) !== $sha) {
                throw new RuntimeException('Arquivo ausente ou divergente no backup: ' . $relative);
            }
            $actual[] = $relative;
        }
        if (!in_array('database.sql', $actual, true) || filesize($target . '/database.sql') < 100) {
            throw new RuntimeException('SQL ausente ou vazio no backup.');
        }
        return $manifest;
    }

    private static function dumpBinary(): string
    {
        $configured = self::credential('SONI_DUMP_BIN');
        if ($configured !== '') {
            if (!is_file($configured) && str_contains($configured, DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('SONI_DUMP_BIN aponta para arquivo inexistente.');
            }
            return $configured;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $candidate = 'C:\\Program Files\\MariaDB 11.4\\bin\\mariadb-dump.exe';
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return 'mariadb-dump';
    }

    private static function dump(string $destination): void
    {
        $name = self::credential('DB_NAME');
        $port = self::credential('DB_PORT', '3306');
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name) || !ctype_digit($port)) {
            throw new RuntimeException('Configuração do banco inválida.');
        }
        $values = [
            'user' => self::credential('DB_USER'),
            'password' => self::credential('DB_PASSWORD'),
            'host' => self::credential('DB_HOST', 'localhost'),
            'port' => $port,
        ];
        if ($values['user'] === '' || $values['host'] === '') {
            throw new RuntimeException('Usuário ou host do banco não definido.');
        }

        // Arquivo temporário necessário para não expor a senha na lista de processos.
        $defaultsFile = tempnam(sys_get_temp_dir(), 'soni-db-');
        if ($defaultsFile === false) {
            throw new RuntimeException('Não foi possível preparar configuração temporária.');
        }
        try {
            @chmod($defaultsFile, 0600);
            $ini = "[client]\n";
            foreach ($values as $key => $value) {
                if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
                    throw new RuntimeException('Configuração do banco contém caractere inválido.');
                }
                $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
                $ini .= $key . '="' . $escaped . "\"\n";
            }
            if (file_put_contents($defaultsFile, $ini, LOCK_EX) === false) {
                throw new RuntimeException('Não foi possível preparar credenciais temporárias.');
            }
            @chmod($defaultsFile, 0600);
            $args = [
                self::dumpBinary(),
                '--defaults-extra-file=' . $defaultsFile,
                '--single-transaction',
                '--quick',
                '--hex-blob',
                '--skip-triggers',
                '--default-character-set=utf8mb4',
                '--result-file=' . $destination,
                $name,
            ];
            $pipes = [];
            $process = @proc_open($args, [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, dirname(__DIR__));
            if (!is_resource($process)) {
                throw new RuntimeException('mariadb-dump não encontrado. Configure SONI_DUMP_BIN.');
            }
            fclose($pipes[0]);
            // Capturar resposta do processo sem exibir credenciais nem conteúdo privado.
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            if ($exit !== 0 || !is_file($destination) || filesize($destination) < 100) {
                throw new RuntimeException('mariadb-dump falhou. Confira executável e permissões SELECT do usuário.');
            }
        } finally {
            @unlink($defaultsFile);
        }
    }

    public static function backup(PDO $db): string
    {
        $backupRoot = self::backups();
        self::ensureDirectory($backupRoot);

        // Conectar antes de invocar o dump para detectar banco incorreto.
        $db->query('SELECT 1')->fetchColumn();
        $name = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $pending = $backupRoot . '/.incomplete-' . $name;
        $final = $backupRoot . '/' . $name;
        self::ensureDirectory($pending);

        self::dump($pending . '/database.sql');
        $manifest = [
            'format' => 'soni-backup-v1',
            'created_utc' => gmdate(DATE_ATOM),
            'database' => self::credential('DB_NAME'),
            'files' => ['database.sql' => hash_file('sha256', $pending . '/database.sql')],
        ];

        $source = self::storage() . '/site_media';
        if (is_dir($source)) {
            if (is_link($source)) throw new RuntimeException('Pasta de mídia é um link simbólico inseguro.');
            $walk = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($walk as $file) {
                if ($file->isLink()) throw new RuntimeException('Link simbólico encontrado na pasta de mídia.');
                if (!$file->isFile()) continue;
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                if (!preg_match('~^[a-f0-9]{2}/[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|mp3|wav|ogg|mp4|webm|pdf)$~D', $relative)) {
                    throw new RuntimeException('Arquivo com caminho inesperado em site_media: ' . $relative);
                }
                $target = $pending . '/media/' . $relative;
                self::ensureDirectory(dirname($target));
                if (!copy($file->getPathname(), $target)) {
                    throw new RuntimeException('Falha ao copiar uma mídia para o backup.');
                }
                $manifest['files']['media/' . $relative] = hash_file('sha256', $target);
            }
        }

        if (file_put_contents(
            $pending . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            LOCK_EX
        ) === false) {
            throw new RuntimeException('Falha ao gravar manifesto de backup.');
        }
        self::verify($pending);
        if (!rename($pending, $final)) {
            throw new RuntimeException('Falha ao finalizar o diretório de backup.');
        }
        return $final;
    }

    /** @return array<int, array<string, mixed>> */
    public static function orphans(PDO $db, int $days = 30, int $limit = 25): array
    {
        if ($days < 30 || $days > 3650 || $limit < 1 || $limit > 100) {
            throw new RuntimeException('Use ao menos 30 dias de retenção e até 100 itens por execução.');
        }
        $query = "SELECT m.id, m.original_name, m.storage_path, m.created_at
                  FROM site_media m
                  WHERE m.created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)
                  AND NOT EXISTS (SELECT 1 FROM site_blocks b WHERE b.media_id = m.id)
                  AND NOT EXISTS (SELECT 1 FROM site_settings s WHERE s.logo_media_id = m.id)
                  AND NOT EXISTS (
                      SELECT 1 FROM quiz_questions qq WHERE qq.image_media_id = m.id OR qq.audio_media_id = m.id
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM quiz_options qo WHERE qo.image_media_id = m.id OR qo.audio_media_id = m.id
                  )
                  AND NOT EXISTS (SELECT 1 FROM documents d WHERE d.media_id = m.id)
                  AND NOT EXISTS (SELECT 1 FROM videos v WHERE v.media_id = m.id)
                  ORDER BY m.created_at ASC, m.id ASC LIMIT {$limit}";
        return $db->query($query)->fetchAll();
    }

    public static function quarantine(PDO $db, string $backup, int $days = 30, int $limit = 25): array
    {
        $manifest = self::verify($backup);
        $created = strtotime((string) ($manifest['created_utc'] ?? ''));
        if ($created === false || $created < time() - 12 * 3600 || $created > time() + 60) {
            throw new RuntimeException('Backup deve ter sido realizado nas últimas 12 horas.');
        }
        $results = [];
        $quarantineRoot = self::storage() . '/site_media_quarantine';
        self::ensureDirectory($quarantineRoot);

        foreach (self::orphans($db, $days, $limit) as $candidate) {
            $id = (int) $candidate['id'];
            $db->beginTransaction();
            $moved = false;
            $from = '';
            $to = '';
            try {
                $stmt = $db->prepare('SELECT storage_path, created_at FROM site_media WHERE id = ? FOR UPDATE');
                $stmt->execute([$id]);
                $record = $stmt->fetch();
                if (!$record) {
                    $db->rollBack();
                    continue;
                }
                $linked = $db->prepare(
                    'SELECT (EXISTS(SELECT 1 FROM site_blocks WHERE media_id = ?) OR
                             EXISTS(SELECT 1 FROM site_settings WHERE logo_media_id = ?) OR
                             EXISTS(SELECT 1 FROM quiz_questions WHERE image_media_id = ? OR audio_media_id = ?) OR
                             EXISTS(SELECT 1 FROM quiz_options WHERE image_media_id = ? OR audio_media_id = ?) OR
                             EXISTS(SELECT 1 FROM documents WHERE media_id = ?) OR
                             EXISTS(SELECT 1 FROM videos WHERE media_id = ?))'
                );
                $linked->execute([$id, $id, $id, $id, $id, $id, $id, $id]);
                if ((int) $linked->fetchColumn() !== 0 ||
                    strtotime($record['created_at']) >= time() - $days * 86400) {
                    $db->rollBack();
                    continue;
                }
                $relative = (string) $record['storage_path'];
                if (!preg_match('~^[a-f0-9]{2}/[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|mp3|wav|ogg|mp4|webm|pdf)$~D', $relative)) {
                    throw new RuntimeException('Caminho de mídia suspeito; operação interrompida.');
                }
                if (!isset($manifest['files']['media/' . $relative])) {
                    throw new RuntimeException('O backup informado não contém a mídia candidata. Faça novo backup.');
                }
                $from = self::storage() . '/site_media/' . $relative;
                if (!is_file($from) || is_link($from)) {
                    throw new RuntimeException('Arquivo de mídia ausente ou inseguro; operação interrompida.');
                }
                if (hash_file('sha256', $from) !== $manifest['files']['media/' . $relative]) {
                    throw new RuntimeException('Mídia alterada após o backup; faça nova cópia antes da quarentena.');
                }
                $folder = $quarantineRoot . '/' . gmdate('Ymd');
                self::ensureDirectory($folder);
                $to = $folder . '/' . $id . '-' . bin2hex(random_bytes(6)) . '.' . pathinfo($relative, PATHINFO_EXTENSION);
                if (!rename($from, $to)) {
                    throw new RuntimeException('Falha ao colocar mídia em quarentena.');
                }
                $moved = true;
                $delete = $db->prepare('DELETE FROM site_media WHERE id = ?');
                $delete->execute([$id]);
                if ($delete->rowCount() !== 1) {
                    throw new RuntimeException('Registro de mídia não foi atualizado.');
                }
                $db->commit();
                $results[] = ['id' => $id, 'quarantine_path' => $to];
                $entry = json_encode(['id' => $id, 'original' => $relative, 'quarantine' => $to, 'at' => gmdate(DATE_ATOM)]);
                file_put_contents($quarantineRoot . '/quarantine-log.jsonl', $entry . "\n", FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                if ($moved && is_file($to)) {
                    @rename($to, $from);
                }
                throw $e;
            }
        }
        return $results;
    }
}
