<?php

declare(strict_types=1);

namespace Prototype\LoginTransactionSlice;

use LogicException;
use RuntimeException;
use Throwable;

final class NativeTransactionalUnitOfWork
{
    private bool $active = false;

    /** @param callable(callable(): mixed): mixed $transaction */
    public function __construct(
        private readonly string $adapter,
        private readonly mixed $transaction,
    ) {}

    public function commitTransactional(callable $operation): mixed
    {
        if ($this->active) {
            throw new LogicException('Nested transactions are outside the portable contract.');
        }

        $this->active = true;
        try {
            return ($this->transaction)($operation);
        } finally {
            $this->active = false;
        }
    }

    public function adapter(): string
    {
        return $this->adapter;
    }
}

enum LoginDecision: string
{
    case Authenticated = 'authenticated';
    case InvalidCredentials = 'invalid_credentials';
}

final readonly class LoginOutcome
{
    public function __construct(
        public LoginDecision $decision,
        public ?string $accessToken = null,
        public ?string $refreshToken = null,
    ) {}
}

final readonly class LoginHandler
{
    /**
     * @param callable(string): array{id: string, password_hash: string, state: string}|null $findUser
     * @param callable(string, string): void $insertSession
     * @param callable(string, string): void $insertAudit
     */
    public function __construct(
        private mixed $findUser,
        private NativeTransactionalUnitOfWork $unitOfWork,
        private mixed $insertSession,
        private mixed $insertAudit,
        private string $sessionId,
        private string $auditId,
    ) {}

    public function handle(string $email, string $password): LoginOutcome
    {
        $user = ($this->findUser)(canonicalEmail($email));
        if ($user === null || $user['state'] !== 'ACTIVE' || !password_verify($password, $user['password_hash'])) {
            return new LoginOutcome(LoginDecision::InvalidCredentials);
        }

        return $this->unitOfWork->commitTransactional(function () use ($user): LoginOutcome {
            ($this->insertSession)($this->sessionId, $user['id']);
            ($this->insertAudit)($this->auditId, $user['id']);

            return new LoginOutcome(
                LoginDecision::Authenticated,
                'prototype.access.jwt',
                'prototype-opaque-refresh-token',
            );
        });
    }
}

/**
 * @param array<string, string|null> $versions
 * @param callable(LoginOutcome): array{class: string, status: int, headers: array<string, string>, body: array<string, mixed>} $respond
 * @param callable(): array{sessions: int, audits: int} $counts
 * @param callable(bool): LoginHandler $handlerFactory
 */
