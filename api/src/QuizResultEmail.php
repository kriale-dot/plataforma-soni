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
        $apiKey = (string) ($_ENV['BREVO_API_KEY'] ?? '');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
            throw new RuntimeException('Remetente Brevo não configurado.');
        }
        if ($apiKey === '' || preg_match('/[\r\n]/', $apiKey)) {
            throw new RuntimeException('Chave da API Brevo não configurada.');
        }

        // Reserva atômica: permite somente uma solicitação por tentativa.
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

            // Gabarito da tentativa, obtido das respostas registradas.
            // Questões ou alternativas com mídia são identificadas no relatório.
            $detail = $db->prepare(
                'SELECT q.prompt_text, q.image_media_id AS question_image, q.audio_media_id AS question_audio,
                        q.explanation, aa.was_correct,
                        selected.option_text AS selected_text, selected.image_media_id AS selected_image,
                        selected.audio_media_id AS selected_audio,
                        correct.option_text AS correct_text, correct.image_media_id AS correct_image,
                        correct.audio_media_id AS correct_audio
                   FROM quiz_attempt_answers aa
                   JOIN quiz_questions q ON q.id = aa.question_id
                   JOIN quiz_options selected ON selected.id = aa.option_id
                   JOIN quiz_options correct ON correct.question_id = q.id AND correct.is_correct = 1
                  WHERE aa.attempt_id = ?
                  ORDER BY q.sort_order, q.id'
            );
            $detail->execute([$attemptId]);
            $result['answers'] = $detail->fetchAll(PDO::FETCH_ASSOC);

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

        $payload = [
            'sender' => ['name' => 'Plataforma SONI', 'email' => $from],
            'to' => [['email' => $email]],
            'subject' => 'Resultado e devolutiva do teste SONI',
            'htmlContent' => '<p>Olá!</p><p>Seu resultado e sua devolutiva da Plataforma SONI estão anexados em PDF.</p><p>Equipe SONI</p>',
            'textContent' => "Olá!\n\nSeu resultado e sua devolutiva da Plataforma SONI estão anexados em PDF.\n\nEquipe SONI",
            'attachment' => [['name' => 'resultado-soni.pdf', 'content' => base64_encode(self::pdf($result))]],
            'tags' => ['soni-resultado'],
        ];

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            'content' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ignore_errors' => true,
            'timeout' => 20,
        ]]);
        $raw = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/^HTTP\\/\\S+\\s+(\\d{3})/', $statusLine, $match);
        $status = (int) ($match[1] ?? 0);
        $response = is_string($raw) ? json_decode($raw, true) : null;

        if ($status !== 201 || !is_array($response) || empty($response['messageId'])) {
            // Timeout pode ocorrer após aceite no Brevo: não liberar reenvio
            // automático em falhas ambíguas para evitar duplicações.
            if ($status >= 400 && $status < 500) {
                $db->prepare('DELETE FROM quiz_result_email_sends WHERE attempt_id = ?')->execute([$attemptId]);
            }
            throw new RuntimeException('O Brevo não confirmou o envio (HTTP ' . $status . ').');
        }
        return [
            'sent' => true,
            'message' => 'O Brevo aceitou seu e-mail. Confira sua caixa de entrada e a pasta de spam.',
        ];
    }

    // Relatório A4 com cabeçalho, seções e gabarito comentado.
    // Mantém geração autocontida, sem alterar dependências do Composer.
    private static function pdf(array $result): string
    {
        $navy = [0.082, 0.231, 0.290];     // #153B4A
        $teal = [0.031, 0.498, 0.455];     // #087F74
        $gold = [0.847, 0.635, 0.290];     // #D8A24A
        $green = [0.231, 0.620, 0.420];    // #3B9E6B
        $terra = [0.769, 0.498, 0.400];    // #C47F66
        $muted = [0.365, 0.435, 0.451];
        $white = [1, 1, 1];
        $pages = [];
        $commands = [];
        $y = 0;

        $color = static fn (array $rgb, string $op): string =>
            sprintf('%.3F %.3F %.3F %s', $rgb[0], $rgb[1], $rgb[2], $op);
        $rect = static function (float $x, float $bottom, float $w, float $h, array $fill) use ($color): string {
            return $color($fill, 'rg') . "\n" . sprintf('%.1F %.1F %.1F %.1F re f', $x, $bottom, $w, $h);
        };
        $text = static function (float $x, float $top, string $value, int $fontSize, array $fill) use ($color): string {
            $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
            $value = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\(', '\)', ' ', ' '], $value);
            return $color($fill, 'rg') . "\nBT /F1 " . $fontSize . " Tf " .
                sprintf('1 0 0 1 %.1F %.1F Tm', $x, 842 - $top) .
                ' (' . $value . ") Tj ET";
        };
        $newPage = static function () use (&$commands, &$y, &$pages, $rect, $text, $navy, $gold, $muted, $white): void {
            if ($commands !== []) $pages[] = implode("\n", $commands) . "\n";
            $commands = [
                $rect(0, 764, 595, 78, $navy),
                $rect(42, 751, 75, 4, $gold),
                $text(42, 39, 'SONI  |  SONORIZAÇÃO DE IGREJAS', 17, $white),
                $text(42, 59, 'RELATÓRIO DE AVALIAÇÃO', 10, $white),
                $rect(42, 43, 511, 1, [0.88, 0.90, 0.91]),
                $text(42, 815, 'www.somdaigreja.com.br', 9, $muted),
            ];
            $y = 112;
        };
        $newPage();
        $ensure = static function (float $height) use (&$y, $newPage): void {
            if ($y + $height > 785) $newPage();
        };
        $line = static function (string $value, array $ink, int $size = 11, int $indent = 0) use (&$y, &$commands, $text, $ensure): void {
            $value = trim(preg_replace('/\s+/u', ' ', $value));
            $max = $size >= 15 ? 42 : ($indent > 0 ? 82 : 92);
            $segments = preg_split('/\r\n|\r|\n/', wordwrap(iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value), $max, "\n", true));
            foreach ($segments as $segment) {
                $ensure($size + 6);
                // Already converted to WinAnsi for safe line wrapping.
                $commands[] = $text(42 + $indent, $y, iconv('Windows-1252', 'UTF-8//IGNORE', $segment), $size, $ink);
                $y += $size + 6;
            }
        };
        $heading = static function (string $value) use (&$y, &$commands, $ensure, $rect, $line, $navy, $gold): void {
            $ensure(47);
            $y += 13;
            $commands[] = $rect(42, 842 - $y + 4, 4, 22, $gold);
            $line($value, $navy, 15, 12);
            $y += 5;
        };

        $heading('Resultado do teste');
        $line((string) $result['title'], $navy, 15);
        $line('Data: ' . (string) $result['submitted_at'], $muted);
        $ensure(64);
        $commands[] = $rect(42, 842 - ($y + 50), 511, 56, [0.940, 0.970, 0.965]);
        $commands[] = $text(56, $y + 22, 'ACERTOS: ' . $result['correct_answers'] . ' / ' . $result['total_questions'], 13, $navy);
        $commands[] = $text(335, $y + 26, number_format((float) $result['score_percent'], 2, ',', '.') . '%', 24, $teal);
        $y += 73;

        $heading('Classificação e devolutiva');
        $line((string) $result['label'], $teal, 13);
        $line((string) $result['feedback_text'], $navy);
        $heading('Recomendações de estudo');
        $line((string) ($result['study_recommendation'] ?: 'Sem recomendações adicionais.'), $navy);
        $heading('Gabarito comentado');

        foreach (($result['answers'] ?? []) as $index => $entry) {
            $isCorrect = (int) $entry['was_correct'] === 1;
            $accent = $isCorrect ? $green : $terra;
            $ensure(65);
            $y += 7;
            $commands[] = $rect(42, 842 - ($y + 15), 511, 22, $isCorrect ? [0.92, 0.97, 0.93] : [0.99, 0.95, 0.92]);
            $commands[] = $text(52, $y + 10, 'QUESTÃO ' . ($index + 1) . '  -  ' . ($isCorrect ? 'CORRETA' : 'ERRADA'), 11, $accent);
            $y += 35;
            $question = trim((string) ($entry['prompt_text'] ?? ''));
            $line($question !== '' ? $question : '[Questão com mídia]', $navy);
            if ($entry['question_image']) $line('Esta questão contém imagem.', $muted, 9);
            if ($entry['question_audio']) $line('Esta questão contém áudio.', $muted, 9);

            $describe = static function (array $data, string $prefix) use ($entry): string {
                $label = trim((string) ($entry[$prefix . '_text'] ?? ''));
                $media = [];
                if ($entry[$prefix . '_image']) $media[] = 'imagem';
                if ($entry[$prefix . '_audio']) $media[] = 'áudio';
                return $label . ($media !== [] ? ($label !== '' ? ' ' : '') . '[' . implode(' e ', $media) . ']' : '');
            };
            $line('Sua resposta: ' . ($describe($entry, 'selected') ?: '[sem texto]'), $isCorrect ? $green : $terra, 10);
            if (!$isCorrect) $line('Resposta correta: ' . ($describe($entry, 'correct') ?: '[sem texto]'), $green, 10);
            if (trim((string) ($entry['explanation'] ?? '')) !== '') {
                $line('Explicação: ' . $entry['explanation'], $muted, 10);
            }
            $y += 12;
        }
        $pages[] = implode("\n", $commands) . "\n";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        $next = 4;
        foreach ($pages as $stream) {
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = $pageId . ' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
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
