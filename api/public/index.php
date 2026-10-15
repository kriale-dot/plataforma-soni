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

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$allowedOrigin = (string) ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173');
$app->add(static function (Request $request, RequestHandlerInterface $handler) use ($allowedOrigin): ResponseInterface {
    $response = strtoupper($request->getMethod()) === 'OPTIONS' ? new Response() : $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->withHeader('Vary', 'Origin');
});

$app->get('/api/health', static function (Request $request, ResponseInterface $response): ResponseInterface {
    return Http::json($response, ['status' => 'ok', 'service' => 'plataforma-soni-api', 'version' => '0.1.0']);
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

$app->options('/{routes:.+}', static fn (Request $request, ResponseInterface $response): ResponseInterface => $response);

$app->addErrorMiddleware(($_ENV['APP_ENV'] ?? '') === 'development', true, true);
$app->run();
