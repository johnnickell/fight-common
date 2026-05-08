<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Messaging;

use Fight\Common\Domain\Messaging\MessageType;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MessageType::class)]
class MessageTypeTest extends UnitTestCase
{
    public function test_that_command_case_has_expected_string_value(): void
    {
        self::assertSame('command', MessageType::COMMAND->value);
    }

    public function test_that_query_case_has_expected_string_value(): void
    {
        self::assertSame('query', MessageType::QUERY->value);
    }

    public function test_that_event_case_has_expected_string_value(): void
    {
        self::assertSame('event', MessageType::EVENT->value);
    }
}
