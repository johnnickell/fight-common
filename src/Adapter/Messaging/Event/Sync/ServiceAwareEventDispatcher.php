<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Event\Sync;

use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Utility\Validate;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Class ServiceAwareEventDispatcher
 */
final class ServiceAwareEventDispatcher extends SimpleEventDispatcher
{
    /**
     * @var array<string, array<string, object>>
     */
    private array $loadedHandlerServices = [];
    /**
     * @var array<string, array<int, array{string, string, int}>>
     */
    private array $handlerServiceRegistrations = [];

    /**
     * Constructs ServiceAwareEventDispatcher
     */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Registers a subscriber service to handle events
     *
     * The subscriber class must implement:
     * Fight\Common\Application\Messaging\Event\EventSubscriber
     */
    public function registerService(string $className, string $serviceId): void
    {
        assert(Validate::implementsInterface($className, EventSubscriber::class));
        foreach ($className::eventRegistration() as $eventType => $eventRegistration) {
            $eventType = ClassName::underscore($eventType);
            if (is_string($eventRegistration)) {
                $this->addHandlerService($eventType, $serviceId, $eventRegistration);
            } elseif (is_string($eventRegistration[0])) {
                $priority = isset($eventRegistration[1]) ? (int) $eventRegistration[1] : 0;
                $this->addHandlerService(
                    $eventType,
                    $serviceId,
                    $eventRegistration[0],
                    $priority
                );
            } else {
                foreach ($eventRegistration as $handlerRegistration) {
                    $priority = isset($handlerRegistration[1]) ? (int) $handlerRegistration[1] : 0;
                    $this->addHandlerService(
                        $eventType,
                        $serviceId,
                        $handlerRegistration[0],
                        $priority
                    );
                }
            }
        }
    }

    /**
     * Adds a handler service for a specific event
     */
    public function addHandlerService(string $eventType, string $serviceId, string $method, int $priority = 0): void
    {
        $this->handlerServiceRegistrations[$eventType] ??= [];

        $this->handlerServiceRegistrations[$eventType][] = [$serviceId, $method, $priority];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dispatch(EventMessage $eventMessage): void
    {
        $this->lazyLoad(ClassName::underscore($eventMessage->payload()));

        parent::dispatch($eventMessage);
    }

    /**
     * Retrieves handlers for an event or all events after loading matching services
     *
     * @return callable[]|array<string, callable[]>
     */
    #[Override]
    public function getHandlers(?string $eventType = null): array
    {
        if ($eventType === null) {
            foreach (array_keys($this->handlerServiceRegistrations) as $type) {
                $this->lazyLoad($type);
            }
        } else {
            $this->lazyLoad($eventType);
        }

        return parent::getHandlers($eventType);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasHandlers(?string $eventType = null): bool
    {
        if ($eventType === null) {
            return (bool) count($this->handlerServiceRegistrations) || (bool) count($this->handlers);
        }

        if (isset($this->handlerServiceRegistrations[$eventType])) {
            return true;
        }

        return parent::hasHandlers($eventType);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeHandler(string $eventType, callable $handler): void
    {
        $this->lazyLoad($eventType);

        if (isset($this->handlerServiceRegistrations[$eventType])) {
            foreach ($this->handlerServiceRegistrations[$eventType] as $registrationIndex => $handlerRegistration) {
                [$serviceId, $method] = $handlerRegistration;
                $handlerServiceKey = sprintf('%s.%s', $serviceId, $method);
                if (
                    isset($this->loadedHandlerServices[$eventType][$handlerServiceKey])
                    && $handler === [$this->loadedHandlerServices[$eventType][$handlerServiceKey], $method]
                ) {
                    unset($this->loadedHandlerServices[$eventType][$handlerServiceKey]);
                    if (empty($this->loadedHandlerServices[$eventType])) {
                        unset($this->loadedHandlerServices[$eventType]);
                    }

                    unset($this->handlerServiceRegistrations[$eventType][$registrationIndex]);
                    if (empty($this->handlerServiceRegistrations[$eventType])) {
                        unset($this->handlerServiceRegistrations[$eventType]);
                    }
                }
            }
        }

        parent::removeHandler($eventType, $handler);
    }

    /**
     * Loads event handlers lazily from the service container
     */
    private function lazyLoad(string $eventType): void
    {
        if (isset($this->handlerServiceRegistrations[$eventType])) {
            foreach ($this->handlerServiceRegistrations[$eventType] as $handlerRegistration) {
                [$serviceId, $method, $priority] = $handlerRegistration;
                $handlerService = $this->container->get($serviceId);
                $handlerServiceKey = sprintf('%s.%s', $serviceId, $method);
                if (!isset($this->loadedHandlerServices[$eventType][$handlerServiceKey])) {
                    $this->addHandler($eventType, [$handlerService, $method], $priority);
                } elseif ($handlerService !== $this->loadedHandlerServices[$eventType][$handlerServiceKey]) {
                    parent::removeHandler(
                        $eventType,
                        [$this->loadedHandlerServices[$eventType][$handlerServiceKey], $method]
                    );
                    $this->addHandler($eventType, [$handlerService, $method], $priority);
                }

                $this->loadedHandlerServices[$eventType][$handlerServiceKey] = $handlerService;
            }
        }
    }
}
