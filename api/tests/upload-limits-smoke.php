<?php

declare(strict_types=1);

// Teste de configuração seguro: não acessa banco de dados nem grava mídias.
require dirname(__DIR__) . '/vendor/autoload.php';

$limits = (new ReflectionClass(\\Soni\\SiteMedia::class))->getReflectionConstant('ALLOWED');
if ($limits === false) throw new RuntimeException('Mapa ALLOWED não encontrado.');
$allowed = $limits->getValue();
$expected = [
    'jpg' => ['IMAGE', 20],
    'jpeg' => ['IMAGE', 20],
    'png' => ['IMAGE', 20],
    'webp' => ['IMAGE', 20],
    'gif' => ['IMAGE', 20],
    'mp3' => ['AUDIO', 50],
    'wav' => ['AUDIO', 50],
    'ogg' => ['AUDIO', 50],
    'mp4' => ['VIDEO', 200],
    'webm' => ['VIDEO', 200],
    'pdf' => ['DOCUMENT', 50],
];

$actualKeys = array_keys($allowed);
$expectedKeys = array_keys($expected);
sort($actualKeys);
sort($expectedKeys);
if ($actualKeys !== $expectedKeys) throw new RuntimeException('Extensões permitidas alteradas inesperadamente.');

foreach ($expected as $ext => [$kind, $mib]) {
    [$actualKind, $mime, $bytes] = $allowed[$ext];
    if ($actualKind !== $kind || !is_string($mime) || $mime === '' || $bytes !== $mib * 1024 * 1024) {
        throw new RuntimeException('Limite/tipo de upload incorreto: .' . $ext);
    }
}
echo "Limites de upload verificados (imagem 20, áudio/PDF 50, vídeo 200 MiB).\\n";
