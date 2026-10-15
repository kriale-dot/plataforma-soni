<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;
use Throwable;

final class QuizFeedback
{
    private const DEFAULTS = [
        ['0.00', '39.99', 'Conhecimento inicial', 'Você está começando sua jornada. Os fundamentos ainda precisam de atenção.', 'Revise os conceitos básicos e refaça o teste depois de estudar.'],
        ['40.00', '59.99', 'Em desenvolvimento', 'Você reconhece alguns conceitos, mas ainda há conhecimentos importantes a fortalecer.', 'Releia as explicações das questões e pratique os assuntos em que errou.'],
        ['60.00', '79.99', 'Bom desempenho', 'Você demonstra boa compreensão dos assuntos avaliados.', 'Aprofunde os temas em que ainda teve dificuldade.'],
        ['80.00', '94.99', 'Muito bom', 'Você apresenta conhecimento consistente sobre este conteúdo.', 'Continue praticando para consolidar os detalhes técnicos.'],
        ['95.00', '100.00', 'Excelente', 'Parabéns! Você demonstrou excelente domínio do conteúdo avaliado.', 'Mantenha a prática e compartilhe os conhecimentos com sua equipe.'],
    ];

    public static function defaults(PDO $db, int $quizId): void
    {
        $stmt = $db->prepare(
            'INSERT INTO quiz_feedback_ranges
             (quiz_id, min_percent, max_percent, label, feedback_text, study_recommendation, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach (self::DEFAULTS as $i => [$min, $max, $label, $text, $recommendation]) {
            $stmt->execute([$quizId, $min, $max, $label, $text, $recommendation, ($i + 1) * 10]);
        }
    }

    public static function list(PDO $db, int $quizId): array
    {
        $stmt = $db->prepare(
            'SELECT id, min_percent, max_percent, label, feedback_text, study_recommendation
               FROM quiz_feedback_ranges WHERE quiz_id = ?
              ORDER BY min_percent, id'
        );
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }

    private static function percent(mixed $raw, string $field): int
    {
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            throw new InvalidArgumentException("Percentual inválido: {$field}.");
        }
        $value = trim((string) $raw);
        if (!preg_match('/^(?:100(?:\.0{1,2})?|[0-9]{1,2}(?:\.[0-9]{1,2})?)$/D', $value)) {
            throw new InvalidArgumentException("Percentual inválido em {$field}: use entre 0 e 100, com até duas casas decimais.");
        }
        return (int) round(((float) $value) * 100);
    }

    private static function text(mixed $value, string $field, int $max, bool $required): ?string
    {
        if ($value === null || $value === '') {
            if ($required) throw new InvalidArgumentException("Preencha {$field}.");
            return null;
        }
        if (!is_string($value)) throw new InvalidArgumentException("Conteúdo inválido em {$field}.");
        $value = trim($value);
        if ($value === '') {
            if ($required) throw new InvalidArgumentException("Preencha {$field}.");
            return null;
        }
        if (strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new InvalidArgumentException("Texto muito longo ou inválido em {$field}.");
        }
        return $value;
    }

    /**
     * As faixas devem cobrir todos os valores possíveis de pontuação (0.00 a 100.00),
     * sem lacunas nem sobreposição; internamente usa centésimos inteiros.
     */
    private static function normalize(array $ranges): array
    {
        if (!array_is_list($ranges) || count($ranges) < 1 || count($ranges) > 20) {
            throw new InvalidArgumentException('Cadastre de 1 a 20 faixas de desempenho.');
        }
        $normalized = [];
        foreach ($ranges as $index => $range) {
            if (!is_array($range)) throw new InvalidArgumentException('Faixa de desempenho inválida.');
            $min = self::percent($range['min_percent'] ?? null, 'mínimo');
            $max = self::percent($range['max_percent'] ?? null, 'máximo');
            if ($min > $max) throw new InvalidArgumentException('O percentual mínimo não pode superar o máximo.');
            $label = self::text($range['label'] ?? null, 'classificação', 120, true);
            $message = self::text($range['feedback_text'] ?? null, 'devolutiva', 3000, true);
            $rec = self::text($range['study_recommendation'] ?? null, 'recomendação', 2000, false);
            $normalized[] = [$min, $max, $label, $message, $rec];
        }
        usort($normalized, static fn ($a, $b) => $a[0] <=> $b[0]);
        $expected = 0;
        foreach ($normalized as [$min, $max]) {
            if ($min !== $expected) {
                throw new InvalidArgumentException(
                    'As faixas devem cobrir de 0,00% a 100,00%, sem lacunas ou sobreposições (duas casas decimais).'
                );
            }
            $expected = $max + 1;
        }
        if ($expected !== 10001) {
            throw new InvalidArgumentException('A última faixa precisa terminar em 100,00%.');
        }
        return $normalized;
    }

    public static function assertValid(PDO $db, int $quizId): void
    {
        self::normalize(self::list($db, $quizId));
    }

    public static function replace(PDO $db, int $quizId, array $body): array
    {
        $ranges = self::normalize($body['ranges'] ?? []);
        $db->beginTransaction();
        try {
            // Impede troca de faixas durante a correção de uma tentativa.
            $stmt = $db->prepare('SELECT id FROM quizzes WHERE id = ? FOR UPDATE');
            $stmt->execute([$quizId]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Teste não encontrado.');

            $db->prepare('DELETE FROM quiz_feedback_ranges WHERE quiz_id = ?')->execute([$quizId]);
            $insert = $db->prepare(
                'INSERT INTO quiz_feedback_ranges
                 (quiz_id, min_percent, max_percent, label, feedback_text, study_recommendation, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($ranges as $i => [$min, $max, $label, $message, $rec]) {
                $insert->execute([
                    $quizId, number_format($min / 100, 2, '.', ''),
                    number_format($max / 100, 2, '.', ''),
                    $label, $message, $rec, ($i + 1) * 10,
                ]);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
        return self::list($db, $quizId);
    }

    public static function forScore(PDO $db, int $quizId, float $score): array
    {
        $stmt = $db->prepare(
            'SELECT min_percent, max_percent, label, feedback_text, study_recommendation
               FROM quiz_feedback_ranges
              WHERE quiz_id = ? AND min_percent <= ? AND max_percent >= ?
              ORDER BY min_percent LIMIT 2'
        );
        $stmt->execute([$quizId, $score, $score]);
        $rows = $stmt->fetchAll();
        if (count($rows) !== 1) {
            throw new InvalidArgumentException('As faixas de desempenho deste teste precisam ser revisadas.');
        }
        return $rows[0];
    }
}
