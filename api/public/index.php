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
use Psr\Http\Message\UploadedFileInterface;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$allowedOrigin = (string) ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173');
$app->add(static function (Request $request, RequestHandlerInterface $handler) use ($allowedOrigin): ResponseInterface {
    $response = strtoupper($request->getMethod()) === 'OPTIONS' ? new Response() : $handler->handle($request);
    $response = $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->withHeader('Vary', 'Origin')
        ->withHeader('X-Content-Type-Options', 'nosniff');

    if (str_starts_with($request->getUri()->getPath(), '/api/admin/')) {
        $response = $response->withHeader('Cache-Control', 'no-store');
    }
    return $response;
});

$app->get('/api/health', static function (Request $request, ResponseInterface $response): ResponseInterface {
    return Http::json($response, ['status' => 'ok', 'service' => 'plataforma-soni-api', 'version' => '0.5.0']);
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

$app->options('/{routes:.+}', static fn (Request $request, ResponseInterface $response): ResponseInterface => $response);
$app->addErrorMiddleware(($_ENV['APP_ENV'] ?? '') === 'development', true, true);
$app->run();
