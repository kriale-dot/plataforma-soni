<?php

declare(strict_types=1);

// Verificação isolada: não acessa banco de dados nem envia e-mails.
require dirname(__DIR__) . '/vendor/autoload.php';

$_ENV['JWT_SECRET'] = str_repeat('t', 64);

function verify(bool $ok, string $name): void
{
    if (!$ok) throw new RuntimeException('Falhou: ' . $name);
    echo 'OK: ' . $name . PHP_EOL;
}

$token = \Soni\QuizResultEmail::issueToken(123);
verify((bool) preg_match('/^123\.[0-9]{10}\.[a-f0-9]{64}$/', $token), 'token temporário assinado');

$authentication = new ReflectionMethod(\Soni\QuizResultEmail::class, 'authenticate');
$authentication->invoke(null, 123, $token);
verify(true, 'token válido aceito');

foreach ([[124, $token], [123, $token . 'a'], [123, '123.1000000000.' . str_repeat('a', 64)]] as [$attempt, $invalid]) {
    try {
        $authentication->invoke(null, $attempt, $invalid);
        throw new RuntimeException('Token inválido foi aceito.');
    } catch (InvalidArgumentException) {
        verify(true, 'token inválido rejeitado');
    }
}

$makePdf = new ReflectionMethod(\Soni\QuizResultEmail::class, 'pdf');
$result = [
    'title' => 'Teste de microfonia',
    'submitted_at' => '2026-10-20 10:00:00',
    'correct_answers' => 8,
    'total_questions' => 10,
    'score_percent' => '80.00',
    'label' => 'Muito bom',
    'feedback_text' => str_repeat('Desempenho satisfatório com atenção à microfonia. ', 60),
    'study_recommendation' => 'Revise ganho e posicionamento.',
];
$result['answers'] = [
    [
        'prompt_text' => 'Qual é a função do ganho no pré-amplificador?',
        'question_image' => null, 'question_audio' => null,
        'was_correct' => 1,
        'selected_text' => 'Ajustar o nível de entrada', 'selected_image' => null, 'selected_audio' => null,
        'correct_text' => 'Ajustar o nível de entrada', 'correct_image' => null, 'correct_audio' => null,
        'explanation' => 'O ganho controla a amplificação do sinal de entrada.',
    ],
    [
        'prompt_text' => 'O que causa microfonia?', 'question_image' => null, 'question_audio' => null,
        'was_correct' => 0,
        'selected_text' => 'Ruído elétrico', 'selected_image' => null, 'selected_audio' => null,
        'correct_text' => 'Realimentação acústica', 'correct_image' => null, 'correct_audio' => null,
        'explanation' => 'Microfonia acontece quando o som do alto-falante retorna ao microfone.',
    ],
];
$pdf = $makePdf->invoke(null, $result);
verify(str_starts_with($pdf, '%PDF-1.4'), 'PDF com cabeçalho correto');
verify(str_contains($pdf, 'Teste de microfonia') && str_contains($pdf, '80,00%'), 'PDF contém resultados');
verify(str_contains($pdf, 'QUEST') && str_contains($pdf, 'Sua resposta:') && str_contains($pdf, 'Resposta correta:'), 'PDF contém gabarito comentado');
verify(str_contains($pdf, '%%EOF'), 'PDF com marcador final');
preg_match('/\/Count (\d+)/', $pdf, $pages);
verify((int) ($pages[1] ?? 0) >= 2, 'PDF com paginação para devolutivas longas');
echo "Validação isolada do relatório concluída.\n";
