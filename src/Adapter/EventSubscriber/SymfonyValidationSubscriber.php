<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSubscriber;

use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Validation\ValidationService;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Class SymfonyValidationSubscriber
 */
final readonly class SymfonyValidationSubscriber implements EventSubscriberInterface
{
    /**
     * Constructs SymfonyValidationSubscriber
     */
    public function __construct(private ValidationService $validationService)
    {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'validateControllerInput'];
    }

    /**
     * Checks Validation Attribute on controller methods
     *
     * @throws Throwable
     */
    public function validateControllerInput(ControllerEvent $event): void
    {
        $controller = $event->getController();
        $request = $event->getRequest();

        if (!is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!is_array($controller) || !is_object($controller[0])) {
            return;
        }

        $className = $controller[0]::class;
        $reflection = new ReflectionClass($className);
        $reflectionMethod = $reflection->getMethod($controller[1]);
        $attributes = $reflectionMethod->getAttributes(Validation::class);
        foreach ($attributes as $attribute) {
            /** @var Validation $validation */
            $validation = $attribute->newInstance();
            $inputData = $request->isMethodSafe() ? $request->query->all() : $request->request->all();
            $this->validationService->validate($inputData, $validation->rules());
        }
    }
}
