<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Query\Routing;

use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\Validate;
use Psr\Container\ContainerInterface;

/**
 * Class ServiceAwareQueryRouter
 */
final class ServiceAwareQueryRouter implements QueryRouter
{
    /**
     * @var array<string, string>
     */
    private array $handlerServiceIdsByType = [];

    /**
     * Constructs ServiceAwareQueryRouter
     */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

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
     *     SomeQuery::class => 'handler_service_name'
     * ]
     *
     * @param array<string, string> $queryToHandlerMap
     */
    public function registerHandlers(array $queryToHandlerMap): void
    {
        foreach ($queryToHandlerMap as $queryClass => $handlerServiceId) {
            $this->registerHandler($queryClass, $handlerServiceId);
        }
    }

    /**
     * Registers a query handler
     */
    public function registerHandler(string $queryClass, string $serviceName): void
    {
        assert(Validate::implementsInterface($queryClass, Query::class));

        $type = Type::create($queryClass)->toString();

        $this->handlerServiceIdsByType[$type] = $serviceName;
    }

    /**
     * @inheritDoc
     */
    public function getHandler(string $queryClass): QueryHandler
    {
        if (!$this->hasHandler($queryClass)) {
            $message = sprintf('Handler not defined for query: %s', $queryClass);
            throw new LookupException($message);
        }

        $type = Type::create($queryClass)->toString();
        $handlerServiceId = $this->handlerServiceIdsByType[$type];

        return $this->container->get($handlerServiceId);
    }

    /**
     * @inheritDoc
     */
    public function hasHandler(string $queryClass): bool
    {
        $type = Type::create($queryClass)->toString();

        if (!isset($this->handlerServiceIdsByType[$type])) {
            return false;
        }

        $handlerServiceId = $this->handlerServiceIdsByType[$type];

        return $this->container->has($handlerServiceId);
    }
}
