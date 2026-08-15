<?php

declare(strict_types=1);

use CodeIgniter\HTTP\Response as CodeIgniterResponse;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Http\Request as LaravelRequest;
use Nyholm\Psr7\Response as Psr7Response;
use Prototype\RealtimeAuthorization\AuthenticatedPrincipal;
use Prototype\RealtimeAuthorization\AuthorizationDecision;
use Prototype\RealtimeAuthorization\AuthoritativePrincipalStore;
use Prototype\RealtimeAuthorization\CurrentPrincipalProvider;
use Prototype\RealtimeAuthorization\MercureAccessTokenIssuer;
use Prototype\RealtimeAuthorization\MercureCredential;
use Prototype\RealtimeAuthorization\MercureSubscriptionAction;
use Prototype\RealtimeAuthorization\NativeAuthentication;
use Prototype\RealtimeAuthorization\RealtimeSubscriptionAuthorizer;
use Psr\Http\Message\ResponseInterface;
use Pusher\Pusher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use const Prototype\RealtimeAuthorization\MERCURE_COOKIE;
use const Prototype\RealtimeAuthorization\MERCURE_HUB;
use const Prototype\RealtimeAuthorization\REVERB_CHANNEL;
use const Prototype\RealtimeAuthorization\USERS_TOPIC;

use function Prototype\RealtimeAuthorization\expect;
use function Prototype\RealtimeAuthorization\lockedVersions;
use function Prototype\RealtimeAuthorization\mercureCookie;
use function Prototype\RealtimeAuthorization\writeReceipt;

require __DIR__ . '/vendor/autoload.php';

final class PrototypeCodeIgniterResponse extends CodeIgniterResponse
{
    public function __construct() {}
}

/** @return array{status: int, set_cookie: string, body: string} */
function inspectSymfony(SymfonyResponse $response): array
{
    return [
        'status' => $response->getStatusCode(),
        'set_cookie' => $response->headers->getCookies() === [] ? '' : (string) $response->headers->getCookies()[0],
        'body' => (string) $response->getContent(),
    ];
}

/** @return array{status: int, set_cookie: string, body: string} */
function inspectPsr(ResponseInterface $response): array
{
    return [
        'status' => $response->getStatusCode(),
        'set_cookie' => $response->getHeaderLine('Set-Cookie'),
        'body' => (string) $response->getBody(),
    ];
}

/** @return array{status: int, set_cookie: string, body: string} */
function inspectCodeIgniter(CodeIgniterResponse $response): array
{
    return [
        'status' => $response->getStatusCode(),
        'set_cookie' => $response->getHeaderLine('Set-Cookie'),
        'body' => (string) $response->getBody(),
    ];
}

/** @return array{status: int, set_cookie: string, body: string} */
function mapMercureCredential(MercureCredential $credential, string $framework): array
{
    $status = match ($credential->decision) {
        AuthorizationDecision::AUTHORIZED => 204,
        AuthorizationDecision::UNAUTHENTICATED => 401,
        AuthorizationDecision::FORBIDDEN => 403,
    };
    $cookie = $credential->accessToken === null ? '' : mercureCookie($credential->accessToken);
    $body = $status === 204 ? '' : json_encode([
        'status' => 'fail',
        'data' => ['code' => $credential->decision->value],
    ], JSON_THROW_ON_ERROR);

    return match ($framework) {
        'symfony' => (static function () use ($status, $cookie, $body): array {
            $response = new SymfonyResponse($body, $status, $body === '' ? [] : ['Content-Type' => 'application/json']);
            if ($cookie !== '') {
                $response->headers->setCookie(Cookie::fromString($cookie));
            }
            return inspectSymfony($response);
        })(),
        'yii', 'slim' => (static function () use ($status, $cookie, $body): array {
            $headers = $body === '' ? [] : ['Content-Type' => 'application/json'];
            if ($cookie !== '') {
                $headers['Set-Cookie'] = $cookie;
            }
            return inspectPsr(new Psr7Response($status, $headers, $body));
        })(),
        'codeigniter' => (static function () use ($status, $cookie, $body): array {
            $response = (new PrototypeCodeIgniterResponse())->setStatusCode($status)->setBody($body);
            if ($body !== '') {
                $response = $response->setHeader('Content-Type', 'application/json');
            }
            if ($cookie !== '') {
                $response = $response->setHeader('Set-Cookie', $cookie);
            }
            return inspectCodeIgniter($response);
        })(),
        default => throw new LogicException('Unknown Mercure framework lane'),
    };
}

