<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\ServiceContainer\Yii\MessagingServiceProvider;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MessagingServiceProvider::class)]
final class MessagingServiceProviderTest extends UnitTestCase
{
    public function test_that_get_definitions_registers_only_the_reusable_synchronous_handlers(): void
    {
        self::assertSame(
            [
                CommandMessageHandler::class => CommandMessageHandler::class,
                EventMessageHandler::class   => EventMessageHandler::class
            ],
            (new MessagingServiceProvider())->getDefinitions()
        );
    }

    public function test_that_get_extensions_returns_no_extensions(): void
    {
        self::assertSame([], (new MessagingServiceProvider())->getExtensions());
    }
}
