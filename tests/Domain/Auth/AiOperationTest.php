<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Auth;

use Fight\Common\Domain\Auth\AiOperation;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AiOperation::class)]
class AiOperationTest extends UnitTestCase
{
    public function test_that_from_array_creates_operation_for_known_action(): void
    {
        $op = AiOperation::fromArray(['action' => 'health_check', 'payload' => ['env' => 'prod']]);

        self::assertSame('health_check', $op->action());
        self::assertSame(['env' => 'prod'], $op->payload());
    }

    public function test_that_from_array_defaults_payload_to_empty_array(): void
    {
        $op = AiOperation::fromArray(['action' => 'clear_cache']);

        self::assertSame([], $op->payload());
    }

    public function test_that_from_array_throws_when_action_is_missing(): void
    {
        $this->expectException(DomainException::class);
        AiOperation::fromArray(['payload' => []]);
    }

    public function test_that_from_array_throws_for_unknown_action(): void
    {
        $this->expectException(DomainException::class);
        AiOperation::fromArray(['action' => 'reboot_everything']);
    }

    public function test_that_all_known_actions_are_accepted(): void
    {
        foreach (['health_check', 'clear_cache', 'run_migration', 'deploy'] as $action) {
            $op = AiOperation::fromArray(['action' => $action]);
            self::assertSame($action, $op->action());
        }
    }

    public function test_that_from_json_parses_valid_json(): void
    {
        $json = json_encode(['action' => 'deploy', 'payload' => ['version' => '1.2.3']]);
        $op = AiOperation::fromJson($json);

        self::assertSame('deploy', $op->action());
        self::assertSame(['version' => '1.2.3'], $op->payload());
    }

    public function test_that_from_json_throws_for_invalid_json(): void
    {
        $this->expectException(DomainException::class);
        AiOperation::fromJson('not-json');
    }

    public function test_that_to_array_returns_action_and_payload(): void
    {
        $op = AiOperation::fromArray(['action' => 'run_migration', 'payload' => ['name' => 'v3']]);

        self::assertSame(['action' => 'run_migration', 'payload' => ['name' => 'v3']], $op->toArray());
    }

    public function test_that_json_serialize_matches_to_array(): void
    {
        $op = AiOperation::fromArray(['action' => 'health_check']);

        self::assertSame($op->toArray(), $op->jsonSerialize());
    }
}
