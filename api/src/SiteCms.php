<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;

final class SiteCms
{
    private const TYPES = [
        'HERO', 'TITULO', 'TEXTO', 'IMAGEM', 'TEXTO_IMAGEM',
        'VIDEO', 'AUDIO', 'CTA', 'LINK', 'TESTES', 'DOCUMENTOS', 'VIDEOS',
    ];

    private const TEXT_LIMITS = [
        'title' => 255,
        'subtitle' => 300,
        'body_text' => 8000,
        'media_url' => 1000,
        'alt_text' => 350,
        'button_label' => 120,
        'button_url' => 1000,
    ];

    private static function text(mixed $value, int $max, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException("Campo inválido: {$label}.");
        }
        $value = trim($value);
        if (strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new InvalidArgumentException("Conteúdo inválido ou longo demais: {$label}.");
        }
        return $value === '' ? null : $value;
    }

    private static function url(?string $url, string $field): ?string
    {
        if ($url === null) {
            return null;
        }
        if (preg_match('/\s|\\\\/', $url)) {
            throw new InvalidArgumentException("Link inválido em {$field}.");
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' ||
            !isset($parts['host']) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Somente HTTPS ou caminho interno é permitido em {$field}.");
        }
        return $url;
    }

    public static function readAdmin(PDO $pdo): array
    {
        $settings = $pdo->query(
            'SELECT site_title, site_description, meta_title, meta_description,
                    contact_email, contact_phone FROM site_settings WHERE id = 1'
        )->fetch();
        $blocks = $pdo->query(
            'SELECT id, type, title, subtitle, body_text, media_url, media_id, alt_text,
                    button_label, button_url, sort_order, is_visible, publication_status
             FROM site_blocks ORDER BY sort_order, id'
        )->fetchAll();
        $media = $pdo->query(
            'SELECT id, kind, original_name, mime_type, size_bytes, created_at
             FROM site_media ORDER BY id DESC LIMIT 100'
        )->fetchAll();
        return ['settings' => $settings ?: [], 'blocks' => $blocks, 'media' => $media];
    }

    public static function saveSettings(PDO $pdo, array $input): array
    {
        $fields = [
            'site_title' => 160, 'site_description' => 3000,
            'meta_title' => 160, 'meta_description' => 300,
            'contact_email' => 200, 'contact_phone' => 70,
        ];
        $data = [];
        foreach ($fields as $name => $limit) {
            if (!array_key_exists($name, $input)) {
                throw new InvalidArgumentException("Campo obrigatório ausente: {$name}.");
            }
            $data[$name] = self::text($input[$name], $limit, $name);
        }
        if ($data['site_title'] === null) {
            throw new InvalidArgumentException('Informe o título do site.');
        }
        if ($data['contact_email'] !== null && filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('E-mail de contato inválido.');
        }
        $stmt = $pdo->prepare(
            'UPDATE site_settings SET site_title = :site_title, site_description = :site_description,
             meta_title = :meta_title, meta_description = :meta_description,
             contact_email = :contact_email, contact_phone = :contact_phone WHERE id = 1'
        );
        $stmt->execute($data);
        return self::readAdmin($pdo)['settings'];
    }

    private static function fields(PDO $pdo, array $input, ?array $current = null): array
    {
        $type = $input['type'] ?? $current['type'] ?? 'TEXTO';
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Tipo de bloco não permitido.');
        }
        $fields = ['type' => $type];
        foreach (self::TEXT_LIMITS as $name => $length) {
            $fields[$name] = self::text($input[$name] ?? $current[$name] ?? null, $length, $name);
        }
        $fields['media_url'] = self::url($fields['media_url'], 'media_url');
        $fields['button_url'] = self::url($fields['button_url'], 'button_url');

        $mediaId = $input['media_id'] ?? $current['media_id'] ?? null;
        if ($mediaId === '' || $mediaId === 0 || $mediaId === '0') {
            $mediaId = null;
        }
        if ($mediaId !== null) {
            if ((!is_int($mediaId) && !is_string($mediaId)) ||
                !preg_match('/^[1-9][0-9]*$/D', (string) $mediaId)) {
                throw new InvalidArgumentException('Identificador de mídia inválido.');
            }
            $allowedKind = match ($type) {
                'HERO', 'IMAGEM', 'TEXTO_IMAGEM' => 'IMAGE',
                'AUDIO' => 'AUDIO',
                'VIDEO' => 'VIDEO',
                default => null,
            };
            $stmt = $pdo->prepare('SELECT kind FROM site_media WHERE id = ?');
            $stmt->execute([(int) $mediaId]);
            if ($allowedKind === null || $stmt->fetchColumn() !== $allowedKind) {
                throw new InvalidArgumentException('A mídia selecionada não é compatível com o tipo de bloco.');
            }
        }
        $fields['media_id'] = $mediaId === null ? null : (int) $mediaId;

        $visibility = $input['is_visible'] ?? $current['is_visible'] ?? 1;
        if (!in_array($visibility, [true, false, 1, 0, '1', '0'], true)) {
            throw new InvalidArgumentException('Visibilidade inválida.');
        }
        $fields['is_visible'] = (int) (bool) $visibility;
        $status = $input['publication_status'] ?? $current['publication_status'] ?? 'DRAFT';
        if (!in_array($status, ['DRAFT', 'PUBLISHED'], true)) {
            throw new InvalidArgumentException('Estado de publicação inválido.');
        }
        $fields['publication_status'] = $status;
        return $fields;
    }

    public static function create(PDO $pdo, array $input): array
    {
        $data = self::fields($pdo, $input);
        $order = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM site_blocks')->fetchColumn();
        $data['sort_order'] = $order;
        $names = array_keys($data);
        $stmt = $pdo->prepare('INSERT INTO site_blocks (' . implode(', ', $names) .
            ') VALUES (:' . implode(', :', $names) . ')');
        $stmt->execute($data);
        return self::getBlock($pdo, (int) $pdo->lastInsertId());
    }

    public static function getBlock(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, type, title, subtitle, body_text, media_url, media_id, alt_text,
                    button_label, button_url, sort_order, is_visible, publication_status
             FROM site_blocks WHERE id = ?'
        );
        $stmt->execute([$id]);
        $block = $stmt->fetch();
        if ($block === false) {
            throw new InvalidArgumentException('Bloco não encontrado.');
        }
        return $block;
    }

    public static function update(PDO $pdo, int $id, array $input): array
    {
        $current = self::getBlock($pdo, $id);
        $data = self::fields($pdo, $input, $current);
        $changes = [];
        foreach (array_keys($data) as $name) {
            $changes[] = "{$name} = :{$name}";
        }
        $data['id'] = $id;
        $pdo->prepare('UPDATE site_blocks SET ' . implode(', ', $changes) . ' WHERE id = :id')
            ->execute($data);
        return self::getBlock($pdo, $id);
    }

    public static function delete(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('DELETE FROM site_blocks WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException('Bloco não encontrado.');
        }
        return ['deleted' => true];
    }

    public static function move(PDO $pdo, int $id, string $direction): array
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Direção inválida.');
        }
        $pdo->beginTransaction();
        try {
            $ids = array_map('intval', $pdo->query(
                'SELECT id FROM site_blocks ORDER BY sort_order, id FOR UPDATE'
            )->fetchAll(PDO::FETCH_COLUMN));
            $index = array_search($id, $ids, true);
            if ($index === false) {
                throw new InvalidArgumentException('Bloco não encontrado.');
            }
            $target = $index + ($direction === 'up' ? -1 : 1);
            if ($target >= 0 && $target < count($ids)) {
                [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
                $stmt = $pdo->prepare('UPDATE site_blocks SET sort_order = ? WHERE id = ?');
                foreach ($ids as $position => $blockId) {
                    $stmt->execute([($position + 1) * 10, $blockId]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        return self::readAdmin($pdo)['blocks'];
    }
}
