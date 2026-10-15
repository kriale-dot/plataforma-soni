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
            "SELECT id, type, title, subtitle, body_text, media_url,
                    alt_text, button_label, button_url, sort_order
             FROM site_blocks
             WHERE publication_status = 'PUBLISHED' AND is_visible = 1
             ORDER BY sort_order ASC, id ASC"
        )->fetchAll();

        return [
            'settings' => $settings === false ? ['site_title' => 'SONI | Sonorização de Igrejas'] : $settings,
            'blocks' => $blocks,
        ];
    }
}
