<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Event\Sync;

use Closure;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Throwable;

/**
 * Class SimpleEventDispatcher
 */
class SimpleEventDispatcher implements SynchronousEventDispatcher
{
    /** @var array<string, array<int, array<int, callable>>> */
    protected array $handlers = [];
    /** @var array<string, array<int, callable>> */
    protected array $sorted = [];

    /**
     * @inheritDoc
     */
    public function trigger(Event $event): void
    {
        $this->dispatch(EventMessage::create($event));
    }

    /**
     * @inheritDoc
     */
    public function dispatch(EventMessage $eventMessage): void
    {
        $eventType = ClassName::underscore($eventMessage->payload());
        $allEvents = ClassName::underscore(AllEvents::class);
        $failures = [];

        $this->invokeHandlerPhase($this->getHandlers($eventType), $eventMessage, $failures);
        $this->invokeHandlerPhase($this->getHandlers($allEvents), $eventMessage, $failures);

        if ([] !== $failures) {
            throw new EventDispatchFailed($failures);
        }
    }

    /**
     * @inheritDoc
     */
    public function register(EventSubscriber $subscriber): void
    {
        foreach ($subscriber->eventRegistration() as $eventType => $params) {
            $eventType = ClassName::underscore($eventType);
            if (is_string($params)) {
                $this->addHandler($eventType, [$subscriber, $params]);
            } elseif (is_string($params[0])) {
                $priority = isset($params[1]) ? (int) $params[1] : 0;
                $this->addHandler($eventType, [$subscriber, $params[0]], $priority);
            } else {
                foreach ($params as $handler) {
                    $priority = isset($handler[1]) ? (int) $handler[1] : 0;
                    $this->addHandler($eventType, [$subscriber, $handler[0]], $priority);
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function unregister(EventSubscriber $subscriber): void
    {
        foreach ($subscriber->eventRegistration() as $eventType => $params) {
            $eventType = ClassName::underscore($eventType);
            if (is_array($params) && is_array($params[0])) {
                foreach ($params as $handler) {
                    $this->removeHandler($eventType, [$subscriber, $handler[0]]);
                }
            } else {
                $handler = is_string($params) ? $params : $params[0];
                $this->removeHandler($eventType, [$subscriber, $handler]);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function addHandler(string $eventType, callable $handler, int $priority = 0): void
    {
        $this->handlers[$eventType] ??= [];

        $this->handlers[$eventType][$priority] ??= [];

        $this->handlers[$eventType][$priority][] = $handler;
        unset($this->sorted[$eventType]);
    }

    /**
     * Retrieves handlers for an event or all events
     *
     * @return callable[]|array<string, callable[]>
     */
    public function getHandlers(?string $eventType = null): array
    {
        if ($eventType !== null) {
            if (!isset($this->handlers[$eventType])) {
                return [];
            }

            if (!isset($this->sorted[$eventType])) {
                $this->sortHandlers($eventType);
            }

            return $this->sorted[$eventType];
        }

        foreach (array_keys($this->handlers) as $eventType) {
            if (!isset($this->sorted[$eventType])) {
                $this->sortHandlers($eventType);
            }
        }

        return array_filter($this->sorted);
    }

    /**
     * @inheritDoc
     */
    public function hasHandlers(?string $eventType = null): bool
    {
        return (bool) count($this->getHandlers($eventType));
    }

    /**
     * @inheritDoc
     */
    public function removeHandler(string $eventType, callable $handler): void
    {
        if (!isset($this->handlers[$eventType])) {
            return;
        }

        foreach ($this->handlers[$eventType] as $priority => $handlers) {
            $key = array_search($handler, $handlers, true);
            if ($key !== false) {
                unset($this->handlers[$eventType][$priority][$key]);
                unset($this->sorted[$eventType]);
                if (empty($this->handlers[$eventType][$priority])) {
                    unset($this->handlers[$eventType][$priority]);
                }

                if (empty($this->handlers[$eventType])) {
                    unset($this->handlers[$eventType]);
                }
            }
        }
    }

    /**
     * Sorts event handlers by priority
     */
    protected function sortHandlers(string $eventType): void
    {
        $this->sorted[$eventType] = [];
        if (isset($this->handlers[$eventType])) {
            krsort($this->handlers[$eventType]);
            $this->sorted[$eventType] = call_user_func_array(
                array_merge(...),
                $this->handlers[$eventType]
            );
        }
    }

    /**
     * Invokes one resolved handler phase and appends its failures
     *
     * @param callable[]            $handlers
     * @param EventMessage          $eventMessage
     * @param EventHandlerFailure[] $failures
     */
    private function invokeHandlerPhase(array $handlers, EventMessage $eventMessage, array &$failures): void
    {
        foreach ($handlers as $handler) {
            try {
                call_user_func($handler, $eventMessage);
            } catch (Throwable $throwable) {
                $failures[] = new EventHandlerFailure($this->describeCallable($handler), $throwable);
            }
        }
    }

    /**
     * Returns an operational description of a handler callable
     */
    private function describeCallable(callable $handler): string
    {
        if ($handler instanceof Closure) {
            return 'Closure (non-replayable)';
        }

        if (is_string($handler)) {
            return $handler;
        }

        if (is_array($handler)) {
            $callableTarget = $handler[0];
            $target = is_object($callableTarget) ? $callableTarget::class : $callableTarget;

            return $target.'::'.$handler[1];
        }

        return get_debug_type($handler).'::__invoke';
    }
}
