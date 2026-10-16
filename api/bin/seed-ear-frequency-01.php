<?php

declare(strict_types=1);

/**
 * SONI — Teste de Ouvido 01: identificação de regiões de frequência.
 *
 * Uso (na pasta api, com migrações 001-003 aplicadas):
 *   php bin/seed-ear-frequency-01.php           # cria como rascunho
 *   php bin/seed-ear-frequency-01.php --publish # publica imediatamente
 *
 * O comando recusa duplicatas pelo título; não apaga nem modifica testes existentes.
 * Não executar via HTTP. Áudios PCM mono de 2 s, pico aproximado -22 dBFS.
 */

use Dotenv\Dotenv;
use Soni\Database;
use Soni\QuizFeedback;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$title = 'Teste de Ouvido 01 — Identificação de Frequências';
$publish = in_array('--publish', $argv, true);
$validArgs = ['--publish', '--help'];
foreach (array_slice($argv, 1) as $arg) {
    if (!in_array($arg, $validArgs, true)) {
        fwrite(STDERR, 'Argumento não reconhecido: ' . $arg . PHP_EOL);
        exit(1);
    }
}
if (in_array('--help', $argv, true)) {
    echo "Uso: php bin/seed-ear-frequency-01.php [--publish]\n";
    echo "Sem --publish, o teste é salvo como rascunho para revisão.\n";
    exit(0);
}

/**
 * O ouvido percebe a altura (pitch) do tom; estes limites são aproximações
 * pedagógicas e não substituem avaliação clínica ou exame audiométrico.
 * Questões 1-6: uma amostra por questão; 7-12: comparar três amostras.
 */
$questions = [
    [
        'prompt' => 'Ouça o áudio. Ele pertence principalmente a qual região de frequências?',
        'tone' => 180, 'options' => ['Graves', 'Médios', 'Agudos'], 'correct' => 0,
        'explanation' => 'O tom tem 180 Hz: é uma frequência grave. Observe a sensação de som mais encorpado e de altura baixa.',
    ],
    [
        'prompt' => 'Classifique a região de frequência do som reproduzido.',
        'tone' => 900, 'options' => ['Agudos', 'Médios', 'Graves'], 'correct' => 1,
        'explanation' => '900 Hz é uma frequência média, presente em regiões importantes para a inteligibilidade de voz e instrumentos.',
    ],
    [
        'prompt' => 'O áudio está na região grave, média ou aguda?',
        'tone' => 4800, 'options' => ['Graves', 'Médios', 'Agudos'], 'correct' => 2,
        'explanation' => '4.800 Hz pertence à região aguda: compare a percepção de altura com os exemplos mais graves.',
    ],
    [
        'prompt' => 'Ouça atentamente e escolha a classificação correta.',
        'tone' => 220, 'options' => ['Médios', 'Graves', 'Agudos'], 'correct' => 1,
        'explanation' => '220 Hz fica na região grave na divisão pedagógica utilizada neste exercício.',
    ],
    [
        'prompt' => 'Qual região melhor descreve este tom?',
        'tone' => 1250, 'options' => ['Médios', 'Agudos', 'Graves'], 'correct' => 0,
        'explanation' => '1.250 Hz é um tom médio. Treine a diferença entre frequência e intensidade sonora (volume).',
    ],
    [
        'prompt' => 'Identifique a região de frequência do áudio.',
        'tone' => 5600, 'options' => ['Médios', 'Graves', 'Agudos'], 'correct' => 2,
        'explanation' => '5.600 Hz é uma frequência aguda. Não é necessário ouvir em volume alto para reconhecê-la.',
    ],
    [
        'prompt' => 'Ouça os três áudios das alternativas. Qual deles é o MAIS GRAVE?',
        'tone' => null, 'options' => [['Áudio A', 220], ['Áudio B', 1250], ['Áudio C', 4800]], 'correct' => 0,
        'explanation' => 'O áudio A tem 220 Hz. Quanto menor a frequência de um tom puro, mais grave é sua altura percebida.',
    ],
    [
        'prompt' => 'Compare as alternativas. Qual áudio é o MAIS AGUDO?',
        'tone' => null, 'options' => [['Áudio A', 300], ['Áudio B', 900], ['Áudio C', 5600]], 'correct' => 2,
        'explanation' => 'O áudio C tem 5.600 Hz e é o mais agudo entre as três amostras.',
    ],
    [
        'prompt' => 'Qual dos três áudios representa melhor a região MÉDIA?',
        'tone' => null, 'options' => [['Áudio A', 180], ['Áudio B', 900], ['Áudio C', 4800]], 'correct' => 1,
        'explanation' => 'O áudio B tem 900 Hz e ocupa a região média; A é grave e C é agudo.',
    ],
    [
        'prompt' => 'Reproduza as amostras. Qual áudio é o MAIS GRAVE?',
        'tone' => null, 'options' => [['Áudio A', 3500], ['Áudio B', 160], ['Áudio C', 700]], 'correct' => 1,
        'explanation' => 'O áudio B tem 160 Hz, a frequência mais baixa apresentada nesta comparação.',
    ],
    [
        'prompt' => 'Qual áudio apresenta a MAIOR frequência?',
        'tone' => null, 'options' => [['Áudio A', 1600], ['Áudio B', 4800], ['Áudio C', 220]], 'correct' => 1,
        'explanation' => 'O áudio B tem 4.800 Hz. Maior frequência significa maior altura percebida (mais agudo) para tons puros.',
    ],
    [
        'prompt' => 'Escolha o áudio que pertence à região MÉDIA.',
        'tone' => null, 'options' => [['Áudio A', 5600], ['Áudio B', 1250], ['Áudio C', 180]], 'correct' => 1,
        'explanation' => 'O áudio B tem 1.250 Hz e corresponde à faixa média deste exercício.',
    ],
];

