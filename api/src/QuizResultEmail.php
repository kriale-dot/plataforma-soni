<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class QuizResultEmail
{
    public static function issueToken(int $attemptId): string
    {
        $expires = time() + 7200;
        $payload = $attemptId . '.' . $expires;
        return $payload . '.' . hash_hmac('sha256', $payload, self::secret());
    }

    private static function secret(): string
    {
        $secret = (string) ($_ENV['JWT_SECRET'] ?? '');
        if (strlen($secret) < 32) throw new RuntimeException('Segredo de assinatura não configurado.');
        return $secret;
    }

    private static function authenticate(int $attemptId, string $token): void
    {
        if (!preg_match('/^([1-9][0-9]*)\.([0-9]{10})\.([a-f0-9]{64})$/D', $token, $m)
            || (int) $m[1] !== $attemptId || (int) $m[2] < time()
            || (int) $m[2] > time() + 7200
            || !hash_equals(hash_hmac('sha256', $m[1] . '.' . $m[2], self::secret()), $m[3])) {
            throw new InvalidArgumentException('Autorização inválida ou expirada. Finalize um novo teste para solicitar o envio.');
        }
    }

    public static function send(PDO $db, int $attemptId, array $body): array
    {
        $email = $body['email'] ?? null;
        $token = $body['result_token'] ?? null;
        if (!is_string($email) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || preg_match('/[\r\n]/', $email)) {
            throw new InvalidArgumentException('Informe um endereço de e-mail válido.');
        }
        if (!is_string($token)) throw new InvalidArgumentException('Autorização ausente.');
        self::authenticate($attemptId, $token);

        $from = (string) ($_ENV['SONI_MAIL_FROM'] ?? '');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
            throw new RuntimeException('Remetente de e-mail não configurado.');
        }

        // Reserva atômica para evitar múltiplos envios da mesma tentativa.
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'SELECT a.total_questions, a.correct_answers, a.score_percent, a.submitted_at,
                        q.title, f.label, f.feedback_text, f.study_recommendation
                   FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id
                   JOIN quiz_attempt_feedback f ON f.attempt_id = a.id WHERE a.id = ?'
            );
            $stmt->execute([$attemptId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) throw new InvalidArgumentException('Resultado não encontrado.');

            $insert = $db->prepare('INSERT IGNORE INTO quiz_result_email_sends (attempt_id, email) VALUES (?, ?)');
            $insert->execute([$attemptId, $email]);
            if ($insert->rowCount() !== 1) {
                throw new InvalidArgumentException('O resultado desta tentativa já foi solicitado por e-mail.');
            }
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }

        $pdf = self::pdf($result);
        $boundary = 'SONI_' . bin2hex(random_bytes(16));
        $subject = 'Resultado do teste SONI';
        $plain = "Olá!\r\n\r\nSegue em anexo o PDF com seu resultado e a devolutiva do teste SONI.\r\n\r\nEquipe SONI";
        $headers = [
            'From: Plataforma SONI <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];
        $message = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($plain), 76, "\r\n")
            . '--' . $boundary . "\r\n"
            . "Content-Type: application/pdf; name=\"resultado-soni.pdf\"\r\n"
            . "Content-Disposition: attachment; filename=\"resultado-soni.pdf\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($pdf), 76, "\r\n")
            . '--' . $boundary . "--\r\n";

        if (!mail($email, $subject, $message, implode("\r\n", $headers), '-f' . $from)) {
            $db->prepare('DELETE FROM quiz_result_email_sends WHERE attempt_id = ?')->execute([$attemptId]);
            throw new RuntimeException('O servidor não conseguiu encaminhar o e-mail.');
        }
        return ['sent' => true, 'message' => 'Solicitação de envio aceita pelo servidor de e-mail.'];
    }

    // PDF autocontido: não requer dependências adicionais no servidor.
    private static function pdf(array $result): string
    {
        $lines = [
            'PLATAFORMA SONI - SONORIZACAO DE IGREJAS',
            'RESULTADO E DEVOLUTIVA',
            '',
            'Teste: ' . $result['title'],
            'Realizado em: ' . $result['submitted_at'],
            'Acertos: ' . $result['correct_answers'] . ' de ' . $result['total_questions'],
            'Percentual: ' . $result['score_percent'] . '%',
            '',
            'CLASSIFICACAO: ' . $result['label'],
            '',
            'DEVOLUTIVA',
            $result['feedback_text'],
            '',
            'RECOMENDACOES DE ESTUDO',
            $result['study_recommendation'] ?: 'Sem recomendacoes adicionais.',
            '',
            'www.somdaigreja.com.br',
        ];
        $wrapped = [];
        foreach ($lines as $line) {
            $line = preg_replace('/\s+/u', ' ', trim((string) $line));
            $line = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $line);
            foreach (explode("\n", wordwrap($line, 85, "\n", true)) as $part) $wrapped[] = $part;
        }
        $pages = array_chunk($wrapped, 45);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $kids = [];
        $next = 4;
        foreach ($pages as $page) {
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = $pageId . ' 0 R';
            $commands = ["BT", "/F1 12 Tf", "50 790 Td", "16 TL"];
            foreach ($page as $line) {
                $line = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $commands[] = '(' . $line . ') Tj T*';
            }
            $commands[] = 'ET';
            $stream = implode("\n", $commands) . "\n";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . (3) . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }
}
