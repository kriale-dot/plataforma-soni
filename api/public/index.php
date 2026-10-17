<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Soni\AdminAuth;
use Soni\Http;
use Soni\Database;
use Soni\SiteRepository;
use Soni\SiteCms;
use Soni\SiteMedia;
use Soni\MediaLibrary;
use Soni\QuizService;
use Soni\QuizFeedback;
use Soni\DocumentService;
use Soni\VideoService;
use Psr\Http\Message\UploadedFileInterface;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$allowedOrigins = array_values(array_filter([
    rtrim((string) ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173'), '/'),
    rtrim((string) ($_ENV['FRONTEND_ALT_URL'] ?? ''), '/'),
], static fn (string $url): bool => $url !== ''));
$app->add(static function (Request $request, RequestHandlerInterface $handler) use ($allowedOrigins): ResponseInterface {
    $response = strtoupper($request->getMethod()) === 'OPTIONS' ? new Response() : $handler->handle($request);
    $origin = $request->getHeaderLine('Origin');
    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
    }
    $response = $response
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Vary', 'Origin')
        ->withHeader('X-Content-Type-Options', 'nosniff');

    if (str_starts_with($request->getUri()->getPath(), '/api/admin/')) {
        $response = $response->withHeader('Cache-Control', 'no-store');
    }
    return $response;
});

$app->get('/api/health', static function (Request $request, ResponseInterface $response): ResponseInterface {
    return Http::json($response, ['status' => 'ok', 'service' => 'plataforma-soni-api', 'version' => '0.9.0']);
});

// Configurações e blocos públicos; não retornar rascunhos ou blocos ocultos.
$app->get('/api/site', static function (Request $request, ResponseInterface $response): ResponseInterface {
    try {
        return Http::json($response, SiteRepository::readPublic(Database::connect()));
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Site institucional temporariamente indisponível.'], 503);
    }
});

// Alguns navegadores buscam este ícone automaticamente.
$app->get('/favicon.ico', static function (Request $request, ResponseInterface $response): ResponseInterface {
    return $response->withStatus(204);
});

$app->post('/api/admin/login', static function (Request $request, ResponseInterface $response): ResponseInterface {
    if (!AdminAuth::configured()) {
        return Http::json($response, ['error' => 'Autenticação não configurada no servidor.'], 503);
    }

    $data = $request->getParsedBody();
    if (!is_array($data) || !is_string($data['email'] ?? null) || !is_string($data['password'] ?? null)) {
        return Http::json($response, ['error' => 'Informe email e password.'], 422);
    }

    $token = AdminAuth::login($data['email'], $data['password']);
    if ($token === null) {
        return Http::json($response, ['error' => 'Credenciais inválidas.'], 401);
    }

    return Http::json($response, ['token' => $token, 'token_type' => 'Bearer', 'expires_in' => 3600]);
});

$app->get('/api/admin/me', static function (Request $request, ResponseInterface $response): ResponseInterface {
    if (!AdminAuth::isValidBearer($request->getHeaderLine('Authorization'))) {
        return Http::json($response, ['error' => 'Não autorizado.'], 401);
    }
    return Http::json($response, ['role' => 'admin']);
});

// Rotas do editor institucional (exigem JWT administrativo, sem acesso anônimo).
$adminSite = static function (Request $request, ResponseInterface $response, callable $action): ResponseInterface {
    if (!AdminAuth::isValidBearer($request->getHeaderLine('Authorization'))) {
        return Http::json($response, ['error' => 'Não autorizado.'], 401);
    }
    try {
        return Http::json($response, $action(Database::connect()));
    } catch (InvalidArgumentException $error) {
        return Http::json($response, ['error' => $error->getMessage()], 422);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Falha ao consultar ou salvar o site.'], 503);
    }
};

$app->get('/api/admin/site', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => SiteCms::readAdmin($db));
});

$app->put('/api/admin/site/settings', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array =>
        SiteCms::saveSettings($db, (array) $request->getParsedBody()));
});

$app->post('/api/admin/site/blocks', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array =>
        SiteCms::create($db, (array) $request->getParsedBody()));
});

$app->put('/api/admin/site/blocks/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array =>
        SiteCms::update($db, (int) $args['id'], (array) $request->getParsedBody()));
});

$app->patch('/api/admin/site/blocks/{id:[0-9]+}/publication', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array =>
        SiteCms::update($db, (int) $args['id'], (array) $request->getParsedBody()));
});

$app->patch('/api/admin/site/blocks/{id:[0-9]+}/order', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array =>
        SiteCms::move($db, (int) $args['id'], (string) (($request->getParsedBody()['direction'] ?? ''))));
});