function runLoginProbe(
    string $framework,
    array $versions,
    NativeTransactionalUnitOfWork $unitOfWork,
    callable $respond,
    callable $counts,
    callable $handlerFactory,
): array {
    $success = $respond($handlerFactory(false)->handle(' ADA@EXAMPLE.TEST ', 'correct horse battery staple'));
    assertResponse($success, 200, 'success', true);
    prototypeAssert($counts() === ['sessions' => 1, 'audits' => 1], 'successful login must atomically persist one session and one audit');

    $invalid = $respond($handlerFactory(false)->handle('ada@example.test', 'wrong password'));
    assertResponse($invalid, 401, 'fail', false);
    prototypeAssert($counts() === ['sessions' => 1, 'audits' => 1], 'invalid credentials must not persist a session or successful-login audit');

    $auditFailure = null;
    try {
        $handlerFactory(true)->handle('ada@example.test', 'correct horse battery staple');
    } catch (RuntimeException $exception) {
        $auditFailure = $exception->getMessage();
    }
    prototypeAssert($auditFailure === 'forced audit failure', 'audit failure must escape the handler');
    prototypeAssert($counts() === ['sessions' => 1, 'audits' => 1], 'audit failure must roll back the new session');

    $nestedRejected = false;
    try {
        $unitOfWork->commitTransactional(
            static fn (): mixed => $unitOfWork->commitTransactional(static fn (): string => 'not reached'),
        );
    } catch (LogicException) {
        $nestedRejected = true;
    }
    prototypeAssert($nestedRejected, 'nested transactions must fail explicitly');

    $receipt = [
        'prototype' => 'WF-017 login transaction slice',
        'framework' => $framework,
        'versions' => $versions,
        'portable_handler' => LoginHandler::class,
        'native_transaction_adapter' => $unitOfWork->adapter(),
        'scenarios' => [
            'successful_login' => $success,
            'invalid_credentials' => $invalid,
            'forced_audit_failure' => [
                'exception' => $auditFailure,
                'state_after_rollback' => $counts(),
            ],
            'nested_transaction_rejected' => $nestedRejected,
        ],
        'observations' => [
            'canonical_email_lookup' => true,
            'password_verified_before_transaction' => true,
            'session_and_audit_atomic' => true,
            'refresh_cookie_http_only' => true,
            'refresh_cookie_secure' => true,
            'refresh_cookie_same_site_strict' => true,
            'generic_invalid_credentials' => true,
        ],
        'pass' => true,
    ];

    $receiptPath = getenv('PROTOTYPE_RECEIPT');
    if (!is_string($receiptPath) || $receiptPath === '') {
        throw new RuntimeException('PROTOTYPE_RECEIPT is required.');
    }
    file_put_contents($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    echo $framework . ": PASS\n";

    return $receipt;
}

function canonicalEmail(string $email): string
{
    return strtolower(trim($email));
}

/** @param array{class: string, status: int, headers: array<string, string>, body: array<string, mixed>} $response */
function assertResponse(array $response, int $status, string $jsendStatus, bool $expectsCookie): void
{
    prototypeAssert($response['status'] === $status, 'unexpected HTTP status');
    prototypeAssert(($response['body']['status'] ?? null) === $jsendStatus, 'unexpected JSend status');
    prototypeAssert(str_starts_with(strtolower($response['headers']['content-type'] ?? ''), 'application/json'), 'response must be JSON');
    $cookie = $response['headers']['set-cookie'] ?? '';
    prototypeAssert(($cookie !== '') === $expectsCookie, 'refresh cookie presence is wrong');
    if ($expectsCookie) {
        $normalizedCookie = strtolower($cookie);
        prototypeAssert(str_contains($normalizedCookie, 'httponly'), 'refresh cookie must be HttpOnly');
        prototypeAssert(str_contains($normalizedCookie, 'secure'), 'refresh cookie must be Secure');
        prototypeAssert(str_contains($normalizedCookie, 'samesite=strict'), 'refresh cookie must be SameSite Strict');
    }
}

function prototypeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{status: int, body: array<string, mixed>, cookie: ?string} */
function responseParts(LoginOutcome $outcome): array
{
    if ($outcome->decision === LoginDecision::InvalidCredentials) {
        return [
            'status' => 401,
            'body' => ['status' => 'fail', 'data' => ['code' => 'invalid_credentials']],
            'cookie' => null,
        ];
    }

    return [
        'status' => 200,
        'body' => [
            'status' => 'success',
            'data' => ['access_token' => $outcome->accessToken, 'expires_in' => 900],
        ],
        'cookie' => sprintf(
            'refresh=%s; Path=/api/v1/access/session; Secure; HttpOnly; SameSite=Strict',
            $outcome->refreshToken,
        ),
    ];
}

/** @return callable(bool): LoginHandler */
function handlerFactory(
    NativeTransactionalUnitOfWork $unitOfWork,
    callable $findUser,
    callable $insertSession,
    callable $insertAudit,
): callable {
    return static fn (bool $failAudit): LoginHandler => new LoginHandler(
        $findUser,
        $unitOfWork,
        $insertSession,
        $failAudit
            ? static function (): never { throw new RuntimeException('forced audit failure'); }
            : $insertAudit,
        $failAudit ? 'session-rollback-probe' : 'session-001',
        $failAudit ? 'audit-rollback-probe' : 'audit-001',
    );
}
