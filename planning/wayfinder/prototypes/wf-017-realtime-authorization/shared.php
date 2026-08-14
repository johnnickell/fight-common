<?php

declare(strict_types=1);

namespace Prototype\RealtimeAuthorization;

use RuntimeException;

const MERCURE_COOKIE = '__Secure-mercure_access_token';
const MERCURE_HUB = 'https://starter.example.test/.well-known/mercure';
const USERS_TOPIC = 'https://starter.example.test/topics/access/users';
const REVERB_CHANNEL = 'private-users.page';

final readonly class NativeAuthentication
{
    public function __construct(
        public string $userId,
        public string $sessionId,
        public int $authenticationVersion,
    ) {}
}

final readonly class AuthenticatedPrincipal
{
    /** @param list<string> $permissions */
    public function __construct(
        public string $userId,
        public string $sessionId,
        public int $authenticationVersion,
        public array $permissions,
    ) {}

    public function may(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}

final class AuthoritativePrincipalStore
{
    private bool $sessionRevoked = false;

    public function revokeSession(): void
    {
        $this->sessionRevoked = true;
    }

    public function resolve(?NativeAuthentication $authentication): ?AuthenticatedPrincipal
    {
        if ($authentication === null || $this->sessionRevoked) {
            return null;
        }

        if (
            $authentication->userId !== 'user-1'
            || $authentication->sessionId !== 'session-1'
            || $authentication->authenticationVersion !== 7
        ) {
            return null;
        }

        return new AuthenticatedPrincipal('user-1', 'session-1', 7, ['LIST_USERS']);
    }
}

final readonly class CurrentPrincipalProvider
{
    /** @param callable(): ?NativeAuthentication $nativeAuthentication */
    public function __construct(
        private mixed $nativeAuthentication,
        private AuthoritativePrincipalStore $store,
    ) {}

    public function current(): ?AuthenticatedPrincipal
    {
        return $this->store->resolve(($this->nativeAuthentication)());
    }
}

enum AuthorizationDecision: string
{
    case Authorized = 'authorized';
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
}

final readonly class AuthorizationResult
{
    public function __construct(
        public AuthorizationDecision $decision,
        public ?AuthenticatedPrincipal $principal = null,
    ) {}
}

final readonly class RealtimeSubscriptionAuthorizer
{
    public function authorize(CurrentPrincipalProvider $provider, string $topic): AuthorizationResult
    {
        $principal = $provider->current();
        if ($principal === null) {
            return new AuthorizationResult(AuthorizationDecision::Unauthenticated);
        }

        if ($topic !== USERS_TOPIC || !$principal->may('LIST_USERS')) {
            return new AuthorizationResult(AuthorizationDecision::Forbidden, $principal);
        }

        return new AuthorizationResult(AuthorizationDecision::Authorized, $principal);
    }
}

final readonly class MercureCredential
{
    public function __construct(
        public AuthorizationDecision $decision,
        public ?string $accessToken = null,
    ) {}
}

final readonly class MercureSubscriptionAction
{
    public function __construct(
        private RealtimeSubscriptionAuthorizer $authorizer,
        private MercureAccessTokenIssuer $issuer,
    ) {}

    public function handle(CurrentPrincipalProvider $provider, string $topic): MercureCredential
    {
        $result = $this->authorizer->authorize($provider, $topic);
        if ($result->decision !== AuthorizationDecision::Authorized || $result->principal === null) {
            return new MercureCredential($result->decision);
        }

        return new MercureCredential(
            AuthorizationDecision::Authorized,
            $this->issuer->issueForExactTopic($result->principal, $topic),
        );
    }
}

final readonly class MercureAccessTokenIssuer
{
    public function __construct(
        private string $key,
        private int $issuedAt,
        private int $lifetimeSeconds = 60,
    ) {}

    public function issueForExactTopic(AuthenticatedPrincipal $principal, string $topic): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'at+jwt'];
        $claims = [
            'iss' => 'https://starter.example.test',
            'aud' => MERCURE_HUB,
            'sub' => $principal->userId,
            'client_id' => 'fight-starter-browser',
            'iat' => $this->issuedAt,
            'exp' => $this->issuedAt + $this->lifetimeSeconds,
            'jti' => 'prototype-' . $principal->sessionId . '-' . $this->issuedAt,
            'authorization_details' => [[
                'type' => 'https://mercure.rocks/authorization-detail',
                'actions' => ['subscribe'],
                'topics' => [['match' => $topic]],
            ]],
        ];

        $segments = [self::encode($header), self::encode($claims)];
        $segments[] = self::base64Url(hash_hmac('sha256', implode('.', $segments), $this->key, true));

        return implode('.', $segments);
    }

    /** @return array<string, mixed> */
    public function verify(string $token): array
    {
        $segments = explode('.', $token);
        expect(count($segments) === 3, 'Mercure token must have three segments');
        [$header, $claims, $signature] = $segments;
        $expected = self::base64Url(hash_hmac('sha256', $header . '.' . $claims, $this->key, true));
        expect(hash_equals($expected, $signature), 'Mercure token signature mismatch');

        $decodedHeader = self::decode($header);
        expect($decodedHeader === ['alg' => 'HS256', 'typ' => 'at+jwt'], 'Mercure token header mismatch');

        return self::decode($claims);
    }

    /** @param array<string, mixed> $value */
    private static function encode(array $value): string
    {
        return self::base64Url(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    private static function decode(string $value): array
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $json = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        expect($json !== false, 'Invalid base64url value');

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

function mercureCookie(string $token): string
{
    return MERCURE_COOKIE . '=' . $token
        . '; Path=/.well-known/mercure; Secure; HttpOnly; SameSite=Strict';
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
