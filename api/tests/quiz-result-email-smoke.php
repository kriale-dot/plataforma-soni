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
$pdf = $makePdf->invoke(null, $result);
verify(str_starts_with($pdf, '%PDF-1.4'), 'PDF com cabeçalho correto');
verify(str_contains($pdf, 'Teste de microfonia') && str_contains($pdf, '80.00%'), 'PDF contém resultados');
verify(str_contains($pdf, '%%EOF'), 'PDF com marcador final');
preg_match('/\/Count (\d+)/', $pdf, $pages);
verify((int) ($pages[1] ?? 0) >= 2, 'PDF com paginação para devolutivas longas');
echo "Validação isolada do relatório concluída.\n";
