<?php

declare(strict_types=1);

namespace Prototype\RefreshCookieSecurity;

use RuntimeException;

const APPLICATION_ORIGIN = 'https://starter.example.test';
const REFRESH_COOKIE = '__Host-fight_refresh';

final readonly class RequestView
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $method,
        public array $headers,
    ) {}

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }
}

enum RequestDecision: string
{
    case Allowed = 'allowed';
    case Forbidden = 'forbidden';
    case MethodNotAllowed = 'method_not_allowed';
    case Unauthenticated = 'unauthenticated';
}

final readonly class RequestSecurityResult
{
    public function __construct(
        public RequestDecision $decision,
        public string $reason,
    ) {}
}

final readonly class RefreshCookieRequestGuard
{
    public function __construct(private string $applicationOrigin) {}

    public function evaluate(RequestView $request): RequestSecurityResult
    {
        if ($request->method !== 'POST') {
            return new RequestSecurityResult(RequestDecision::MethodNotAllowed, 'unsafe_endpoint_requires_post');
        }

        $contentType = strtolower(trim(explode(';', $request->header('content-type'), 2)[0]));
        if ($contentType !== 'application/json') {
            return new RequestSecurityResult(RequestDecision::Forbidden, 'json_content_type_required');
        }

        $fetchSite = strtolower(trim($request->header('sec-fetch-site')));
        if ($fetchSite !== '' && $fetchSite !== 'same-origin') {
            return new RequestSecurityResult(RequestDecision::Forbidden, 'same_origin_fetch_required');
        }

        if (!hash_equals($this->applicationOrigin, trim($request->header('origin')))) {
            return new RequestSecurityResult(RequestDecision::Forbidden, 'exact_origin_required');
        }

        if (!array_key_exists(REFRESH_COOKIE, parseCookies($request->header('cookie')))) {
            return new RequestSecurityResult(RequestDecision::Unauthenticated, 'refresh_cookie_required');
        }

        return new RequestSecurityResult(RequestDecision::Allowed, 'same_origin_cookie_request');
    }
}

/** @return array<string, string> */
function parseCookies(string $header): array
{
    $cookies = [];
    foreach (explode(';', $header) as $pair) {
        $parts = explode('=', trim($pair), 2);
        if (count($parts) === 2 && $parts[0] !== '') {
            $cookies[$parts[0]] = $parts[1];
        }
    }

    return $cookies;
}

function refreshCookie(string $opaqueCredential): string
{
    return REFRESH_COOKIE . '=' . rawurlencode($opaqueCredential)
        . '; Path=/; Secure; HttpOnly; SameSite=Strict';
}

function expiredRefreshCookie(): string
{
    return REFRESH_COOKIE
        . '=; Path=/; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Secure; HttpOnly; SameSite=Strict';
}

/** @param array<string, mixed> $receipt */
function writeReceipt(string $name, array $receipt): void
{
    $directory = __DIR__ . '/receipts';
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create receipt directory');
    }

    file_put_contents(
        $directory . '/' . $name . '.json',
        json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    );
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, string> */
function lockedVersions(): array
{
    $lock = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    $versions = [];
    foreach ($lock['packages'] as $package) {
        $versions[$package['name']] = $package['version'];
    }

    return $versions;
}

