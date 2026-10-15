<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;
use Throwable;

final class DocumentService
{
    private static function text(mixed $value, int $max, string $field, bool $required = false): ?string
    {
        if ($value === null || $value === '') {
            if ($required) throw new InvalidArgumentException('Informe ' . $field . '.');
            return null;
        }
        if (!is_string($value)) throw new InvalidArgumentException('Valor inválido: ' . $field . '.');
        $value = trim($value);
        if ($value === '') {
            if ($required) throw new InvalidArgumentException('Informe ' . $field . '.');
            return null;
        }
        if (strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new InvalidArgumentException('Conteúdo inválido ou longo demais: ' . $field . '.');
        }
        return $value;
    }

    private static function id(mixed $value, string $field): int
    {
        if (!is_int($value) && !is_string($value) ||
            !preg_match('/^[1-9][0-9]*$/D', (string) $value)) {
            throw new InvalidArgumentException('Identificador inválido: ' . $field . '.');
        }
        return (int) $value;
    }

    public static function categories(PDO $db): array
    {
        return $db->query(
            'SELECT id, title, description, sort_order FROM document_categories ORDER BY sort_order, id'
        )->fetchAll();
    }

    private static function categoryExists(PDO $db, int $id): void
    {
        $stmt = $db->prepare('SELECT id FROM document_categories WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Categoria não encontrada.');
    }

    public static function saveCategory(PDO $db, ?int $id, array $data): array
    {
        $title = self::text($data['title'] ?? null, 120, 'o nome da categoria', true);
        $description = self::text($data['description'] ?? null, 500, 'a descrição');
        if ($id === null) {
            $stmt = $db->prepare('INSERT INTO document_categories (title, description) VALUES (?, ?)');
            $stmt->execute([$title, $description]);
            $id = (int) $db->lastInsertId();
        } else {
            self::categoryExists($db, $id);
            $stmt = $db->prepare('UPDATE document_categories SET title = ?, description = ? WHERE id = ?');
            $stmt->execute([$title, $description, $id]);
        }
        return ['id' => $id, 'title' => $title, 'description' => $description];
    }

