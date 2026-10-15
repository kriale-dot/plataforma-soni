<?php

declare(strict_types=1);

namespace Soni;

use PDO;

final class SiteRepository
{
    public static function readPublic(PDO $pdo): array
    {
        $settings = $pdo->query(
            'SELECT site_title, site_description, meta_title, meta_description, contact_email, contact_phone
             FROM site_settings WHERE id = 1 LIMIT 1'
        )->fetch();

        $blocks = $pdo->query(
            "SELECT b.id, b.type, b.title, b.subtitle, b.body_text, b.media_id,
                    CASE WHEN b.media_id IS NOT NULL
                         THEN CONCAT('/api/site/media/', b.media_id)
                         ELSE b.media_url END AS media_url,
                    b.alt_text, b.button_label, b.button_url, b.sort_order
             FROM site_blocks b
             WHERE b.publication_status = 'PUBLISHED' AND b.is_visible = 1
             ORDER BY b.sort_order ASC, b.id ASC"
        )->fetchAll();

        return [
            'settings' => $settings === false ? ['site_title' => 'SONI | Sonorização de Igrejas'] : $settings,
            'blocks' => $blocks,
        ];
    }
}
