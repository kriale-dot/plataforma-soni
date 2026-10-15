<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;
use PDOException;

final class VideoService
{
    private static function text(mixed $value, int $max, string $field, bool $required = false): ?string
    {
        if ($value === null || $value === '') {
            if ($required) throw new InvalidArgumentException("Informe {$field}.");
            return null;
        }
        if (!is_string($value)) throw new InvalidArgumentException("Campo inválido: {$field}.");
        $value = trim($value);
        if ($value === '') {
            if ($required) throw new InvalidArgumentException("Informe {$field}.");
            return null;
        }
        if (strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new InvalidArgumentException("Campo inválido ou longo demais: {$field}.");
        }
        return $value;
    }

    private static function id(mixed $value, string $field): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)) {
            throw new InvalidArgumentException("Identificador inválido: {$field}.");
        }
        return (int) $value;
    }

    public static function youtubeId(mixed $value): string
    {
        $value = self::text($value, 350, 'a URL do YouTube', true);
        $parts = parse_url($value);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) ||
            isset($parts['fragment'])) {
            throw new InvalidArgumentException('Utilize uma URL HTTPS válida do YouTube.');
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $videoId = null;
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true) &&
            preg_match('~^/([A-Za-z0-9_-]{11})/?$~D', $path, $m)) {
            $videoId = $m[1];
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $videoId = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~D', $path, $m)) {
                $videoId = $m[1];
            }
        }
        if (!is_string($videoId) || !preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId)) {
            throw new InvalidArgumentException('Informe um link válido de um vídeo do YouTube.');
        }
        return $videoId;
    }

    private static function ensureCategory(PDO $db, int $category): void
    {
        $stmt = $db->prepare('SELECT id FROM video_categories WHERE id = ?');
        $stmt->execute([$category]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Categoria de vídeo não encontrada.');
    }

    public static function saveCategory(PDO $db, ?int $id, array $input): array
    {
        $title = self::text($input['title'] ?? null, 120, 'o título da categoria', true);
        $desc = self::text($input['description'] ?? null, 500, 'a descrição da categoria');
        if ($id === null) {
            $stmt = $db->prepare('INSERT INTO video_categories (title, description) VALUES (?, ?)');
            $stmt->execute([$title, $desc]);
            $id = (int) $db->lastInsertId();
        } else {
            self::ensureCategory($db, $id);
            $stmt = $db->prepare('UPDATE video_categories SET title=?, description=? WHERE id=?');
            $stmt->execute([$title, $desc, $id]);
        }
        return ['id' => $id, 'title' => $title, 'description' => $desc];
    }

    public static function categories(PDO $db): array
    {
        return $db->query('SELECT id, title, description, sort_order FROM video_categories ORDER BY sort_order, id')->fetchAll();
    }

    private static function listRows(PDO $db, bool $public, ?int $videoId = null, string $search = '', ?int $category = null): array
    {
        $sql = "SELECT v.id, v.category_id, c.title AS category_title, v.title, v.description,
                       v.source_type, v.youtube_id, v.media_id, v.publication_status, v.is_visible,
                       m.original_name, m.mime_type, m.size_bytes, v.created_at, v.updated_at
                  FROM videos v JOIN video_categories c ON c.id = v.category_id
                  LEFT JOIN site_media m ON m.id = v.media_id
                 WHERE 1=1";
        $params = [];
        if ($public) $sql .= " AND v.publication_status = 'PUBLISHED' AND v.is_visible = 1";
        if ($videoId !== null) {
            $sql .= ' AND v.id = ?';
            $params[] = $videoId;
        }
        if ($category !== null) {
            $sql .= ' AND v.category_id = ?';
            $params[] = $category;
        }
        if ($search !== '') {
            $sql .= ' AND (v.title LIKE ? OR v.description LIKE ? OR c.title LIKE ?)';
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
        }
        $sql .= ' ORDER BY v.updated_at DESC, v.id DESC';
        if ($videoId === null) $sql .= ' LIMIT 100';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function listPublic(PDO $db, array $query): array
    {
        $search = self::text($query['q'] ?? null, 120, 'a busca') ?? '';
        $category = isset($query['category']) && $query['category'] !== ''
            ? self::id($query['category'], 'categoria') : null;
        return [
            'categories' => self::categories($db),
            'videos' => self::listRows($db, true, null, $search, $category),
        ];
    }

    public static function getPublic(PDO $db, int $id): array
    {
        $result = self::listRows($db, true, $id);
        if (!$result) throw new InvalidArgumentException('Vídeo não encontrado ou não publicado.');
        return $result[0];
    }

    public static function listAdmin(PDO $db): array
    {
        return [
            'categories' => self::categories($db),
            'videos' => self::listRows($db, false),
            'media' => $db->query("SELECT id, original_name, kind, mime_type, size_bytes FROM site_media
                                  WHERE kind='VIDEO' ORDER BY id DESC LIMIT 250")->fetchAll(),
        ];
    }

    private static function getAdmin(PDO $db, int $id): array
    {
        $result = self::listRows($db, false, $id);
        if (!$result) throw new InvalidArgumentException('Vídeo não encontrado.');
        return $result[0];
    }

    private static function validatedData(PDO $db, array $input, ?array $current = null): array
    {
        $title = self::text($input['title'] ?? $current['title'] ?? null, 180, 'o título', true);
        $desc = self::text($input['description'] ?? $current['description'] ?? null, 4000, 'a descrição');
        $categoryId = self::id($input['category_id'] ?? $current['category_id'] ?? null, 'categoria');
        self::ensureCategory($db, $categoryId);
        $type = $input['source_type'] ?? $current['source_type'] ?? 'YOUTUBE';
        if (!in_array($type, ['YOUTUBE', 'UPLOAD'], true)) {
            throw new InvalidArgumentException('Origem do vídeo inválida.');
        }
        $youtube = null;
        $mediaId = null;
        if ($type === 'YOUTUBE') {
            // Ao editar sem enviar outra URL, preserve o ID previamente validado.
            $url = $input['youtube_url'] ?? null;
            if ($url !== null && $url !== '') {
                $youtube = self::youtubeId($url);
            } elseif (($current['source_type'] ?? '') === 'YOUTUBE' &&
                preg_match('/^[A-Za-z0-9_-]{11}$/D', (string) ($current['youtube_id'] ?? ''))) {
                $youtube = $current['youtube_id'];
            } else {
                throw new InvalidArgumentException('Informe a URL HTTPS do YouTube.');
            }
        } else {
            $mediaId = self::id($input['media_id'] ?? $current['media_id'] ?? null, 'arquivo MP4/WebM');
            $stmt = $db->prepare('SELECT kind, mime_type FROM site_media WHERE id = ?');
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch();
            if (!$media || $media['kind'] !== 'VIDEO' ||
                !in_array($media['mime_type'], ['video/mp4', 'video/webm'], true)) {
                throw new InvalidArgumentException('O arquivo deve ser um vídeo MP4 ou WebM enviado à biblioteca.');
            }
        }
        $status = $input['publication_status'] ?? $current['publication_status'] ?? 'DRAFT';
        if (!in_array($status, ['DRAFT', 'PUBLISHED'], true)) {
            throw new InvalidArgumentException('Publicação inválida.');
        }
        $visible = $input['is_visible'] ?? $current['is_visible'] ?? 1;
        if (!in_array($visible, [true, false, 0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('Visibilidade inválida.');
        }
        return [$categoryId, $title, $desc, $type, $youtube, $mediaId, $status, (int) (bool) $visible];
    }

    public static function create(PDO $db, array $input): array
    {
        [$category, $title, $desc, $type, $youtube, $media, $status, $visible] = self::validatedData($db, $input);
        try {
            $stmt = $db->prepare(
                'INSERT INTO videos (category_id, title, description, source_type, youtube_id, media_id, publication_status, is_visible)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$category, $title, $desc, $type, $youtube, $media, $status, $visible]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new InvalidArgumentException('Este arquivo já pertence a outro vídeo; envie uma cópia.');
            }
            throw $error;
        }
        return self::getAdmin($db, (int) $db->lastInsertId());
    }

    public static function update(PDO $db, int $id, array $input): array
    {
        $current = self::getAdmin($db, $id);
        [$category, $title, $desc, $type, $youtube, $media, $status, $visible] = self::validatedData($db, $input, $current);
        try {
            $stmt = $db->prepare(
                'UPDATE videos SET category_id=?, title=?, description=?, source_type=?,
                        youtube_id=?, media_id=?, publication_status=?, is_visible=?
                 WHERE id=?'
            );
            $stmt->execute([$category, $title, $desc, $type, $youtube, $media, $status, $visible, $id]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new InvalidArgumentException('Este arquivo já pertence a outro vídeo; envie uma cópia.');
            }
            throw $error;
        }
        return self::getAdmin($db, $id);
    }

    public static function delete(PDO $db, int $id): array
    {
        $stmt = $db->prepare('DELETE FROM videos WHERE id=?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('Vídeo não encontrado.');
        // O arquivo não é apagado: mídias sem cadastro podem ser auditadas depois.
        return ['deleted' => true];
    }
}
