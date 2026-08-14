<?php

declare(strict_types=1);

namespace Prototype\HttpAction;

use LogicException;

final readonly class AuthenticatedPrincipal
{
    /** @param list<string> $permissions */
    public function __construct(
        public string $userId,
        public array $permissions,
    ) {}
}

enum ListUsersDecision: string
{
    case Authorized = 'authorized';
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
}

final readonly class ListUsersOutcome
{
    /** @param list<array{id: string, display_name: string}> $users */
    public function __construct(
        public ListUsersDecision $decision,
        public array $users = [],
    ) {}
}

final readonly class ListUsersQueryHandler
{
    /** @param list<array{id: string, display_name: string}> $users */
    public function __construct(private array $users) {}

    public function handle(?AuthenticatedPrincipal $principal): ListUsersOutcome
    {
        if ($principal === null) {
            return new ListUsersOutcome(ListUsersDecision::Unauthenticated);
        }
        if (!in_array('LIST_USERS', $principal->permissions, true)) {
            return new ListUsersOutcome(ListUsersDecision::Forbidden);
        }

        return new ListUsersOutcome(ListUsersDecision::Authorized, $this->users);
    }
}

/**
 * @param array<string, string|null> $versions
 * @param array<string, string> $composition
 * @param callable(ListUsersOutcome): array{class: string, status: int, content_type: string, body: array<string, mixed>} $respond
 */
function runLane(string $framework, array $versions, array $composition, callable $respond): array
{
    $handler = new ListUsersQueryHandler([
        ['id' => 'user-1', 'display_name' => 'Ada'],
        ['id' => 'user-2', 'display_name' => 'Grace'],
    ]);
    $scenarios = [
        'authorized' => new AuthenticatedPrincipal('admin-1', ['LIST_USERS']),
        'unauthenticated' => null,
        'forbidden' => new AuthenticatedPrincipal('member-1', ['VIEW_PROFILE']),
    ];

    $responses = [];
    foreach ($scenarios as $scenario => $principal) {
        $outcome = $handler->handle($principal);
        $response = $respond($outcome);
        $expectedStatus = match ($scenario) {
            'authorized' => 200,
            'unauthenticated' => 401,
            'forbidden' => 403,
        };
        $expectedBody = match ($scenario) {
            'authorized' => ['status' => 'success', 'data' => ['users' => $outcome->users]],
            'unauthenticated' => ['status' => 'fail', 'data' => ['code' => 'authentication_required']],
            'forbidden' => ['status' => 'fail', 'data' => ['code' => 'forbidden']],
        };

        if ($response['status'] !== $expectedStatus || $response['body'] !== $expectedBody) {
            throw new LogicException($framework . ' returned the wrong ' . $scenario . ' response.');
        }
        if (!str_starts_with(strtolower($response['content_type']), 'application/json')) {
            throw new LogicException($framework . ' did not return JSON for ' . $scenario . '.');
        }

        $responses[$scenario] = $response;
    }

    $receipt = [
        'prototype' => 'WF-017 native HTTP action',
        'framework' => $framework,
        'versions' => $versions,
        'composition' => $composition,
        'portable_handler' => ListUsersQueryHandler::class,
        'portable_outcome' => ListUsersOutcome::class,
        'responses' => $responses,
        'pass' => true,
    ];
    file_put_contents(
        __DIR__ . '/receipts/' . strtolower($framework) . '.json',
        json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL,
    );
    echo $framework . ": PASS\n";

    return $receipt;
}
