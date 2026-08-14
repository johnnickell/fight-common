<?php

declare(strict_types=1);

use CodeIgniter\HTTP\Response as CodeIgniterResponse;
use Illuminate\Http\JsonResponse as LaravelJsonResponse;
use Nyholm\Psr7\Response as Psr7Response;
use Prototype\HttpAction\ListUsersDecision;
use Prototype\HttpAction\ListUsersOutcome;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

use function Prototype\HttpAction\runLane;

require __DIR__ . '/vendor/autoload.php';

final class PrototypeCodeIgniterResponse extends CodeIgniterResponse
{
    /** The framework constructor needs application services; the action only exercises its native message API. */
    public function __construct() {}
}

/** @return array{class: string, status: int, content_type: string, body: array<string, mixed>} */
function inspectSymfonyResponse(SymfonyJsonResponse $response): array
{
    return [
        'class' => $response::class,
        'status' => $response->getStatusCode(),
        'content_type' => (string) $response->headers->get('Content-Type'),
        'body' => json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR),
    ];
}

/** @return array{class: string, status: int, content_type: string, body: array<string, mixed>} */
function inspectPsr7Response(ResponseInterface $response): array
{
    return [
        'class' => $response::class,
        'status' => $response->getStatusCode(),
        'content_type' => $response->getHeaderLine('Content-Type'),
        'body' => json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
    ];
}

/** @return array{class: string, status: int, content_type: string, body: array<string, mixed>} */
function inspectCodeIgniterResponse(CodeIgniterResponse $response): array
{
    return [
        'class' => $response::class,
        'status' => $response->getStatusCode(),
        'content_type' => $response->getHeaderLine('Content-Type'),
        'body' => json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
    ];
}

$locked = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$versions = [];
foreach ($locked['packages'] as $package) {
    $versions[$package['name']] = $package['version'];
}

runLane('Symfony', ['symfony/http-foundation' => $versions['symfony/http-foundation']], [
    'route' => 'GET /api/v1/access/users -> invokable controller',
    'response' => SymfonyJsonResponse::class,
    'jsend' => 'starter-owned response mapper; Fight Common JSendResponse remains optional Symfony convenience',
], static function (ListUsersOutcome $outcome): array {
    [$status, $body] = match ($outcome->decision) {
        ListUsersDecision::Authorized => [200, ['status' => 'success', 'data' => ['users' => $outcome->users]]],
        ListUsersDecision::Unauthenticated => [401, ['status' => 'fail', 'data' => ['code' => 'authentication_required']]],
        ListUsersDecision::Forbidden => [403, ['status' => 'fail', 'data' => ['code' => 'forbidden']]],
    };

    return inspectSymfonyResponse(new SymfonyJsonResponse($body, $status));
});

runLane('Laravel', ['illuminate/http' => $versions['illuminate/http']], [
    'route' => 'Route::get(/api/v1/access/users, invokable controller)',
    'response' => LaravelJsonResponse::class,
    'jsend' => 'starter-owned response mapper',
], static function (ListUsersOutcome $outcome): array {
    [$status, $body] = match ($outcome->decision) {
        ListUsersDecision::Authorized => [200, ['status' => 'success', 'data' => ['users' => $outcome->users]]],
        ListUsersDecision::Unauthenticated => [401, ['status' => 'fail', 'data' => ['code' => 'authentication_required']]],
        ListUsersDecision::Forbidden => [403, ['status' => 'fail', 'data' => ['code' => 'forbidden']]],
    };

    return inspectSymfonyResponse(new LaravelJsonResponse($body, $status));
});

runLane('Yii', ['yiisoft/http' => $versions['yiisoft/http'], 'nyholm/psr7' => $versions['nyholm/psr7']], [
    'route' => 'Yiisoft Router GET route action',
    'response' => ResponseInterface::class . ' backed by Nyholm PSR-7',
    'jsend' => 'starter-owned response mapper',
], static function (ListUsersOutcome $outcome): array {
    [$status, $body] = match ($outcome->decision) {
        ListUsersDecision::Authorized => [200, ['status' => 'success', 'data' => ['users' => $outcome->users]]],
        ListUsersDecision::Unauthenticated => [401, ['status' => 'fail', 'data' => ['code' => 'authentication_required']]],
        ListUsersDecision::Forbidden => [403, ['status' => 'fail', 'data' => ['code' => 'forbidden']]],
    };

    return inspectPsr7Response(new Psr7Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    ));
});

runLane('CodeIgniter', ['codeigniter4/framework' => $versions['codeigniter4/framework']], [
    'route' => 'Routes::get(api/v1/access/users, invokable controller)',
    'response' => CodeIgniterResponse::class,
    'jsend' => 'starter-owned response mapper using native response message API',
], static function (ListUsersOutcome $outcome): array {
    [$status, $body] = match ($outcome->decision) {
        ListUsersDecision::Authorized => [200, ['status' => 'success', 'data' => ['users' => $outcome->users]]],
        ListUsersDecision::Unauthenticated => [401, ['status' => 'fail', 'data' => ['code' => 'authentication_required']]],
        ListUsersDecision::Forbidden => [403, ['status' => 'fail', 'data' => ['code' => 'forbidden']]],
    };
    $response = (new PrototypeCodeIgniterResponse())
        ->setStatusCode($status)
        ->setHeader('Content-Type', 'application/json')
        ->setBody(json_encode($body, JSON_THROW_ON_ERROR));

    return inspectCodeIgniterResponse($response);
});

runLane('Slim', ['nyholm/psr7' => $versions['nyholm/psr7']], [
    'route' => 'Slim App::get(/api/v1/access/users, route action)',
    'response' => ResponseInterface::class,
    'jsend' => 'starter-owned PSR-7 response mapper',
], static function (ListUsersOutcome $outcome): array {
    [$status, $body] = match ($outcome->decision) {
        ListUsersDecision::Authorized => [200, ['status' => 'success', 'data' => ['users' => $outcome->users]]],
        ListUsersDecision::Unauthenticated => [401, ['status' => 'fail', 'data' => ['code' => 'authentication_required']]],
        ListUsersDecision::Forbidden => [403, ['status' => 'fail', 'data' => ['code' => 'forbidden']]],
    };
    $response = new Psr7Response();
    $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));

    return inspectPsr7Response($response->withStatus($status)->withHeader('Content-Type', 'application/json'));
});
