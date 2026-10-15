<?php

declare(strict_types=1);

// Teste HTTP somente no banco MariaDB descartável do GitHub Actions.
// NÃO EXECUTAR no banco do usuário, pois cria e publica dados.
function callApi(string $method, string $path, ?array $data = null, ?string $token = null): array
{
    $headers = ['Accept: application/json'];
    if ($data !== null) $headers[] = 'Content-Type: application/json';
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $data === null ? '' : json_encode($data, JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 15,
    ]]);
    $raw = file_get_contents('http://127.0.0.1:8282/api' . $path, false, $context);
    if ($raw === false) throw new RuntimeException("Falha HTTP {$method} {$path}");
    preg_match('/HTTP\/\S+\s+(\d{3})/', $http_response_header[0] ?? '', $match);
    $json = json_decode($raw, true);
    return [(int) ($match[1] ?? 0), $json, $raw];
}

function demand(bool $pass, string $description): void
{
    if (!$pass) throw new RuntimeException('Falhou: ' . $description);
    echo "OK: {$description}\n";
}

[$status, $login] = callApi('POST', '/admin/login', ['email' => 'ci@example.test', 'password' => 'ci-password']);
demand($status === 200 && isset($login['token']), 'Admin autenticado');
$token = $login['token'];

[$status] = callApi('GET', '/admin/quizzes');
demand($status === 401, 'Admin de testes exige JWT');

[$status, $catalog] = callApi('GET', '/admin/quizzes', null, $token);
demand($status === 200 && isset($catalog['categories'][0]['id']), 'Categorias criadas pela migração');
$catId = (int) $catalog['categories'][0]['id'];

[$status, $created] = callApi('POST', '/admin/quizzes', [
    'title' => 'Teste privado CI',
    'description' => 'Teste de publicação',
    'category_id' => $catId,
], $token);
demand($status === 200 && isset($created['id']) &&
    $created['publication_status'] === 'DRAFT', 'Novo teste inicia em rascunho');
$id = (int) $created['id'];
$imageId = null;
foreach (($created['media'] ?? []) as $media) {
    if ($media['kind'] === 'IMAGE') {
        $imageId = (int) $media['id'];
        break;
    }
}
demand($imageId !== null, 'Biblioteca de imagem anterior disponível para reuso');

[$status, $published] = callApi('GET', '/quizzes');
demand($status === 200 && count($published) === 0, 'Teste em rascunho não consta no catálogo');

[$status] = callApi('GET', '/quizzes/' . $id);
demand($status === 404, 'Rascunho não acessível ao aluno');
[$status] = callApi('GET', '/site/media/' . $imageId);
demand($status === 404, 'Mídia ainda não vinculada a teste publicado permanece privada');

[$status] = callApi('PUT', '/admin/quizzes/' . $id, [
    'publication_status' => 'PUBLISHED',
], $token);
demand($status === 422, 'Publicação vazia bloqueada');

[$status] = callApi('POST', '/admin/quizzes/' . $id . '/questions', [
    'prompt_text' => 'Questão sem opção correta',
    'options' => [
        ['option_text' => 'Errado A', 'is_correct' => false],
        ['option_text' => 'Errado B', 'is_correct' => false],
    ],
], $token);
demand($status === 422, 'Questão exige uma resposta correta');

[$status, $q] = callApi('POST', '/admin/quizzes/' . $id . '/questions', [
    'prompt_text' => 'Qual situação requer atenção à microfonia?',
    'image_media_id' => $imageId,
    'explanation' => 'Microfones voltados para caixas podem realimentar o sistema.',
    'options' => [
        ['option_text' => 'Microfone apontado diretamente para o retorno', 'is_correct' => true],
        ['option_text' => 'Ganhos ajustados corretamente', 'image_media_id' => $imageId, 'is_correct' => false],
        ['option_text' => 'Caixas fora do eixo principal do microfone', 'is_correct' => false],
    ],
], $token);
demand($status === 200 && isset($q['id']) && count($q['options']) === 3,
    'Questão e alternativas persistidas');