$app->delete('/api/admin/site/blocks/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array =>
        SiteCms::delete($db, (int) $args['id']));
});

// A mídia privada (rascunhos e arquivos não vinculados) só é entregue ao administrador.
// A rota pública só permite mídias relacionadas a blocos visíveis e publicados.
$serveSiteMedia = static function (Request $request, ResponseInterface $response, int $id, bool $admin): ResponseInterface {
    if ($admin && !AdminAuth::isValidBearer($request->getHeaderLine('Authorization'))) {
        return Http::json($response, ['error' => 'Não autorizado.'], 401);
    }
    try {
        return SiteMedia::serve(Database::connect(), $id, $response, $request, $admin);
    } catch (InvalidArgumentException) {
        return Http::json($response, ['error' => 'Mídia não encontrada.'], 404);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Mídia temporariamente indisponível.'], 503);
    }
};

$app->get('/api/site/media/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($serveSiteMedia): ResponseInterface {
    return $serveSiteMedia($request, $response, (int) $args['id'], false);
});

$app->get('/api/admin/site/media/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($serveSiteMedia): ResponseInterface {
    return $serveSiteMedia($request, $response, (int) $args['id'], true);
});

$app->post('/api/admin/site/media', static function (Request $request, ResponseInterface $response): ResponseInterface {
    if (!AdminAuth::isValidBearer($request->getHeaderLine('Authorization'))) {
        return Http::json($response, ['error' => 'Não autorizado.'], 401);
    }
    try {
        $file = $request->getUploadedFiles()['file'] ?? null;
        $uploaded = $file instanceof UploadedFileInterface ? $file : null;
        return Http::json($response, SiteMedia::upload(Database::connect(), $uploaded), 201);
    } catch (LengthException $error) {
        return Http::json($response, ['error' => $error->getMessage()], 413);
    } catch (InvalidArgumentException $error) {
        return Http::json($response, ['error' => $error->getMessage()], 422);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Não foi possível enviar o arquivo.'], 503);
    }
});

// Biblioteca central de mídias: acesso e exclusão restritos a JWT administrativo.
$app->get('/api/admin/media', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    $query = $request->getQueryParams();
    return $adminSite($request, $response, static fn (\PDO $db): array => MediaLibrary::list(
        $db, (int) ($query['offset'] ?? 0), (int) ($query['limit'] ?? 50)
    ));
});

$app->delete('/api/admin/media/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => MediaLibrary::delete(
        $db, (int) $args['id'], (string) (($request->getParsedBody()['confirmation'] ?? ''))
    ));
});

// Área pública de provas: não inclui gabarito, explicação nem indicador is_correct.
// Somente ao finalizar TODAS as questões o resultado e o gabarito são retornados.
$app->get('/api/quizzes', static function (Request $request, ResponseInterface $response): ResponseInterface {
    try {
        return Http::json($response, QuizService::listPublic(Database::connect()));
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Não foi possível listar os testes.'], 503);
    }
});

$app->get('/api/quizzes/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args): ResponseInterface {
    try {
        return Http::json($response, QuizService::publicDetail(Database::connect(), (int) $args['id']));
    } catch (InvalidArgumentException) {
        return Http::json($response, ['error' => 'Teste não encontrado ou não publicado.'], 404);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Teste temporariamente indisponível.'], 503);
    }
});

$app->post('/api/quizzes/{id:[0-9]+}/attempts', static function (Request $request, ResponseInterface $response, array $args): ResponseInterface {
    try {
        $body = $request->getParsedBody();
        if (!is_array($body)) throw new InvalidArgumentException('Formato de respostas inválido.');
        return Http::json($response, QuizService::submit(Database::connect(), (int) $args['id'], $body), 201);
    } catch (InvalidArgumentException $error) {
        return Http::json($response, ['error' => $error->getMessage()], 422);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Não foi possível finalizar o teste.'], 503);
    }
});

// Gestão de categorias, testes e questões, sempre com a autenticação JWT do administrador.
$app->get('/api/admin/quizzes', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::adminList($db));
});
$app->post('/api/admin/quiz-categories', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::saveCategory($db, null, (array) $request->getParsedBody()));
});
$app->put('/api/admin/quiz-categories/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::saveCategory($db, (int) $args['id'], (array) $request->getParsedBody()));
});
$app->post('/api/admin/quizzes', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::createQuiz($db, (array) $request->getParsedBody()));
});
$app->get('/api/admin/quizzes/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::adminDetail($db, (int) $args['id']));
});
$app->put('/api/admin/quizzes/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::updateQuiz($db, (int) $args['id'], (array) $request->getParsedBody()));
});
$app->delete('/api/admin/quizzes/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::deleteQuiz($db, (int) $args['id']));
});