    private static function selectDocuments(PDO $db, bool $public, ?int $id = null, string $search = '', ?int $category = null): array
    {
        $sql = 'SELECT d.id, d.category_id, c.title AS category_title, d.media_id,
                       d.title, d.description, d.publication_status, d.is_visible,
                       m.original_name, m.mime_type, m.size_bytes, d.created_at, d.updated_at
                  FROM documents d
                  JOIN document_categories c ON c.id = d.category_id
                  JOIN site_media m ON m.id = d.media_id
                 WHERE 1=1';
        $params = [];
        if ($public) $sql .= " AND d.publication_status = 'PUBLISHED' AND d.is_visible = 1";
        if ($id !== null) {
            $sql .= ' AND d.id = ?';
            $params[] = $id;
        }
        if ($category !== null) {
            $sql .= ' AND d.category_id = ?';
            $params[] = $category;
        }
        if ($search !== '') {
            $sql .= ' AND (d.title LIKE ? OR d.description LIKE ? OR c.title LIKE ?)';
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
        }
        $sql .= ' ORDER BY d.updated_at DESC, d.id DESC';
        if ($id === null) $sql .= ' LIMIT 100';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function adminList(PDO $db): array
    {
        $media = $db->query(
            "SELECT id, kind, original_name, mime_type, size_bytes FROM site_media
              WHERE kind = 'DOCUMENT' OR (kind = 'IMAGE' AND mime_type = 'image/png')
              ORDER BY id DESC LIMIT 250"
        )->fetchAll();
        return [
            'categories' => self::categories($db),
            'documents' => self::selectDocuments($db, false),
            'media' => $media,
        ];
    }

    public static function publicList(PDO $db, array $params): array
    {
        $search = self::text($params['q'] ?? '', 120, 'a pesquisa') ?? '';
        $category = isset($params['category']) && $params['category'] !== ''
            ? self::id($params['category'], 'categoria') : null;
        return [
            'categories' => self::categories($db),
            'documents' => self::selectDocuments($db, true, null, $search, $category),
        ];
    }

    public static function publicDetail(PDO $db, int $id): array
    {
        $results = self::selectDocuments($db, true, $id);
        if (!$results) throw new InvalidArgumentException('Documento não encontrado ou não publicado.');
        return $results[0];
    }

    private static function mediaExists(PDO $db, int $id): void
    {
        $stmt = $db->prepare('SELECT kind, mime_type FROM site_media WHERE id = ?');
        $stmt->execute([$id]);
        $media = $stmt->fetch();
        if (!$media || !(
            ($media['kind'] === 'DOCUMENT' && $media['mime_type'] === 'application/pdf') ||
            ($media['kind'] === 'IMAGE' && $media['mime_type'] === 'image/png')
        )) {
            throw new InvalidArgumentException('Selecione um PDF ou PNG válido da biblioteca de mídia.');
        }
    }

    private static function data(PDO $db, array $input, ?array $current = null): array
    {
        $title = self::text($input['title'] ?? $current['title'] ?? null, 180, 'o título', true);
        $desc = self::text($input['description'] ?? $current['description'] ?? null, 4000, 'a descrição');
        $category = self::id($input['category_id'] ?? $current['category_id'] ?? null, 'categoria');
        self::categoryExists($db, $category);
        $mediaId = self::id($input['media_id'] ?? $current['media_id'] ?? null, 'arquivo');
        self::mediaExists($db, $mediaId);
        $status = $input['publication_status'] ?? $current['publication_status'] ?? 'DRAFT';
        if (!in_array($status, ['DRAFT', 'PUBLISHED'], true)) {
            throw new InvalidArgumentException('Estado de publicação inválido.');
        }
        $visible = $input['is_visible'] ?? $current['is_visible'] ?? 1;
        if (!in_array($visible, [true, false, 0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('Visibilidade inválida.');
        }
        return [$category, $mediaId, $title, $desc, $status, (int) (bool) $visible];
    }

    private static function getAdmin(PDO $db, int $id): array
    {
        $rows = self::selectDocuments($db, false, $id);
        if (!$rows) throw new InvalidArgumentException('Documento não encontrado.');
        return $rows[0];
    }

    public static function create(PDO $db, array $input): array
    {
        [$category, $media, $title, $desc, $status, $visible] = self::data($db, $input);
        try {
            $stmt = $db->prepare(
                'INSERT INTO documents (category_id, media_id, title, description, publication_status, is_visible)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$category, $media, $title, $desc, $status, $visible]);
        } catch (\PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new InvalidArgumentException('Arquivo já associado a outro documento; envie uma cópia nova.');
            }
            throw $error;
        }
        return self::getAdmin($db, (int) $db->lastInsertId());
    }

    public static function update(PDO $db, int $id, array $input): array
    {
        $current = self::getAdmin($db, $id);
        [$category, $media, $title, $desc, $status, $visible] = self::data($db, $input, $current);
        try {
            $stmt = $db->prepare(
                'UPDATE documents SET category_id=?, media_id=?, title=?, description=?, publication_status=?, is_visible=? WHERE id=?'
            );
            $stmt->execute([$category, $media, $title, $desc, $status, $visible, $id]);
        } catch (\PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new InvalidArgumentException('Arquivo já associado a outro documento; envie uma cópia nova.');
            }
            throw $error;
        }
        return self::getAdmin($db, $id);
    }

    public static function delete(PDO $db, int $id): array
    {
        $stmt = $db->prepare('DELETE FROM documents WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('Documento não encontrado.');
        // O registro site_media e o arquivo permanecem intactos para auditoria e recuperação.
        return ['deleted' => true];
    }
}
