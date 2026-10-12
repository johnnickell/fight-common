<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Symfony\EventSubscriber;

use Fight\Common\Adapter\Http\Symfony\Controller\SafeErrorController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Class SafeSymfonyExceptionSubscriber
 *
 * Register explicitly instead of autoconfiguring beside the unsafe legacy listener.
 */
final readonly class SafeSymfonyExceptionSubscriber
{
    /**
     * Constructs SafeSymfonyExceptionSubscriber
     */
    public function __construct(private SafeErrorController $errors)
    {
    }

    /**
     * Handles a JSON exception before the framework's diagnostic listener
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->wantsJson($event->getRequest())) {
            return;
        }

        $event->setResponse($this->errors->handle($event->getThrowable()));
    }

    /**
     * Determines the canonical subscriber's JSON/XHR preference
     */
    private function wantsJson(Request $request): bool
    {
        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        $acceptable = $request->getAcceptableContentTypes();
        $json = array_search('application/json', $acceptable, true);
        $html = array_search('text/html', $acceptable, true);

        return $json !== false && ($html === false || $json < $html);
    }
}
