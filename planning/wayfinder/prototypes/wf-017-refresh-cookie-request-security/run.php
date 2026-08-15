<?php

declare(strict_types=1);

use CodeIgniter\HTTP\Request as CodeIgniterRequest;
use Illuminate\Http\Request as LaravelRequest;
use Nyholm\Psr7\ServerRequest;
use Prototype\RefreshCookieSecurity\RefreshCookieRequestGuard;
use Prototype\RefreshCookieSecurity\RequestDecision;
use Prototype\RefreshCookieSecurity\RequestView;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

use function Prototype\RefreshCookieSecurity\expect;
use function Prototype\RefreshCookieSecurity\expiredRefreshCookie;
use function Prototype\RefreshCookieSecurity\lockedVersions;
use function Prototype\RefreshCookieSecurity\refreshCookie;
use function Prototype\RefreshCookieSecurity\writeReceipt;

use const Prototype\RefreshCookieSecurity\APPLICATION_ORIGIN;
use const Prototype\RefreshCookieSecurity\REFRESH_COOKIE;

require __DIR__ . '/vendor/autoload.php';

/** @return array<string, string> */
function scenarioHeaders(string $name): array
{
    $base = [
        'Content-Type' => 'application/json',
        'Origin' => APPLICATION_ORIGIN,
        'Sec-Fetch-Site' => 'same-origin',
        'Cookie' => REFRESH_COOKIE . '=opaque-prototype-credential',
        'Host' => 'starter.example.test',
    ];

    return match ($name) {
        'same_origin' => $base,
        'origin_fallback' => array_diff_key($base, ['Sec-Fetch-Site' => true]),
        'same_site_subdomain' => array_replace($base, [
            'Origin' => 'https://untrusted.starter.example.test',
            'Sec-Fetch-Site' => 'same-site',
        ]),
        'cross_site' => array_replace($base, [
            'Origin' => 'https://attacker.example',
            'Sec-Fetch-Site' => 'cross-site',
        ]),
        'missing_origin' => array_diff_key($base, ['Origin' => true]),
        'null_origin' => array_replace($base, ['Origin' => 'null']),
        'spoofed_forwarded_host' => array_replace($base, [
            'Origin' => 'https://attacker.example',
            'X-Forwarded-Host' => 'starter.example.test',
        ]),
        'form_content_type' => array_replace($base, ['Content-Type' => 'application/x-www-form-urlencoded']),
        'missing_cookie' => array_diff_key($base, ['Cookie' => true]),
        default => throw new LogicException('Unknown scenario'),
    };
}