function writeAll($handle, string $bytes): void
{
    while ($bytes !== '') {
        $written = fwrite($handle, $bytes);
        if ($written === false || $written === 0) {
            throw new RuntimeException('Erro ao gravar áudio.');
        }
        $bytes = substr($bytes, $written);
    }
}

function makeSineWav(string $path, int $hz): void
{
    $rate = 44100;
    $frames = 2 * $rate;
    $dataSize = $frames * 2;
    $fp = fopen($path, 'wb');
    if ($fp === false) {
        throw new RuntimeException('Não foi possível criar o arquivo WAV.');
    }
    try {
        $header = 'RIFF' . pack('V', 36 + $dataSize) . 'WAVEfmt '
            . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            . 'data' . pack('V', $dataSize);
        writeAll($fp, $header);

        $buffer = '';
        $fadeSamples = (int) ($rate * 0.04);
        for ($i = 0; $i < $frames; $i++) {
            $envelope = min(1.0, $i / $fadeSamples, ($frames - 1 - $i) / $fadeSamples);
            $value = (int) round(sin(2.0 * M_PI * $hz * $i / $rate) * 0.08 * $envelope * 32767);
            $buffer .= pack('v', $value & 0xFFFF);
            if (strlen($buffer) >= 8192) {
                writeAll($fp, $buffer);
                $buffer = '';
            }
        }
        if ($buffer !== '') {
            writeAll($fp, $buffer);
        }
    } finally {
        fclose($fp);
    }
}

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$createdFiles = [];
$db = null;

