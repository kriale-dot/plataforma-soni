<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class QuizResultEmail
{
    private static function secret(): string
    {
        $secret = (string) ($_ENV['JWT_SECRET'] ?? '');
        if (strlen($secret) < 32) throw new RuntimeException('Segredo de assinatura não configurado.');
        return $secret;
    }

    public static function token(int $quizId, int $attemptId): string
    {
        return hash_hmac('sha256', 'soni-quiz-email:v1:' . $quizId . ':' . $attemptId, self::secret());
    }

    public static function send(PDO $db, int $quizId, array $body): void
    {
        $attemptId = filter_var($body['attempt_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $email = $body['email'] ?? null;
        $token = $body['email_token'] ?? null;
        if (!$attemptId || !is_string($email) || strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
            throw new InvalidArgumentException('Informe um endereço de e-mail válido.');
        }
        if (!is_string($token) || !hash_equals(self::token($quizId, $attemptId), $token)) {
            throw new InvalidArgumentException('Solicitação inválida. Finalize novamente o teste.');
        }

        $apiKey = (string) ($_ENV['BREVO_API_KEY'] ?? '');
        $sender = (string) ($_ENV['SONI_MAIL_FROM'] ?? '');
        if ($apiKey === '' || !filter_var($sender, FILTER_VALIDATE_EMAIL) || !function_exists('curl_init')) {
            throw new RuntimeException('Serviço de e-mail indisponível.');
        }

        $stmt = $db->prepare(
            'SELECT q.title, a.total_questions, a.correct_answers, a.score_percent,
                    f.label, f.feedback_text, f.study_recommendation
             FROM quiz_attempts a
             JOIN quizzes q ON q.id = a.quiz_id
             JOIN quiz_attempt_feedback f ON f.attempt_id = a.id
             WHERE a.id = ? AND a.quiz_id = ?'
        );
        $stmt->execute([$attemptId, $quizId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) throw new InvalidArgumentException('Resultado não encontrado.');

        // Reserva o envio ANTES do contato externo, impedindo repetição/spam.
        // Em falhas de rede a reserva é liberada para uma nova tentativa.
        try {
            $db->prepare('INSERT INTO quiz_result_emails (attempt_id, recipient_email) VALUES (?, ?)')
               ->execute([$attemptId, $email]);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new InvalidArgumentException('O resultado desta tentativa já foi solicitado por e-mail.');
            }
            throw $e;
        }

        $score = rtrim(rtrim(number_format((float) $result['score_percent'], 2, '.', ''), '0'), '.');
        $message = "SONI - Resultado do teste\n\n"
            . "Teste: {$result['title']}\n"
            . "Acertos: {$result['correct_answers']} de {$result['total_questions']}\n"
            . "Pontuação: {$score}%\n\n"
            . "Devolutiva: {$result['label']}\n{$result['feedback_text']}\n\n";
        if (!empty($result['study_recommendation'])) {
            $message .= "Recomendação de estudo:\n{$result['study_recommendation']}\n\n";
        }
        $message .= "Este e-mail foi solicitado ao concluir um teste em somdaigreja.com.br.\n";

        $payload = json_encode([
            'sender' => ['email' => $sender, 'name' => 'SONI - Som da Igreja'],
            'to' => [['email' => $email]],
            'subject' => 'SONI | Resultado do teste: ' . substr((string) $result['title'], 0, 120),
            'textContent' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['api-key: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        try {
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false || $code < 200 || $code >= 300) {
                throw new RuntimeException('Falha no serviço de envio de e-mail.');
            }
        } catch (Throwable $error) {
            $db->prepare('DELETE FROM quiz_result_emails WHERE attempt_id = ? AND recipient_email = ?')
               ->execute([$attemptId, $email]);
            throw $error;
        } finally {
            curl_close($ch);
        }
    }
}
