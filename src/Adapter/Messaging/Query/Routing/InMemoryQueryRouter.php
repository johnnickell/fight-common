<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Query\Routing;

use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class InMemoryQueryRouter
 */
final class InMemoryQueryRouter implements QueryRouter
{
    private array $handlers = [];

    /**
     * @inheritDoc
     */
    public function match(Query $query): QueryHandler
    {
        return $this->getHandler($query::class);
    }

    /**
     * Registers query handlers
     *
     * The query to handler map must follow this format:
     * [
     *     SomeQuery::class => $someHandlerInstance
     * ]
     */
    public function registerHandlers(array $queryToHandlerMap): void
    {
        foreach ($queryToHandlerMap as $queryClass => $handler) {
            $this->registerHandler($queryClass, $handler);
        }
    }

    /**
     * Registers a query handler
     */
    public function registerHandler(string $queryClass, QueryHandler $handler): void
    {
        assert(Validate::implementsInterface($queryClass, Query::class));

        $type = Type::create($queryClass)->toString();

        $this->handlers[$type] = $handler;
    }

    /**
     * @inheritDoc
     */
    public function getHandler(string $queryClass): QueryHandler
    {
        $type = Type::create($queryClass)->toString();

        if (!isset($this->handlers[$type])) {
            $message = sprintf('Handler not defined for query: %s', $queryClass);
            throw new LookupException($message);
        }

        return $this->handlers[$type];
    }

    /**
     * @inheritDoc
     */
    public function hasHandler(string $queryClass): bool
    {
        $type = Type::create($queryClass)->toString();

        return isset($this->handlers[$type]);
    }
}