// Atualizar as faixas sem afetar o histórico das tentativas já finalizadas.
$app->put('/api/admin/quizzes/{id:[0-9]+}/feedback-ranges', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array =>
        QuizFeedback::replace($db, (int) $args['id'], (array) $request->getParsedBody()));
});

$app->post('/api/admin/quizzes/{id:[0-9]+}/questions', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::saveQuestion($db, (int) $args['id'], null, (array) $request->getParsedBody()));
});
$app->put('/api/admin/quizzes/{id:[0-9]+}/questions/{questionId:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::saveQuestion($db, (int) $args['id'], (int) $args['questionId'], (array) $request->getParsedBody()));
});
$app->delete('/api/admin/quizzes/{id:[0-9]+}/questions/{questionId:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => QuizService::deleteQuestion(
        $db, (int) $args['id'], (int) $args['questionId'],
        (string) ((is_array($request->getParsedBody()) ? $request->getParsedBody() : [])['confirmation'] ?? '')
    ));
});

// Biblioteca pública: catálogo e consulta de documentos publicados (PDF/PNG).
$app->get('/api/documents', static function (Request $request, ResponseInterface $response): ResponseInterface {
    try {
        return Http::json($response, DocumentService::publicList(Database::connect(), $request->getQueryParams()));
    } catch (InvalidArgumentException $error) {
        return Http::json($response, ['error' => $error->getMessage()], 422);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Biblioteca de documentos temporariamente indisponível.'], 503);
    }
});
$app->get('/api/documents/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args): ResponseInterface {
    try {
        return Http::json($response, DocumentService::publicDetail(Database::connect(), (int) $args['id']));
    } catch (InvalidArgumentException) {
        return Http::json($response, ['error' => 'Documento não encontrado ou não publicado.'], 404);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Documento temporariamente indisponível.'], 503);
    }
});

// Gestão com JWT — permite guardar rascunhos e ocultar documentos.
$app->get('/api/admin/documents', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => DocumentService::adminList($db));
});
$app->post('/api/admin/document-categories', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => DocumentService::saveCategory($db, null, (array) $request->getParsedBody()));
});
$app->put('/api/admin/document-categories/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => DocumentService::saveCategory($db, (int) $args['id'], (array) $request->getParsedBody()));
});
$app->post('/api/admin/documents', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => DocumentService::create($db, (array) $request->getParsedBody()));
});
$app->put('/api/admin/documents/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => DocumentService::update($db, (int) $args['id'], (array) $request->getParsedBody()));
});
$app->delete('/api/admin/documents/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => DocumentService::delete($db, (int) $args['id']));
});

// Biblioteca pública de vídeos: somente publicados e visíveis.
$app->get('/api/videos', static function (Request $request, ResponseInterface $response): ResponseInterface {
    try {
        return Http::json($response, VideoService::listPublic(Database::connect(), $request->getQueryParams()));
    } catch (InvalidArgumentException $error) {
        return Http::json($response, ['error' => $error->getMessage()], 422);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Biblioteca de vídeos temporariamente indisponível.'], 503);
    }
});
$app->get('/api/videos/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args): ResponseInterface {
    try {
        return Http::json($response, VideoService::getPublic(Database::connect(), (int) $args['id']));
    } catch (InvalidArgumentException) {
        return Http::json($response, ['error' => 'Vídeo não encontrado ou não publicado.'], 404);
    } catch (Throwable) {
        return Http::json($response, ['error' => 'Vídeo temporariamente indisponível.'], 503);
    }
});

// Administração autenticada JWT para categorias e vídeos.
$app->get('/api/admin/videos', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => VideoService::listAdmin($db));
});
$app->post('/api/admin/video-categories', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => VideoService::saveCategory($db, null, (array) $request->getParsedBody()));
});
$app->put('/api/admin/video-categories/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => VideoService::saveCategory($db, (int) $args['id'], (array) $request->getParsedBody()));
});
$app->post('/api/admin/videos', static function (Request $request, ResponseInterface $response) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => VideoService::create($db, (array) $request->getParsedBody()));
});
$app->put('/api/admin/videos/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => VideoService::update($db, (int) $args['id'], (array) $request->getParsedBody()));
});
$app->delete('/api/admin/videos/{id:[0-9]+}', static function (Request $request, ResponseInterface $response, array $args) use ($adminSite): ResponseInterface {
    return $adminSite($request, $response, static fn (\PDO $db): array => VideoService::delete($db, (int) $args['id']));
});

$app->options('/{routes:.+}', static fn (Request $request, ResponseInterface $response): ResponseInterface => $response);
$app->addErrorMiddleware(($_ENV['APP_ENV'] ?? '') === 'development', true, true);
$app->run();
