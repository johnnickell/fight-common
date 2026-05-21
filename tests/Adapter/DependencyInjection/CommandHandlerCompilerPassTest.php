<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\DependencyInjection\CommandHandlerCompilerPass;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(CommandHandlerCompilerPass::class)]
class CommandHandlerCompilerPassTest extends UnitTestCase
{
    public function test_that_it_returns_early_when_router_not_registered(): void
    {
        $container = new ContainerBuilder();
        $pass = new CommandHandlerCompilerPass();
        $pass->process($container);

        self::assertFalse($container->has(ServiceAwareCommandRouter::class));
    }

    public function test_that_it_registers_tagged_command_handlers(): void
    {
        $container = new ContainerBuilder();
        $routerDef = $container->register(ServiceAwareCommandRouter::class, ServiceAwareCommandRouter::class);

        $handlerDef = $container->register('handler_id', StubCommandHandler::class);
        $handlerDef->addTag('common.command_handler', []);
        $handlerDef->setPublic(true);

        $pass = new CommandHandlerCompilerPass();
        $pass->process($container);

        $calls = $routerDef->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('registerHandler', $calls[0][0]);
        self::assertSame([StubCommand::class, 'handler_id'], $calls[0][1]);
    }

    public function test_that_it_registers_multiple_tagged_command_handlers(): void
    {
        $container = new ContainerBuilder();
        $routerDef = $container->register(ServiceAwareCommandRouter::class, ServiceAwareCommandRouter::class);

        $handlerDef1 = $container->register('handler_1', StubCommandHandler::class);
        $handlerDef1->addTag('common.command_handler', []);
        $handlerDef1->setPublic(true);

        $handlerDef2 = $container->register('handler_2', StubOtherCommandHandler::class);
        $handlerDef2->addTag('common.command_handler', []);
        $handlerDef2->setPublic(true);

        $pass = new CommandHandlerCompilerPass();
        $pass->process($container);

        $calls = $routerDef->getMethodCalls();
        self::assertCount(2, $calls);
        self::assertSame('registerHandler', $calls[0][0]);
        self::assertSame([StubCommand::class, 'handler_1'], $calls[0][1]);
        self::assertSame('registerHandler', $calls[1][0]);
        self::assertSame([StubOtherCommand::class, 'handler_2'], $calls[1][1]);
    }

    public function test_that_it_throws_when_tagged_handler_is_not_public(): void
    {
        $container = new ContainerBuilder();
        $container->register(ServiceAwareCommandRouter::class, ServiceAwareCommandRouter::class);

        $handlerDef = $container->register('private_handler', StubCommandHandler::class);
        $handlerDef->addTag('common.command_handler', []);
        $handlerDef->setPublic(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be public');

        $pass = new CommandHandlerCompilerPass();
        $pass->process($container);
    }

    public function test_that_it_throws_when_tagged_handler_does_not_implement_command_handler(): void
    {
        $container = new ContainerBuilder();
        $container->register(ServiceAwareCommandRouter::class, ServiceAwareCommandRouter::class);

        $badDef = $container->register('bad_handler', StubCommandNonHandlerService::class);
        $badDef->addTag('common.command_handler', []);
        $badDef->setPublic(true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must implement interface');

        $pass = new CommandHandlerCompilerPass();
        $pass->process($container);
    }
}

class StubCommand implements Command
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

class StubOtherCommand implements Command
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

class StubCommandHandler implements CommandHandler
{
    public static function commandRegistration(): string
    {
        return StubCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void {}
}

class StubOtherCommandHandler implements CommandHandler
{
    public static function commandRegistration(): string
    {
        return StubOtherCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void {}
}

class StubCommandNonHandlerService
{
}