$qid = (int) $q['id'];
$correctOption = (int) $q['options'][0]['id'];
$wrongOption = (int) $q['options'][1]['id'];

[$status, $quiz] = callApi('PUT', '/admin/quizzes/' . $id, [
    'publication_status' => 'PUBLISHED',
], $token);
demand($status === 200 && $quiz['publication_status'] === 'PUBLISHED', 'Teste publicado com validação');

[$status, $list] = callApi('GET', '/quizzes');
demand($status === 200 && count($list) === 1 && (int) $list[0]['id'] === $id,
    'Catálogo mostra apenas teste publicado');

[$status, $public, $raw] = callApi('GET', '/quizzes/' . $id);
demand($status === 200 && count($public['questions']) === 1, 'Aluno recebe questão publicada');
demand((int) $public['questions'][0]['image_media_id'] === $imageId &&
    (int) $public['questions'][0]['options'][1]['image_media_id'] === $imageId,
    'Mídias de enunciados e alternativas estão disponíveis');
[$status] = callApi('GET', '/site/media/' . $imageId);
demand($status === 200, 'Mídia de prova publicada torna-se acessível');
demand(!isset($public['questions'][0]['options'][0]['is_correct']) &&
    !isset($public['questions'][0]['explanation']) &&
    !str_contains($raw, 'correct_option_id'), 'API pública não revela gabarito');

[$status] = callApi('POST', '/quizzes/' . $id . '/attempts', ['answers' => []]);
demand($status === 422, 'Teste incompleto não pode ser finalizado');

[$status] = callApi('POST', '/quizzes/' . $id . '/attempts', [
    'answers' => [['question_id' => $qid, 'option_id' => 999999]],
]);
demand($status === 422, 'Alternativa de outra questão ou inexistente rejeitada');

[$status, $result] = callApi('POST', '/quizzes/' . $id . '/attempts', [
    'answers' => [['question_id' => $qid, 'option_id' => $wrongOption]],
]);
demand($status === 201 && $result['correct'] === 0 &&
    (int) $result['score_percent'] === 0 &&
    (int) $result['feedback'][0]['correct_option_id'] === $correctOption,
    'Gabarito entregue somente após finalização');
demand(isset($result['attempt_id']) &&
    str_contains((string) $result['feedback'][0]['explanation'], 'Microfones'),
    'Resultado e explicação persistidos');

[$status] = callApi('PUT', '/admin/quizzes/' . $id . '/questions/' . $qid, [
    'prompt_text' => 'Alteração proibida após tentativas',
    'options' => [
        ['option_text' => 'Sim', 'is_correct' => true],
        ['option_text' => 'Não', 'is_correct' => false],
    ],
], $token);
demand($status === 422, 'Alteração proibida em teste publicado');

[$status] = callApi('PUT', '/admin/quizzes/' . $id, [
    'publication_status' => 'DRAFT',
], $token);
demand($status === 200, 'Administrador pode retirar teste de publicação');

[$status] = callApi('PUT', '/admin/quizzes/' . $id . '/questions/' . $qid, [
    'prompt_text' => 'Alteração após tentativas proibida',
    'options' => [
        ['option_text' => 'Sim', 'is_correct' => true],
        ['option_text' => 'Não', 'is_correct' => false],
    ],
], $token);
demand($status === 422, 'Questões com tentativas não podem ser alteradas nem no rascunho');

[$status] = callApi('GET', '/quizzes/' . $id);
demand($status === 404, 'Teste retirado da publicação não aparece ao aluno');
[$status] = callApi('GET', '/site/media/' . $imageId);
demand($status === 404, 'Mídia deixa de ser pública após despublicar');
echo "Fluxo completo de questionários, proteção de respostas e correção aprovados.\n";
