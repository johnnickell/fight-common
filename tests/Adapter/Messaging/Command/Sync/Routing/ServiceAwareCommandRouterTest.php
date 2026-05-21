<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Command\Sync\Routing;

use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;

#[CoversClass(ServiceAwareCommandRouter::class)]
class ServiceAwareCommandRouterTest extends UnitTestCase
{
    /** @var MockInterface|ContainerInterface */
    private ContainerInterface $container;

    private ServiceAwareCommandRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->mock(ContainerInterface::class);
        $this->router = new ServiceAwareCommandRouter($this->container);
    }

    public function test_that_register_handler_and_get_handler_load_from_container(): void
    {
        $handler = new SampleServiceCommandHandler();

        $this->container->shouldReceive('has')->with('handler_service')->andReturn(true);
        $this->container->shouldReceive('get')->with('handler_service')->andReturn($handler);

        $this->router->registerHandler(SampleServiceCommand::class, 'handler_service');

        self::assertSame($handler, $this->router->getHandler(SampleServiceCommand::class));
    }

    public function test_that_register_handlers_batch_registers_multiple(): void
    {
        $handler1 = new SampleServiceCommandHandler();
        $handler2 = new SampleOtherServiceCommandHandler();

        $this->container->shouldReceive('has')->andReturn(true);
        $this->container->shouldReceive('get')->with('svc1')->andReturn($handler1);
        $this->container->shouldReceive('get')->with('svc2')->andReturn($handler2);

        $this->router->registerHandlers([
            SampleServiceCommand::class => 'svc1',
            SampleOtherServiceCommand::class => 'svc2',
        ]);

        self::assertSame($handler1, $this->router->getHandler(SampleServiceCommand::class));
        self::assertSame($handler2, $this->router->getHandler(SampleOtherServiceCommand::class));
    }

    public function test_that_match_delegates_to_get_handler(): void
    {
        $handler = new SampleServiceCommandHandler();

        $this->container->shouldReceive('has')->andReturn(true);
        $this->container->shouldReceive('get')->with('svc')->andReturn($handler);

        $this->router->registerHandler(SampleServiceCommand::class, 'svc');

        self::assertSame($handler, $this->router->match(new SampleServiceCommand()));
    }

    public function test_that_get_handler_throws_when_not_registered(): void
    {
        $this->container->shouldReceive('has')->andReturn(false);

        $this->expectException(LookupException::class);
        $this->router->getHandler(SampleServiceCommand::class);
    }

    public function test_that_has_handler_returns_false_when_not_registered(): void
    {
        self::assertFalse($this->router->hasHandler(SampleServiceCommand::class));
    }

    public function test_that_has_handler_checks_container_has(): void
    {
        $this->container->shouldReceive('has')->with('svc')->andReturn(true);

        $this->router->registerHandler(SampleServiceCommand::class, 'svc');

        self::assertTrue($this->router->hasHandler(SampleServiceCommand::class));
    }

    public function test_that_has_handler_returns_false_when_container_does_not_have_service(): void
    {
        $this->container->shouldReceive('has')->with('svc')->andReturn(false);

        $this->router->registerHandler(SampleServiceCommand::class, 'svc');

        self::assertFalse($this->router->hasHandler(SampleServiceCommand::class));
    }
}

class SampleServiceCommand implements Command
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

class SampleOtherServiceCommand implements Command
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

class SampleServiceCommandHandler implements CommandHandler
{
    public static function commandRegistration(): string
    {
        return SampleServiceCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void {}
}

class SampleOtherServiceCommandHandler implements CommandHandler
{
    public static function commandRegistration(): string
    {
        return SampleOtherServiceCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void {}
}
