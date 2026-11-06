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
        $aiOperation = AiOperation::fromArray(['action' => 'health_check', 'payload' => ['env' => 'prod']]);

        self::assertSame(AiOperation::class, $aiOperation::class);
        self::assertSame('health_check', $aiOperation->action());
        self::assertSame(['env' => 'prod'], $aiOperation->payload());
        self::assertSame('{"action":"health_check","payload":{"env":"prod"}}', json_encode($aiOperation));
    }

    public function test_that_from_array_defaults_payload_to_empty_array(): void
    {
        $aiOperation = AiOperation::fromArray(['action' => 'clear_cache']);

        self::assertSame([], $aiOperation->payload());
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
            $aiOperation = AiOperation::fromArray(['action' => $action]);
            self::assertSame($action, $aiOperation->action());
        }
    }

    public function test_that_from_json_parses_valid_json(): void
    {
        $json = json_encode(['action' => 'deploy', 'payload' => ['version' => '1.2.3']]);
        $aiOperation = AiOperation::fromJson($json);

        self::assertSame(AiOperation::class, $aiOperation::class);
        self::assertSame('deploy', $aiOperation->action());
        self::assertSame(['version' => '1.2.3'], $aiOperation->payload());
    }

    public function test_that_from_json_throws_for_invalid_json(): void
    {
        $this->expectException(DomainException::class);
        AiOperation::fromJson('not-json');
    }

    public function test_that_to_array_returns_action_and_payload(): void
    {
        $aiOperation = AiOperation::fromArray(['action' => 'run_migration', 'payload' => ['name' => 'v3']]);

        self::assertSame(['action' => 'run_migration', 'payload' => ['name' => 'v3']], $aiOperation->toArray());
    }

    public function test_that_json_serialize_matches_to_array(): void
    {
        $aiOperation = AiOperation::fromArray(['action' => 'health_check']);

        self::assertSame($aiOperation->toArray(), $aiOperation->jsonSerialize());
    }
}
