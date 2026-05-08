<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSubscriber;

use RuntimeException;
use Fight\Common\Adapter\EventSubscriber\SymfonyExceptionSubscriber;
use Fight\Common\Adapter\HttpFoundation\JSendResponse;
use Fight\Common\Adapter\HttpKernel\ErrorController;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(SymfonyExceptionSubscriber::class)]
class SymfonyExceptionSubscriberTest extends UnitTestCase
{
    public function test_that_subscriber_registers_for_kernel_exception_event(): void
    {
        $errorController = new ErrorController();
        $subscriber = new SymfonyExceptionSubscriber($errorController);

        $events = $subscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        self::assertSame('onKernelException', $events[KernelEvents::EXCEPTION]);
    }

    public function test_that_on_kernel_exception_delegates_to_error_controller_and_sets_response(): void
    {
        $exception = new RuntimeException('test error');
        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $request = new Request();

        $errorController = new ErrorController();
        $subscriber = new SymfonyExceptionSubscriber($errorController);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        /** @var JSendResponse $response */
        $response = $event->getResponse();

        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertTrue($response->isError());
        self::assertSame(500, $response->getStatusCode());
    }
}