/** @param array<string, string> $packages */
function runMercureLane(string $framework, array $packages): void
{
    $native = new NativeAuthentication('user-1', 'session-1', 7);
    $store = new AuthoritativePrincipalStore();
    $provider = new CurrentPrincipalProvider(static fn (): NativeAuthentication => $native, $store);
    $issuer = new MercureAccessTokenIssuer('prototype-mercure-key-at-least-32-bytes', 1_786_686_000);
    $action = new MercureSubscriptionAction(new RealtimeSubscriptionAuthorizer(), $issuer);

    $authorized = $action->handle($provider, USERS_TOPIC);
    $success = mapMercureCredential($authorized, $framework);
    expect($success['status'] === 204, "$framework must authorize the users topic");
    expect(str_contains($success['set_cookie'], MERCURE_COOKIE . '='), "$framework must set the Mercure 1.0 cookie");
    $serializedCookie = strtolower($success['set_cookie']);
    foreach (['path=/.well-known/mercure', 'secure', 'httponly', 'samesite=strict'] as $attribute) {
        expect(str_contains($serializedCookie, $attribute), "$framework cookie missing $attribute");
    }

    $claims = $issuer->verify((string) $authorized->accessToken);
    expect($claims['aud'] === MERCURE_HUB, "$framework token audience mismatch");
    expect($claims['exp'] - $claims['iat'] === 60, "$framework token lifetime must be bounded to 60 seconds");
    expect($claims['authorization_details'][0] === [
        'type' => 'https://mercure.rocks/authorization-detail',
        'actions' => ['subscribe'],
        'topics' => [['match' => USERS_TOPIC]],
    ], "$framework token must allow only the exact requested topic");

    $forbidden = mapMercureCredential($action->handle($provider, USERS_TOPIC . '/secret'), $framework);
    expect($forbidden['status'] === 403 && $forbidden['set_cookie'] === '', "$framework must deny an unapproved topic");

    $anonymousProvider = new CurrentPrincipalProvider(static fn (): null => null, new AuthoritativePrincipalStore());
    $anonymous = mapMercureCredential($action->handle($anonymousProvider, USERS_TOPIC), $framework);
    expect($anonymous['status'] === 401 && $anonymous['set_cookie'] === '', "$framework must deny anonymous renewal");

    $store->revokeSession();
    $renewal = mapMercureCredential($action->handle($provider, USERS_TOPIC), $framework);
    expect($renewal['status'] === 401 && $renewal['set_cookie'] === '', "$framework must deny renewal after revocation");
    $issuer->verify((string) $authorized->accessToken);

    writeReceipt($framework, [
        'prototype' => 'WF-017 realtime subscription authorization',
        'framework' => $framework,
        'packages' => $packages,
        'transport' => 'Mercure 1.0 OAuth 2 exact-match subscription',
        'delivery' => 'same-origin reverse-proxied hub with Secure HttpOnly cookie',
        'route' => 'POST /api/v1/realtime/subscription',
        'topic' => USERS_TOPIC,
        'scenarios' => [
            'authorized' => ['status' => 204, 'cookie' => 'secure_http_only_same_site_strict'],
            'unapproved_topic' => ['status' => 403, 'credential_issued' => false],
            'anonymous' => ['status' => 401, 'credential_issued' => false],
            'renewal_after_revocation' => ['status' => 401, 'credential_issued' => false],
        ],
        'existing_connection_boundary' => 'revocation blocks renewal immediately; an already-issued credential remains valid for at most 60 seconds',
        'result' => 'passed',
    ]);
}

