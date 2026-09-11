<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSubscriber;

use Fight\Common\Adapter\HttpKernel\ErrorController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class SymfonyExceptionSubscriber
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyExceptionSubscriber
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
        if (!$this->wantsJson($event->getRequest())) {
            return;
        }

        $event->setResponse($this->errorController->handle($event->getThrowable()));
    }

    /**
     * Determines whether the request expects JSON
     */
    private function wantsJson(Request $request): bool
    {
        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        $acceptable = $request->getAcceptableContentTypes();
        $jsonPos = array_search('application/json', $acceptable, true);
        $htmlPos = array_search('text/html', $acceptable, true);

        return $jsonPos !== false && ($htmlPos === false || $jsonPos < $htmlPos);
    }
}
