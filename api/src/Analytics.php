<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;

final class Analytics
{
    // Apenas caminhos públicos conhecidos. Não gravar query strings, IP, cookies ou referer.
    private static function path(mixed $path): string
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Caminho inválido.');
        }
        $path = rtrim($path, '/') ?: '/';
        if (in_array($path, ['/', '/testes', '/documentos', '/videos'], true)) {
            return $path;
        }
        if (preg_match('~^/(testes|documentos|videos)/[1-9][0-9]{0,14}$~D', $path)) {
            return $path;
        }
        throw new InvalidArgumentException('Página pública inválida.');
    }

    public static function record(PDO $pdo, array $input, string $userAgent = '', string $dnt = ''): array
    {
        // Não contar crawlers nem solicitações que indicaram preferência por não rastrear.
        if ($dnt === '1' || preg_match('/bot|crawler|spider|slurp|preview|facebookexternalhit/i', $userAgent)) {
            return ['tracked' => false, 'visit_count' => null];
        }
        $token = $input['session_id'] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9-]{36}$/iD', $token)
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $token)) {
            throw new InvalidArgumentException('Sessão de visita inválida.');
        }
        $path = self::path($input['page'] ?? null);
        $key = hash('sha256', strtolower($token));
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO analytics_sessions (session_key, started_at, last_seen_at, pageviews)
                 VALUES (?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 0)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $stmt->execute([$key]);
            $sessionId = (int) $pdo->lastInsertId();

            // Evita duplicidade em montagens React StrictMode e repetição imediata do mesmo evento.
            $stmt = $pdo->prepare(
                'SELECT id FROM analytics_pageviews
                 WHERE session_id = ? AND page_path = ?
                   AND viewed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 SECOND)
                 LIMIT 1'
            );
            $stmt->execute([$sessionId, $path]);
            $isNewPageview = $stmt->fetchColumn() === false;
            if ($isNewPageview) {
                $stmt = $pdo->prepare(
                    'INSERT INTO analytics_pageviews (session_id, page_path, viewed_at)
                     VALUES (?, ?, UTC_TIMESTAMP())'
                );
                $stmt->execute([$sessionId, $path]);
                $pdo->prepare(
                    'UPDATE analytics_sessions SET last_seen_at = UTC_TIMESTAMP(), pageviews = pageviews + 1 WHERE id = ?'
                )->execute([$sessionId]);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        $visible = (int) $pdo->query('SELECT show_visit_counter FROM site_settings WHERE id = 1')->fetchColumn() === 1;
        return [
            'tracked' => true,
            'visit_count' => $visible ? self::totalVisits($pdo) : null,
        ];
    }

    public static function totalVisits(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM analytics_sessions')->fetchColumn();
    }

    public static function report(PDO $pdo, int $days = 30): array
    {
        if (!in_array($days, [7, 30, 90], true)) {
            throw new InvalidArgumentException('Período deve ser de 7, 30 ou 90 dias.');
        }
        $scalar = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
        $totals = [
            'visits_all' => self::totalVisits($pdo),
            'visits_today' => $scalar("SELECT COUNT(*) FROM analytics_sessions
                WHERE DATE(DATE_SUB(started_at, INTERVAL 3 HOUR)) = DATE(DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR))"),
            'visits_7d' => $scalar('SELECT COUNT(*) FROM analytics_sessions WHERE started_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)'),
            'visits_30d' => $scalar('SELECT COUNT(*) FROM analytics_sessions WHERE started_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'),
            'pageviews_all' => $scalar('SELECT COUNT(*) FROM analytics_pageviews'),
            'pageviews_30d' => $scalar('SELECT COUNT(*) FROM analytics_pageviews WHERE viewed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'),
            'attempts_all' => $scalar('SELECT COUNT(*) FROM quiz_attempts'),
            'attempts_30d' => $scalar('SELECT COUNT(*) FROM quiz_attempts WHERE submitted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'),
        ];

        // Registros por dia, em data civil de Brasília (UTC-03:00).
        $visits = $pdo->prepare(
            'SELECT DATE(DATE_SUB(started_at, INTERVAL 3 HOUR)) AS day, COUNT(*) AS amount
             FROM analytics_sessions
             WHERE started_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
             GROUP BY day ORDER BY day'
        );
        $visits->bindValue(1, $days + 1, PDO::PARAM_INT);
        $visits->execute();
        $dailyVisits = array_column($visits->fetchAll(), 'amount', 'day');

        $views = $pdo->prepare(
            'SELECT DATE(DATE_SUB(viewed_at, INTERVAL 3 HOUR)) AS day, COUNT(*) AS amount
             FROM analytics_pageviews
             WHERE viewed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
             GROUP BY day ORDER BY day'
        );
        $views->bindValue(1, $days + 1, PDO::PARAM_INT);
        $views->execute();
        $dailyViews = array_column($views->fetchAll(), 'amount', 'day');

        $tz = new \DateTimeZone('America/Sao_Paulo');
        $today = new \DateTimeImmutable('today', $tz);
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->modify("-{$i} days")->format('Y-m-d');
            $series[] = [
                'date' => $day,
                'visits' => (int) ($dailyVisits[$day] ?? 0),
                'pageviews' => (int) ($dailyViews[$day] ?? 0),
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT page_path AS path, COUNT(*) AS views,
                    COUNT(DISTINCT session_id) AS sessions
             FROM analytics_pageviews
             WHERE viewed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
             GROUP BY page_path ORDER BY views DESC, page_path ASC LIMIT 12'
        );
        $stmt->bindValue(1, $days, PDO::PARAM_INT);
        $stmt->execute();
        $pages = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT q.id, q.title, COUNT(a.id) AS attempts,
                    ROUND(AVG(a.score_percent), 1) AS average_score
             FROM quizzes q LEFT JOIN quiz_attempts a
               ON a.quiz_id = q.id AND a.submitted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
             GROUP BY q.id, q.title ORDER BY attempts DESC, q.title ASC LIMIT 12'
        );
        $stmt->bindValue(1, $days, PDO::PARAM_INT);
        $stmt->execute();
        $quizzes = $stmt->fetchAll();

        $catalog = [
            'quizzes_published' => $scalar("SELECT COUNT(*) FROM quizzes WHERE publication_status = 'PUBLISHED'"),
            'documents_published' => $scalar("SELECT COUNT(*) FROM documents WHERE publication_status = 'PUBLISHED' AND is_visible = 1"),
            'videos_published' => $scalar("SELECT COUNT(*) FROM videos WHERE publication_status = 'PUBLISHED' AND is_visible = 1"),
            'media_bytes' => $scalar('SELECT COALESCE(SUM(size_bytes), 0) FROM site_media'),
        ];

        return [
            'days' => $days,
            'timezone' => 'America/Sao_Paulo',
            'totals' => $totals,
            'series' => $series,
            'pages' => $pages,
            'quizzes' => $quizzes,
            'catalog' => $catalog,
            'notes' => 'Visitas = sessões anônimas iniciadas. Visualizações = páginas abertas. Bots conhecidos e DNT são ignorados. Tentativas de testes excluídas não integram o histórico.',
        ];
    }
}
