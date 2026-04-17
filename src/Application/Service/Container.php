<?php

declare(strict_types=1);

namespace Fight\Common\Application\Service;

use ArrayAccess;
use Fight\Common\Application\Service\Exception\NotFoundException;
use Psr\Container\ContainerInterface;

/**
 * Class Container
 */
final class Container implements ArrayAccess, ContainerInterface
{
    private array $factories = [];
    private array $parameters = [];

    /**
     * Sets a service factory (returns the same instance on each call)
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = function (Container $container) use ($factory) {
            static $instance;

            if ($instance === null) {
                $instance = $factory($container);
            }

            return $instance;
        };
    }

    /**
     * Sets an object factory (returns a new instance on each call)
     */
    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Retrieves the service or object by ID
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new NotFoundException("Service '{$id}' not found.");
        }

        return $this->factories[$id]($this);
    }

    /**
     * Checks if a factory is defined for the given ID
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->factories);
    }

    /**
     * Sets a parameter by ID
     */
    public function setParameter(string $id, mixed $value): void
    {
        $this->parameters[$id] = $value;
    }

    /**
     * Retrieves a parameter by ID, or returns the default value if not found
     */
    public function getParameter(string $id, mixed $default = null): mixed
    {
        return $this->parameters[$id] ?? $default;
    }

    /**
     * Checks if a parameter is defined for the given ID
     */
    public function hasParameter(string $id): bool
    {
        return array_key_exists($id, $this->parameters);
    }

    /**
     * Removes a parameter by the given ID
     */
    public function removeParameter(string $id): void
    {
        unset($this->parameters[$id]);
    }

    /**
     * Sets a parameter by ID
     */
    public function offsetSet($offset, $value): void
    {
        $this->setParameter((string) $offset, $value);
    }

    /**
     * Retrieves a parameter by ID, or returns null if not found
     */
    public function offsetGet($offset): mixed
    {
        return $this->getParameter((string) $offset);
    }

    /**
     * Checks if a parameter is defined for the given ID
     */
    public function offsetExists($offset): bool
    {
        return $this->hasParameter((string) $offset);
    }

    /**
     * Removes a parameter by the given ID
     */
    public function offsetUnset($offset): void
    {
        $this->removeParameter((string) $offset);
    }
}
