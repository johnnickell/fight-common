<?php

declare(strict_types=1);

namespace Prototype\PrincipalIntegration;

use DateTimeImmutable;
use LogicException;

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
    /** @param list<string> $roles @param list<string> $permissions */
    public function __construct(
        public string $userId,
        public int $authenticationVersion,
        public array $roles,
        public array $permissions,
        public DateTimeImmutable $authenticatedAt,
    ) {}
}

interface CurrentPrincipalProvider
{
    public function current(): ?AuthenticatedPrincipal;
}

final class AuthoritativePrincipalStore
{
    /** @var array<string, array{state: string, version: int, roles: list<string>, permissions: list<string>}> */
    private array $users = [];

    /** @var array<string, array{user_id: string, revoked: bool}> */
    private array $sessions = [];

    /** @param list<string> $roles @param list<string> $permissions */
    public function putUser(string $id, string $state, int $version, array $roles, array $permissions): void
    {
        $this->users[$id] = compact('state', 'version', 'roles', 'permissions');
    }

    public function putSession(string $id, string $userId, bool $revoked = false): void
    {
        $this->sessions[$id] = ['user_id' => $userId, 'revoked' => $revoked];
    }

    public function resolve(NativeAuthentication $native): ?AuthenticatedPrincipal
    {
        $user = $this->users[$native->userId] ?? null;
        $session = $this->sessions[$native->sessionId] ?? null;

        if ($user === null || $session === null || $user['state'] !== 'ACTIVE') {
            return null;
        }
        if ($session['revoked'] || $session['user_id'] !== $native->userId) {
            return null;
        }
        if ($user['version'] !== $native->authenticationVersion) {
            return null;
        }

        return new AuthenticatedPrincipal(
            $native->userId,
            $user['version'],
            $user['roles'],
            $user['permissions'],
            new DateTimeImmutable('2026-08-14T12:00:00+00:00'),
        );
    }
}

final readonly class AdapterCurrentPrincipalProvider implements CurrentPrincipalProvider
{
    /** @param callable(): ?NativeAuthentication $nativeIdentity */
    public function __construct(
        private mixed $nativeIdentity,
        private AuthoritativePrincipalStore $store,
    ) {}

    public function current(): ?AuthenticatedPrincipal
    {
        $native = ($this->nativeIdentity)();

        return $native === null ? null : $this->store->resolve($native);
    }
}

/** Models the project service behind CodeIgniter's documented user_id() authentication convention. */
final readonly class CodeIgniterAuthenticationService
{
    public function __construct(private ?NativeAuthentication $authentication) {}

    public function userId(): ?string
    {
        return $this->authentication?->userId;
    }

    public function authentication(): ?NativeAuthentication
    {
        return $this->authentication;
    }
}

/**
 * @param callable(?NativeAuthentication): CurrentPrincipalProvider $provider
 * @param array<string, string|null> $versions
 * @param array<string, mixed> $composition
 */
function runLane(string $framework, array $versions, array $composition, callable $provider): array
{
    $valid = new NativeAuthentication('user-1', 'session-1', 7);
    $scenarios = [
        'valid' => $valid,
        'anonymous' => null,
        'missing-user' => new NativeAuthentication('missing', 'session-1', 7),
        'disabled-user' => $valid,
        'stale-authentication-version' => new NativeAuthentication('user-1', 'session-1', 6),
        'revoked-session' => $valid,
        'wrong-session-owner' => $valid,
    ];

    $outcomes = [];
    foreach ($scenarios as $name => $native) {
        $store = fixtureStore();
        if ($name === 'disabled-user') {
            $store->putUser('user-1', 'DISABLED', 7, ['ROLE_ADMIN'], ['LIST_USERS']);
        } elseif ($name === 'revoked-session') {
            $store->putSession('session-1', 'user-1', true);
        } elseif ($name === 'wrong-session-owner') {
            $store->putSession('session-1', 'other-user');
        }

        $principal = $provider($native, $store)->current();
        $outcomes[$name] = $principal === null ? 'denied' : [
            'user_id' => $principal->userId,
            'authentication_version' => $principal->authenticationVersion,
            'roles' => $principal->roles,
            'permissions' => $principal->permissions,
        ];
    }

    if (!is_array($outcomes['valid']) || array_filter($outcomes, static fn (mixed $v, string $k): bool => $k !== 'valid' && $v !== 'denied', ARRAY_FILTER_USE_BOTH) !== []) {
        throw new LogicException($framework . ' principal integration failed closed-state checks.');
    }

    $receipt = [
        'prototype' => 'WF-017 principal integration',
        'framework' => $framework,
        'versions' => $versions,
        'composition' => $composition,
        'outcomes' => $outcomes,
        'portable_contract' => CurrentPrincipalProvider::class,
        'portable_value' => AuthenticatedPrincipal::class,
        'pass' => true,
    ];
    file_put_contents(__DIR__ . '/receipts/' . $framework . '.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
    echo $framework . ": PASS\n";

    return $receipt;
}

function fixtureStore(): AuthoritativePrincipalStore
{
    $store = new AuthoritativePrincipalStore();
    $store->putUser('user-1', 'ACTIVE', 7, ['ROLE_ADMIN'], ['LIST_USERS']);
    $store->putSession('session-1', 'user-1');

    return $store;
}
