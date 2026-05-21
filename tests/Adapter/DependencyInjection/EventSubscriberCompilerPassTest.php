<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\DependencyInjection\EventSubscriberCompilerPass;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(EventSubscriberCompilerPass::class)]
class EventSubscriberCompilerPassTest extends UnitTestCase
{
    public function test_that_it_returns_early_when_dispatcher_not_registered(): void
    {
        $container = new ContainerBuilder();
        $pass = new EventSubscriberCompilerPass();
        $pass->process($container);

        self::assertFalse($container->has(ServiceAwareEventDispatcher::class));
    }

    public function test_that_it_registers_tagged_event_subscribers(): void
    {
        $container = new ContainerBuilder();
        $dispatcherDef = $container->register(ServiceAwareEventDispatcher::class, ServiceAwareEventDispatcher::class);

        $subscriberDef = $container->register('subscriber_id', StubEventSubscriber::class);
        $subscriberDef->addTag('common.event_subscriber', []);
        $subscriberDef->setPublic(true);

        $pass = new EventSubscriberCompilerPass();
        $pass->process($container);

        $calls = $dispatcherDef->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('registerService', $calls[0][0]);
        self::assertSame([StubEventSubscriber::class, 'subscriber_id'], $calls[0][1]);
    }

    public function test_that_it_registers_multiple_tagged_event_subscribers(): void
    {
        $container = new ContainerBuilder();
        $dispatcherDef = $container->register(ServiceAwareEventDispatcher::class, ServiceAwareEventDispatcher::class);

        $subDef1 = $container->register('sub_1', StubEventSubscriber::class);
        $subDef1->addTag('common.event_subscriber', []);
        $subDef1->setPublic(true);

        $subDef2 = $container->register('sub_2', StubOtherEventSubscriber::class);
        $subDef2->addTag('common.event_subscriber', []);
        $subDef2->setPublic(true);

        $pass = new EventSubscriberCompilerPass();
        $pass->process($container);

        $calls = $dispatcherDef->getMethodCalls();
        self::assertCount(2, $calls);
        self::assertSame('registerService', $calls[0][0]);
        self::assertSame([StubEventSubscriber::class, 'sub_1'], $calls[0][1]);
        self::assertSame('registerService', $calls[1][0]);
        self::assertSame([StubOtherEventSubscriber::class, 'sub_2'], $calls[1][1]);
    }

    public function test_that_it_throws_when_tagged_subscriber_is_not_public(): void
    {
        $container = new ContainerBuilder();
        $container->register(ServiceAwareEventDispatcher::class, ServiceAwareEventDispatcher::class);

        $subscriberDef = $container->register('private_sub', StubEventSubscriber::class);
        $subscriberDef->addTag('common.event_subscriber', []);
        $subscriberDef->setPublic(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be public');

        $pass = new EventSubscriberCompilerPass();
        $pass->process($container);
    }

    public function test_that_it_throws_when_tagged_subscriber_does_not_implement_event_subscriber(): void
    {
        $container = new ContainerBuilder();
        $container->register(ServiceAwareEventDispatcher::class, ServiceAwareEventDispatcher::class);

        $badDef = $container->register('bad_sub', StubNonSubscriberService::class);
        $badDef->addTag('common.event_subscriber', []);
        $badDef->setPublic(true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must implement interface');

        $pass = new EventSubscriberCompilerPass();
        $pass->process($container);
    }
}

class StubEventSubscriber implements EventSubscriber
{
    public static function eventRegistration(): array
    {
        return [];
    }
}

class StubOtherEventSubscriber implements EventSubscriber
{
    public static function eventRegistration(): array
    {
        return [];
    }
}

class StubNonSubscriberService
{
}
