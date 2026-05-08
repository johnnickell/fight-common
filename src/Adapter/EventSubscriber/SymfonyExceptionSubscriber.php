<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSubscriber;

use Fight\Common\Adapter\HttpKernel\ErrorController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class SymfonyExceptionSubscriber
 */
final readonly class SymfonyExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * Constructs SymfonyExceptionSubscriber
     */
    public function __construct(private ErrorController $errorController)
    {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    /**
     * Handles exceptions
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $response = $this->errorController->handle($event->getThrowable());

        $event->setResponse($response);
    }
}
