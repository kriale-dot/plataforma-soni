<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class QuizService
{
    private static function text(mixed $value, int $limit, string $name, bool $required = false): ?string
    {
        if ($value === null || $value === '') {
            if ($required) throw new InvalidArgumentException("Informe {$name}.");
            return null;
        }
        if (!is_string($value)) throw new InvalidArgumentException("Campo inválido: {$name}.");
        $value = trim($value);
        if ($value === '') {
            if ($required) throw new InvalidArgumentException("Informe {$name}.");
            return null;
        }
        if (strlen($value) > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new InvalidArgumentException("O campo {$name} é inválido ou muito longo.");
        }
        return $value;
    }

    private static function identifier(mixed $value, string $name): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException("Identificador inválido: {$name}.");
        }
        if (!preg_match('/^[1-9][0-9]*$/D', (string) $value)) {
            throw new InvalidArgumentException("Identificador inválido: {$name}.");
        }
        return (int) $value;
    }

    private static function media(PDO $db, mixed $value, string $kind): ?int
    {
        if ($value === null || $value === '' || $value === 0) return null;
        $id = self::identifier($value, 'mídia');
        $stmt = $db->prepare('SELECT kind FROM site_media WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() !== $kind) {
            throw new InvalidArgumentException("Escolha uma mídia do tipo {$kind}.");
        }
        return $id;
    }

    private static function getQuiz(PDO $db, int $id, bool $lock = false): array
    {
        $stmt = $db->prepare(
            'SELECT id, category_id, title, description, publication_status FROM quizzes WHERE id = ?' .
            ($lock ? ' FOR UPDATE' : '')
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) throw new InvalidArgumentException('Teste não encontrado.');
        return $row;
    }

    private static function mutable(PDO $db, int $quizId): array
    {
        $quiz = self::getQuiz($db, $quizId, true);
        if ($quiz['publication_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Retorne o teste para rascunho antes de editar as questões.');
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ?');
        $stmt->execute([$quizId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('Teste com tentativas registradas: crie uma nova versão em outro teste.');
        }
        return $quiz;
    }

    private static function ensureCategory(PDO $db, int $id): void
    {
        $stmt = $db->prepare('SELECT id FROM quiz_categories WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Categoria não encontrada.');
    }

    public static function adminList(PDO $db): array
    {
        $categories = $db->query(
            'SELECT id, title, description, sort_order FROM quiz_categories ORDER BY sort_order, id'
        )->fetchAll();
        $quizzes = $db->query(
            'SELECT q.id, q.category_id, q.title, q.description, q.publication_status,
                    (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count,
                    (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id = q.id) AS attempt_count
             FROM quizzes q ORDER BY q.updated_at DESC, q.id DESC'
        )->fetchAll();
        return ['categories' => $categories, 'quizzes' => $quizzes];
    }

    public static function saveCategory(PDO $db, ?int $id, array $body): array
    {
        $title = self::text($body['title'] ?? null, 120, 'título da categoria', true);
        $description = self::text($body['description'] ?? null, 500, 'descrição');
        if ($id === null) {
            $stmt = $db->prepare('INSERT INTO quiz_categories (title, description) VALUES (?, ?)');
            $stmt->execute([$title, $description]);
            $id = (int) $db->lastInsertId();
        } else {
            self::ensureCategory($db, $id);
            $stmt = $db->prepare('UPDATE quiz_categories SET title = ?, description = ? WHERE id = ?');
            $stmt->execute([$title, $description, $id]);
        }
        return ['id' => $id, 'title' => $title, 'description' => $description];
    }

    public static function createQuiz(PDO $db, array $body): array
    {
        $categoryId = self::identifier($body['category_id'] ?? null, 'categoria');
        self::ensureCategory($db, $categoryId);
        $title = self::text($body['title'] ?? null, 180, 'título do teste', true);
        $description = self::text($body['description'] ?? null, 1000, 'descrição');
        $stmt = $db->prepare('INSERT INTO quizzes (category_id, title, description) VALUES (?, ?, ?)');
        $stmt->execute([$categoryId, $title, $description]);
        return self::adminDetail($db, (int) $db->lastInsertId());
    }

    private static function questionList(PDO $db, int $quizId, bool $includeAnswers): array
    {
        $stmt = $db->prepare(
            'SELECT id, prompt_text, image_media_id, audio_media_id, explanation, sort_order
             FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll();
        $optionsStmt = $db->prepare(
            'SELECT id, option_text, image_media_id, audio_media_id, is_correct, sort_order
             FROM quiz_options WHERE question_id = ? ORDER BY sort_order, id'
        );
        foreach ($questions as &$question) {
            $optionsStmt->execute([$question['id']]);
            $options = $optionsStmt->fetchAll();
            if (!$includeAnswers) {
                unset($question['explanation']);
                foreach ($options as &$option) {
                    unset($option['is_correct']);
                }
                unset($option);
            }
            $question['options'] = $options;
        }
        unset($question);
        return $questions;
    }

    public static function adminDetail(PDO $db, int $id): array
    {
        $quiz = self::getQuiz($db, $id);
        $quiz['questions'] = self::questionList($db, $id, true);
        $media = $db->query(
            'SELECT id, kind, original_name, mime_type, size_bytes FROM site_media ORDER BY id DESC LIMIT 250'
        )->fetchAll();
        $quiz['media'] = $media;
        return $quiz;
    }

    private static function validatePublish(PDO $db, int $quizId): void
    {
        $stmt = $db->prepare(
            'SELECT qq.id,
                    (SELECT COUNT(*) FROM quiz_options qo WHERE qo.question_id=qq.id) AS option_count,
                    (SELECT COUNT(*) FROM quiz_options qo WHERE qo.question_id=qq.id AND qo.is_correct=1) AS correct_count
             FROM quiz_questions qq WHERE qq.quiz_id = ?'
        );
        $stmt->execute([$quizId]);
        $rows = $stmt->fetchAll();
        if (count($rows) < 1) {
            throw new InvalidArgumentException('Cadastre pelo menos uma questão antes de publicar.');
        }
        foreach ($rows as $row) {
            if ((int) $row['option_count'] < 2 || (int) $row['option_count'] > 6 ||
                (int) $row['correct_count'] !== 1) {
                throw new InvalidArgumentException('Cada questão deve ter de 2 a 6 alternativas e uma única resposta correta.');
            }
        }
    }

    public static function updateQuiz(PDO $db, int $id, array $body): array
    {
        $db->beginTransaction();
        try {
            $current = self::getQuiz($db, $id, true);
            $title = self::text($body['title'] ?? $current['title'], 180, 'título do teste', true);
            $description = self::text($body['description'] ?? $current['description'], 1000, 'descrição');
            $categoryId = self::identifier($body['category_id'] ?? $current['category_id'], 'categoria');
            self::ensureCategory($db, $categoryId);
            $status = $body['publication_status'] ?? $current['publication_status'];
            if (!in_array($status, ['DRAFT', 'PUBLISHED'], true)) {
                throw new InvalidArgumentException('Estado de publicação inválido.');
            }
            if ($status === 'PUBLISHED') self::validatePublish($db, $id);
            $stmt = $db->prepare(
                'UPDATE quizzes SET title = ?, description = ?, category_id = ?, publication_status = ? WHERE id = ?'
            );
            $stmt->execute([$title, $description, $categoryId, $status, $id]);
            $db->commit();
        } catch (Throwable $err) {
            if ($db->inTransaction()) $db->rollBack();
            throw $err;
        }
        return self::adminDetail($db, $id);
    }

    private static function validatedOptions(PDO $db, mixed $options): array
    {
        if (!is_array($options) || count($options) < 2 || count($options) > 6 ||
            !array_is_list($options)) {
            throw new InvalidArgumentException('Forneça de 2 a 6 alternativas.');
        }
        $data = [];
        $correct = 0;
        foreach ($options as $option) {
            if (!is_array($option)) throw new InvalidArgumentException('Alternativa inválida.');
            $title = self::text($option['option_text'] ?? null, 1200, 'texto da alternativa');
            $image = self::media($db, $option['image_media_id'] ?? null, 'IMAGE');
            $audio = self::media($db, $option['audio_media_id'] ?? null, 'AUDIO');
            if ($title === null && $image === null && $audio === null) {
                throw new InvalidArgumentException('Cada alternativa precisa de texto, imagem ou áudio.');
            }
            $isCorrect = $option['is_correct'] ?? false;
            if (!in_array($isCorrect, [true, false, 1, 0, '1', '0'], true)) {
                throw new InvalidArgumentException('Resposta correta inválida.');
            }
            $flag = (int) (bool) $isCorrect;
            $correct += $flag;
            $data[] = [$title, $image, $audio, $flag];
        }
        if ($correct !== 1) throw new InvalidArgumentException('Marque exatamente uma resposta correta.');
        return $data;
    }

    public static function saveQuestion(PDO $db, int $quizId, ?int $id, array $body): array
    {
        $db->beginTransaction();
        try {
            self::mutable($db, $quizId);
            if ($id !== null) {
                $stmt = $db->prepare('SELECT id FROM quiz_questions WHERE id = ? AND quiz_id = ? FOR UPDATE');
                $stmt->execute([$id, $quizId]);
                if (!$stmt->fetch()) throw new InvalidArgumentException('Questão não encontrada.');
            }
            $text = self::text($body['prompt_text'] ?? null, 5000, 'enunciado');
            $image = self::media($db, $body['image_media_id'] ?? null, 'IMAGE');
            $audio = self::media($db, $body['audio_media_id'] ?? null, 'AUDIO');
            $explanation = self::text($body['explanation'] ?? null, 2500, 'explicação');
            if ($text === null && $image === null && $audio === null) {
                throw new InvalidArgumentException('Questão precisa de texto, imagem ou áudio.');
            }
            $options = self::validatedOptions($db, $body['options'] ?? null);
            if ($id === null) {
                $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM quiz_questions WHERE quiz_id = ?');
                $stmt->execute([$quizId]);
                $order = (int) $stmt->fetchColumn();
                $stmt = $db->prepare(
                    'INSERT INTO quiz_questions (quiz_id, prompt_text, image_media_id, audio_media_id, explanation, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$quizId, $text, $image, $audio, $explanation, $order]);
                $id = (int) $db->lastInsertId();
            } else {
                $stmt = $db->prepare(
                    'UPDATE quiz_questions SET prompt_text=?, image_media_id=?, audio_media_id=?, explanation=? WHERE id=?'
                );
                $stmt->execute([$text, $image, $audio, $explanation, $id]);
                $db->prepare('DELETE FROM quiz_options WHERE question_id=?')->execute([$id]);
            }
            $stmt = $db->prepare(
                'INSERT INTO quiz_options (question_id, option_text, image_media_id, audio_media_id, is_correct, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($options as $index => [$label, $imageId, $audioId, $correct]) {
                $stmt->execute([$id, $label, $imageId, $audioId, $correct, ($index + 1) * 10]);
            }
            $db->commit();
        } catch (Throwable $err) {
            if ($db->inTransaction()) $db->rollBack();
            throw $err;
        }
        $questions = self::questionList($db, $quizId, true);
        foreach ($questions as $question) {
            if ((int) $question['id'] === $id) return $question;
        }
        throw new RuntimeException('Questão não disponível após salvar.');
    }

    public static function deleteQuestion(PDO $db, int $quizId, int $questionId): array
    {
        $db->beginTransaction();
        try {
            self::mutable($db, $quizId);
            $stmt = $db->prepare('DELETE FROM quiz_questions WHERE quiz_id = ? AND id = ?');
            $stmt->execute([$quizId, $questionId]);
            if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('Questão não encontrada.');
            $db->commit();
        } catch (Throwable $err) {
            if ($db->inTransaction()) $db->rollBack();
            throw $err;
        }
        return ['deleted' => true];
    }

    public static function listPublic(PDO $db): array
    {
        return $db->query(
            "SELECT q.id, q.title, q.description, c.id AS category_id, c.title AS category_title,
                    (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
             FROM quizzes q JOIN quiz_categories c ON c.id = q.category_id
             WHERE q.publication_status = 'PUBLISHED'
             ORDER BY c.sort_order, c.id, q.id"
        )->fetchAll();
    }

    public static function publicDetail(PDO $db, int $id): array
    {
        $quiz = self::getQuiz($db, $id);
        if ($quiz['publication_status'] !== 'PUBLISHED') {
            throw new InvalidArgumentException('Teste não publicado.');
        }
        $quiz['questions'] = self::questionList($db, $id, false);
        // NUNCA retornar explicação, is_correct ou chave de resposta nesta rota.
        return $quiz;
    }

    public static function submit(PDO $db, int $id, array $body): array
    {
        $answers = $body['answers'] ?? null;
        if (!is_array($answers) || !array_is_list($answers) || count($answers) > 100) {
            throw new InvalidArgumentException('Envie a lista de respostas do teste.');
        }
        $db->beginTransaction();
        try {
            $quiz = self::getQuiz($db, $id, true);
            if ($quiz['publication_status'] !== 'PUBLISHED') {
                throw new InvalidArgumentException('Teste não disponível para respostas.');
            }
            $questions = self::questionList($db, $id, true);
            if (count($questions) < 1 || count($questions) > 100 || count($answers) !== count($questions)) {
                throw new InvalidArgumentException('Responda todas as questões antes de finalizar.');
            }
            $submitted = [];
            foreach ($answers as $entry) {
                if (!is_array($entry)) throw new InvalidArgumentException('Resposta inválida.');
                $qId = self::identifier($entry['question_id'] ?? null, 'questão');
                $oId = self::identifier($entry['option_id'] ?? null, 'alternativa');
                if (isset($submitted[$qId])) throw new InvalidArgumentException('Questão repetida na submissão.');
                $submitted[$qId] = $oId;
            }
            $feedback = [];
            $correct = 0;
            foreach ($questions as $question) {
                $questionId = (int) $question['id'];
                if (!isset($submitted[$questionId])) throw new InvalidArgumentException('Existe questão sem resposta.');
                $selected = $submitted[$questionId];
                $validIds = array_map('intval', array_column($question['options'], 'id'));
                if (!in_array($selected, $validIds, true)) {
                    throw new InvalidArgumentException('Alternativa não pertence à questão.');
                }
                $right = null;
                foreach ($question['options'] as $option) {
                    if ((int) $option['is_correct'] === 1) $right = (int) $option['id'];
                }
                if ($right === null) throw new RuntimeException('Questão sem chave de resposta.');
                $hit = $selected === $right;
                if ($hit) $correct++;
                $feedback[] = [
                    'question_id' => $questionId,
                    'selected_option_id' => $selected,
                    'correct_option_id' => $right,
                    'correct' => $hit,
                    'explanation' => $question['explanation'],
                ];
            }
            $total = count($questions);
            $score = round(100 * $correct / $total, 2);
            $stmt = $db->prepare(
                'INSERT INTO quiz_attempts (quiz_id, total_questions, correct_answers, score_percent) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$id, $total, $correct, $score]);
            $attemptId = (int) $db->lastInsertId();
            $stmt = $db->prepare(
                'INSERT INTO quiz_attempt_answers (attempt_id, question_id, option_id, was_correct) VALUES (?, ?, ?, ?)'
            );
            foreach ($feedback as $entry) {
                $stmt->execute([$attemptId, $entry['question_id'], $entry['selected_option_id'], (int) $entry['correct']]);
            }
            $db->commit();
        } catch (Throwable $err) {
            if ($db->inTransaction()) $db->rollBack();
            throw $err;
        }
        return [
            'attempt_id' => $attemptId,
            'total' => $total,
            'correct' => $correct,
            'score_percent' => $score,
            'feedback' => $feedback,
        ];
    }
}