try {
    $db = Database::connect();
    // Evita duplicação ou alteração inadvertida de um teste com tentativas.
    $check = $db->prepare('SELECT id FROM quizzes WHERE title = ? LIMIT 1');
    $check->execute([$title]);
    if ($check->fetchColumn() !== false) {
        throw new RuntimeException('Esse teste já existe. Edite e publique o rascunho pelo painel; nenhum dado foi alterado.');
    }

    $db->beginTransaction();
    $category = $db->prepare('SELECT id FROM quiz_categories WHERE title = ? ORDER BY id LIMIT 1');
    $category->execute(['Testes de Ouvido']);
    $categoryId = $category->fetchColumn();
    if ($categoryId === false) {
        $db->prepare('INSERT INTO quiz_categories (title, description, sort_order) VALUES (?, ?, ?)')
            ->execute(['Testes de Ouvido', 'Treinamento de percepção auditiva para operadores e técnicos de som.', 20]);
        $categoryId = $db->lastInsertId();
    }

    $db->prepare('INSERT INTO quizzes (category_id, title, description, publication_status) VALUES (?, ?, ?, ?)')
        ->execute([
            $categoryId, $title,
            '12 questões auditivas com tons puros. Use fones de ouvido e volume confortável. '
            . 'Ouça cada amostra quantas vezes precisar, marque uma opção por questão e finalize para ver '
            . 'a pontuação e as explicações. Exercício pedagógico, não exame de audição.',
            $publish ? 'PUBLISHED' : 'DRAFT',
        ]);
    $quizId = (int) $db->lastInsertId();

    QuizFeedback::defaults($db, $quizId);
    $updateFeedback = $db->prepare(
        'UPDATE quiz_feedback_ranges SET feedback_text = ?, study_recommendation = ? '
        . 'WHERE quiz_id = ? AND min_percent = ?'
    );
    $messages = [
        ['0.00', 'Você está iniciando o reconhecimento auditivo das regiões de frequência.',
            'Ouça novamente os tons graves, médios e agudos; use fones e pratique em volume confortável.'],
        ['40.00', 'Você já percebe algumas diferenças de altura, mas ainda confunde regiões sonoras.',
            'Compare tons bem distantes em frequência antes de tentar distinguir regiões próximas.'],
        ['60.00', 'Você reconhece a maior parte das regiões de frequência.',
            'Repita os exercícios de comparação entre três áudios e revise os erros.'],
        ['80.00', 'Muito bom! Sua percepção de graves, médios e agudos está consistente.',
            'Pratique com instrumentos musicais e vozes para transferir o aprendizado à mixagem.'],
        ['95.00', 'Excelente identificação auditiva das frequências apresentadas.',
            'Avance para o treinamento de equalização e reconhecimento de bandas.'],
    ];
    foreach ($messages as [$range, $message, $recommendation]) {
        $updateFeedback->execute([$message, $recommendation, $quizId, $range]);
    }

    $frequencySet = [];
    foreach ($questions as $q) {
        if ($q['tone'] !== null) {
            $frequencySet[(int) $q['tone']] = true;
        }
        foreach ($q['options'] as $option) {
            if (is_array($option)) {
                $frequencySet[(int) $option[1]] = true;
            }
        }
    }

    $storage = dirname(__DIR__) . '/storage/site_media';
    $mediaIds = [];
    foreach (array_keys($frequencySet) as $hz) {
        $random = bin2hex(random_bytes(16));
        $subdir = substr($random, 0, 2);
        $folder = $storage . '/' . $subdir;
        if (!is_dir($folder) && !mkdir($folder, 0770, true) && !is_dir($folder)) {
            throw new RuntimeException('Não foi possível criar o diretório de mídia.');
        }
        if (!is_writable($folder)) {
            throw new RuntimeException('Pasta de mídia sem permissão de gravação.');
        }
        $relative = $subdir . '/' . $random . '.wav';
        $path = $storage . '/' . $relative;
        $createdFiles[] = $path;
        makeSineWav($path, (int) $hz);
        chmod($path, 0640);
        $db->prepare(
            'INSERT INTO site_media (kind, original_name, storage_path, mime_type, size_bytes) '
            . 'VALUES (?, ?, ?, ?, ?)'
        )->execute(['AUDIO', 'soni-ouvido-01-' . $hz . 'hz.wav', $relative, 'audio/x-wav', filesize($path)]);
        $mediaIds[(int) $hz] = (int) $db->lastInsertId();
    }

    $addQuestion = $db->prepare(
        'INSERT INTO quiz_questions (quiz_id, prompt_text, audio_media_id, explanation, sort_order) '
        . 'VALUES (?, ?, ?, ?, ?)'
    );
    $addOption = $db->prepare(
        'INSERT INTO quiz_options (question_id, option_text, audio_media_id, is_correct, sort_order) '
        . 'VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($questions as $index => $q) {
        $qAudio = $q['tone'] === null ? null : $mediaIds[(int) $q['tone']];
        $addQuestion->execute([$quizId, $q['prompt'], $qAudio, $q['explanation'], ($index + 1) * 10]);
        $questionId = (int) $db->lastInsertId();
        foreach ($q['options'] as $j => $choice) {
            $label = is_array($choice) ? $choice[0] : $choice;
            $audioId = is_array($choice) ? $mediaIds[(int) $choice[1]] : null;
            $addOption->execute([$questionId, $label, $audioId, $j === $q['correct'] ? 1 : 0, ($j + 1) * 10]);
        }
    }

    $db->commit();
    echo 'Teste criado com sucesso.' . PHP_EOL;
    echo 'ID: ' . $quizId . ' | Questões: ' . count($questions)
        . ' | Áudios: ' . count($mediaIds) . ' | Estado: ' . ($publish ? 'PUBLICADO' : 'RASCUNHO') . PHP_EOL;
    echo 'Editar: /admin/quizzes | Acessar quando publicado: /testes/' . $quizId . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    foreach ($createdFiles as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    fwrite(STDERR, 'Importação não concluída: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