/** @param array<string, string> $headers */
function symfonyView(string $method, array $headers): RequestView
{
    $server = [];
    foreach ($headers as $name => $value) {
        $server[$name === 'Content-Type' ? 'CONTENT_TYPE' : 'HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }
    $request = SymfonyRequest::create(APPLICATION_ORIGIN . '/api/v1/access/session/refresh', $method, server: $server);

    return viewFromHeaderReader($request->getMethod(), static fn (string $name): string => (string) $request->headers->get($name, ''));
}

/** @param array<string, string> $headers */
function laravelView(string $method, array $headers): RequestView
{
    $server = [];
    foreach ($headers as $name => $value) {
        $server[$name === 'Content-Type' ? 'CONTENT_TYPE' : 'HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }
    $request = LaravelRequest::create(APPLICATION_ORIGIN . '/api/v1/access/session/refresh', $method, server: $server);

    return viewFromHeaderReader($request->getMethod(), static fn (string $name): string => (string) $request->headers->get($name, ''));
}

/** @param array<string, string> $headers */
function psrView(string $method, array $headers): RequestView
{
    $request = new ServerRequest($method, APPLICATION_ORIGIN . '/api/v1/access/session/refresh', $headers);

    return viewFromHeaderReader($request->getMethod(), static fn (string $name): string => $request->getHeaderLine($name));
}

/** @param array<string, string> $headers */
function codeIgniterView(string $method, array $headers): RequestView
{
    $request = (new ReflectionClass(CodeIgniterRequest::class))->newInstanceWithoutConstructor()->withMethod($method);
    foreach ($headers as $name => $value) {
        $request->setHeader($name, $value);
    }

    return viewFromHeaderReader($request->getMethod(), static fn (string $name): string => $request->getHeaderLine($name));
}

/** @param callable(string): string $readHeader */
function viewFromHeaderReader(string $method, callable $readHeader): RequestView
{
    $headers = [];
    foreach (['content-type', 'origin', 'sec-fetch-site', 'cookie', 'host', 'x-forwarded-host'] as $name) {
        $headers[$name] = $readHeader($name);
    }

    return new RequestView($method, $headers);
}

/** @return array<string, string> */
function selectedPackageVersions(string $framework, array $versions): array
{
    return match ($framework) {
        'symfony' => ['symfony/http-foundation' => $versions['symfony/http-foundation']],
        'laravel' => ['illuminate/http' => $versions['illuminate/http']],
        'yii', 'slim' => ['nyholm/psr7' => $versions['nyholm/psr7']],
        'codeigniter' => ['codeigniter4/framework' => $versions['codeigniter4/framework']],
        default => throw new LogicException('Unknown framework lane'),
    };
}

$guard = new RefreshCookieRequestGuard(APPLICATION_ORIGIN);
$versions = lockedVersions();
$lanes = [
    'symfony' => 'symfonyView',
    'laravel' => 'laravelView',
    'yii' => 'psrView',
    'codeigniter' => 'codeIgniterView',
    'slim' => 'psrView',
];
$expectations = [
    'same_origin' => [RequestDecision::Allowed, 'same_origin_cookie_request'],
    'origin_fallback' => [RequestDecision::Allowed, 'same_origin_cookie_request'],
    'same_site_subdomain' => [RequestDecision::Forbidden, 'same_origin_fetch_required'],
    'cross_site' => [RequestDecision::Forbidden, 'same_origin_fetch_required'],
    'missing_origin' => [RequestDecision::Forbidden, 'exact_origin_required'],
    'null_origin' => [RequestDecision::Forbidden, 'exact_origin_required'],
    'spoofed_forwarded_host' => [RequestDecision::Forbidden, 'exact_origin_required'],
    'form_content_type' => [RequestDecision::Forbidden, 'json_content_type_required'],
    'missing_cookie' => [RequestDecision::Unauthenticated, 'refresh_cookie_required'],
];

foreach ($lanes as $framework => $factory) {
    $scenarios = [];
    foreach ($expectations as $scenario => [$expectedDecision, $expectedReason]) {
        $view = $factory('POST', scenarioHeaders($scenario));
        $result = $guard->evaluate($view);
        expect($result->decision === $expectedDecision, "$framework/$scenario decision mismatch");
        expect($result->reason === $expectedReason, "$framework/$scenario reason mismatch");
        $scenarios[$scenario] = ['decision' => $result->decision->value, 'reason' => $result->reason];
    }

    $get = $guard->evaluate($factory('GET', scenarioHeaders('same_origin')));
    expect($get->decision === RequestDecision::MethodNotAllowed, "$framework must reject GET refresh/logout");
    $scenarios['get'] = ['decision' => $get->decision->value, 'reason' => $get->reason];

    $issuedCookie = strtolower(refreshCookie('opaque-prototype-credential'));
    foreach (['__host-fight_refresh=', 'path=/', 'secure', 'httponly', 'samesite=strict'] as $attribute) {
        expect(str_contains($issuedCookie, $attribute), "$framework issued cookie missing $attribute");
    }
    expect(!str_contains($issuedCookie, 'domain='), "$framework __Host- cookie must not declare Domain");

    $clearedCookie = strtolower(expiredRefreshCookie());
    foreach (['max-age=0', 'expires=thu, 01 jan 1970', 'path=/', 'secure', 'httponly', 'samesite=strict'] as $attribute) {
        expect(str_contains($clearedCookie, $attribute), "$framework cleared cookie missing $attribute");
    }

    writeReceipt($framework, [
        'prototype' => 'WF-017 refresh-cookie request security',
        'framework' => $framework,
        'packages' => selectedPackageVersions($framework, $versions),
        'routes' => ['POST /api/v1/access/session/refresh', 'POST /api/v1/access/session/logout'],
        'trusted_application_origin' => APPLICATION_ORIGIN,
        'target_origin_source' => 'explicit project configuration, never Host or X-Forwarded-Host',
        'cookie_profile' => '__Host- prefix; Path=/; Secure; HttpOnly; SameSite=Strict; no Domain',
        'cors_profile' => 'same-origin only; no credentialed cross-origin refresh/logout',
        'scenarios' => $scenarios,
        'result' => 'passed',
    ]);

    echo $framework . ": passed\n";
}