/** @param array<string, string> $packages */
function runLaravelLane(array $packages): void
{
    $native = new NativeAuthentication('user-1', 'session-1', 7);
    $store = new AuthoritativePrincipalStore();
    $provider = new CurrentPrincipalProvider(static fn (): NativeAuthentication => $native, $store);
    $authorizer = new RealtimeSubscriptionAuthorizer();
    $pusher = new Pusher('prototype-key', 'prototype-secret', 'prototype-app', ['useTLS' => false]);
    $broadcaster = new PusherBroadcaster($pusher);
    $broadcaster->channel('users.page', static fn (AuthenticatedPrincipal $principal): bool => $principal->may('LIST_USERS'));

    $authorize = static function () use ($authorizer, $provider, $broadcaster): array {
        $result = $authorizer->authorize($provider, USERS_TOPIC);
        if ($result->decision !== AuthorizationDecision::AUTHORIZED || $result->principal === null) {
            return ['status' => $result->decision === AuthorizationDecision::UNAUTHENTICATED ? 401 : 403, 'body' => []];
        }

        $request = LaravelRequest::create('/broadcasting/auth', 'POST', [
            'channel_name' => REVERB_CHANNEL,
            'socket_id' => '1234.5678',
        ]);
        $request->setUserResolver(static fn (): AuthenticatedPrincipal => $result->principal);

        try {
            return ['status' => 200, 'body' => $broadcaster->auth($request)];
        } catch (AccessDeniedHttpException) {
            return ['status' => 403, 'body' => []];
        }
    };

    $success = $authorize();
    $expectedSignature = hash_hmac('sha256', '1234.5678:' . REVERB_CHANNEL, 'prototype-secret');
    expect($success === ['status' => 200, 'body' => ['auth' => 'prototype-key:' . $expectedSignature]], 'Laravel native Reverb authorization mismatch');

    $wrongTopic = $authorizer->authorize($provider, USERS_TOPIC . '/secret');
    expect($wrongTopic->decision === AuthorizationDecision::FORBIDDEN, 'Laravel must deny an unapproved topic');

    $anonymous = $authorizer->authorize(
        new CurrentPrincipalProvider(static fn (): null => null, new AuthoritativePrincipalStore()),
        USERS_TOPIC,
    );
    expect($anonymous->decision === AuthorizationDecision::UNAUTHENTICATED, 'Laravel must deny anonymous authorization');

    $store->revokeSession();
    $renewal = $authorize();
    expect($renewal === ['status' => 401, 'body' => []], 'Laravel must deny channel authorization after revocation');

    writeReceipt('laravel', [
        'prototype' => 'WF-017 realtime subscription authorization',
        'framework' => 'laravel',
        'packages' => $packages,
        'transport' => 'Laravel Reverb private channel using native Pusher protocol authorization',
        'delivery' => 'native /broadcasting/auth JSON signature',
        'route' => 'POST /broadcasting/auth',
        'channel' => REVERB_CHANNEL,
        'scenarios' => [
            'authorized' => ['status' => 200, 'native_signature_verified' => true],
            'unapproved_topic' => ['status' => 403, 'credential_issued' => false],
            'anonymous' => ['status' => 401, 'credential_issued' => false],
            'authorization_after_revocation' => ['status' => 401, 'credential_issued' => false],
        ],
        'existing_connection_boundary' => 'revocation blocks the next private-channel authorization; disconnecting an already-open socket requires a separate server-side termination or bounded reconnect policy',
        'result' => 'passed',
    ]);
}

$versions = lockedVersions();
runMercureLane('symfony', ['symfony/http-foundation' => $versions['symfony/http-foundation']]);
runLaravelLane([
    'illuminate/broadcasting' => $versions['illuminate/broadcasting'],
    'pusher/pusher-php-server' => $versions['pusher/pusher-php-server'],
]);
runMercureLane('yii', ['nyholm/psr7' => $versions['nyholm/psr7']]);
runMercureLane('codeigniter', ['codeigniter4/framework' => $versions['codeigniter4/framework']]);
runMercureLane('slim', ['nyholm/psr7' => $versions['nyholm/psr7']]);

fwrite(STDOUT, "WF-017 realtime authorization prototype passed for Symfony, Laravel, Yii, CodeIgniter, and Slim.\n");
