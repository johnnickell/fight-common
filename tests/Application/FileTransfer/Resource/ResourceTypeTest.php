<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\FileTransfer\Resource;

use Fight\Common\Application\FileTransfer\Resource\ResourceType;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ResourceType::class)]
class ResourceTypeTest extends UnitTestCase
{
    public function test_that_all_cases_have_correct_values(): void
    {
        self::assertSame('file', ResourceType::FILE->value);
        self::assertSame('dir', ResourceType::DIR->value);
        self::assertSame('link', ResourceType::LINK->value);
        self::assertSame('fifo', ResourceType::FIFO->value);
        self::assertSame('char', ResourceType::CHAR->value);
        self::assertSame('block', ResourceType::BLOCK->value);
        self::assertSame('socket', ResourceType::SOCKET->value);
        self::assertSame('unknown', ResourceType::UNKNOWN->value);
    }

    public function test_that_from_returns_correct_case(): void
    {
        self::assertSame(ResourceType::FILE, ResourceType::from('file'));
        self::assertSame(ResourceType::DIR, ResourceType::from('dir'));
        self::assertSame(ResourceType::LINK, ResourceType::from('link'));
        self::assertSame(ResourceType::UNKNOWN, ResourceType::from('unknown'));
    }
}
