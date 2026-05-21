<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Command\Sync\Routing;

use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryCommandRouter::class)]
class InMemoryCommandRouterTest extends UnitTestCase
{
    private InMemoryCommandRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new InMemoryCommandRouter();
    }

    public function test_that_register_handler_and_match_return_correct_handler(): void
    {
        $command = new SampleInMemoryCommand();
        $handler = new SampleInMemoryCommandHandler();

        $this->router->registerHandler(SampleInMemoryCommand::class, $handler);

        self::assertSame($handler, $this->router->match($command));
    }

    public function test_that_register_handlers_batch_registers_multiple(): void
    {
        $handler1 = new SampleInMemoryCommandHandler();
        $handler2 = new SampleOtherInMemoryCommandHandler();

        $this->router->registerHandlers([
            SampleInMemoryCommand::class => $handler1,
            SampleOtherInMemoryCommand::class => $handler2,
        ]);

        self::assertSame($handler1, $this->router->match(new SampleInMemoryCommand()));
        self::assertSame($handler2, $this->router->match(new SampleOtherInMemoryCommand()));
    }

    public function test_that_get_handler_returns_registered_handler(): void
    {
        $handler = new SampleInMemoryCommandHandler();
        $this->router->registerHandler(SampleInMemoryCommand::class, $handler);

        self::assertSame($handler, $this->router->getHandler(SampleInMemoryCommand::class));
    }

    public function test_that_get_handler_throws_lookup_exception_when_not_found(): void
    {
        $this->expectException(LookupException::class);
        $this->router->getHandler(SampleInMemoryCommand::class);
    }

    public function test_that_has_handler_returns_false_when_not_registered(): void
    {
        self::assertFalse($this->router->hasHandler(SampleInMemoryCommand::class));
    }

    public function test_that_has_handler_returns_true_when_registered(): void
    {
        $this->router->registerHandler(SampleInMemoryCommand::class, new SampleInMemoryCommandHandler());

        self::assertTrue($this->router->hasHandler(SampleInMemoryCommand::class));
    }
}

class SampleInMemoryCommand implements Command
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

class SampleOtherInMemoryCommand implements Command
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

class SampleInMemoryCommandHandler implements CommandHandler
{
    public static function commandRegistration(): string
    {
        return SampleInMemoryCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void {}
}

class SampleOtherInMemoryCommandHandler implements CommandHandler
{
    public static function commandRegistration(): string
    {
        return SampleOtherInMemoryCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void {}
}
