<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Fight;

use Fight\Common\Adapter\HttpClient\Psr18\Psr18Client;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Application\Service\Container;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use Psr\Http\Client\ClientInterface;

/**
 * Class ContainerCapabilityRegistrar
 */
final class ContainerCapabilityRegistrar
{
    /**
     * Registers the selected synchronous messaging capability
     *
     * @param Container $container The service container
     * @param array<string, callable(Container): mixed> $services Shared service factories
     * @param array<string, callable(Container): mixed> $factories Transient service factories
     * @param array<string, string> $commandHandlers Command class to service ID map
     * @param array<string, string> $queryHandlers Query class to service ID map
     * @param array<class-string, string> $eventSubscribers Subscriber class to service ID map
     * @param array<string, list<string>> $filters Pipeline service ID to filter service IDs map
     * @param array<string, string> $collaborators Collaborator key to service ID map
     */
    public static function registerMessaging(
        Container $container,
        array $services,
        array $factories,
        array $commandHandlers,
        array $queryHandlers,
        array $eventSubscribers,
        array $filters,
        array $collaborators
    ): void {
        self::registerServices($container, $services, $factories);

        if ($commandHandlers !== []) {
            /**
             * @var ServiceAwareCommandRouter $commandRouter
             */
            $commandRouter = $container->get($collaborators['command.router']);
            $commandRouter->registerHandlers($commandHandlers);
        }

        if ($queryHandlers !== []) {
            /**
             * @var ServiceAwareQueryRouter $queryRouter
             */
            $queryRouter = $container->get($collaborators['query.router']);
            $queryRouter->registerHandlers($queryHandlers);
        }

        if ($eventSubscribers !== []) {
            /**
             * @var ServiceAwareEventDispatcher $eventDispatcher
             */
            $eventDispatcher = $container->get($collaborators['event.dispatcher']);
            foreach ($eventSubscribers as $subscriberClass => $serviceId) {
                $eventDispatcher->registerService($subscriberClass, $serviceId);
            }
        }

        foreach ($filters as $pipelineServiceId => $filterServiceIds) {
            $pipeline = $container->get($pipelineServiceId);
            foreach ($filterServiceIds as $filterServiceId) {
                $filter = $container->get($filterServiceId);
                if ($pipeline instanceof CommandPipeline) {
                    assert($filter instanceof CommandFilter);
                    $pipeline->addFilter($filter);
                } elseif ($pipeline instanceof QueryPipeline) {
                    assert($filter instanceof QueryFilter);
                    $pipeline->addFilter($filter);
                }
            }
        }
    }

    /**
     * Registers selected template helpers with explicit collaborators
     *
     * @param Container $container The service container
     * @param array<string, callable(Container): mixed> $services Shared helper service factories
     * @param array<string, callable(Container): mixed> $collaborators Shared collaborator factories
     * @param array<string, list<string>> $helpers Template-engine service ID to helper service IDs map
     */
    public static function registerTemplateHelpers(
        Container $container,
        array $services,
        array $collaborators,
        array $helpers
    ): void {
        self::registerServices($container, $services, $collaborators);

        foreach ($helpers as $engineServiceId => $helperServiceIds) {
            /**
             * @var TemplateEngine $templateEngine
             */
            $templateEngine = $container->get($engineServiceId);
            foreach ($helperServiceIds as $helperServiceId) {
                $helper = $container->get($helperServiceId);
                assert($helper instanceof TemplateHelper);
                $templateEngine->addHelper($helper);
            }
        }
    }

    /**
     * Registers one configured Fight transport and its PSR-18 view
     *
     * @param Container $container The service container
     * @param callable(Container): HttpClient $transportFactory The configured transport factory
     */
    public static function registerHttpClient(Container $container, callable $transportFactory): void
    {
        $container->set(HttpClient::class, $transportFactory);
        $container->set(
            ClientInterface::class,
            static function (Container $container): Psr18Client {
                $httpClient = $container->get(HttpClient::class);
                assert($httpClient instanceof HttpClient);

                return new Psr18Client($httpClient);
            }
        );
    }

    /**
     * Registers explicit shared and transient service factories
     *
     * @param Container $container The service container
     * @param array<string, callable(Container): mixed> $sharedServiceFactories Shared service factories
     * @param array<string, callable(Container): mixed> $transientServiceFactories Transient service factories
     */
    private static function registerServices(
        Container $container,
        array $sharedServiceFactories,
        array $transientServiceFactories
    ): void {
        foreach ($sharedServiceFactories as $serviceId => $serviceFactory) {
            $container->set($serviceId, $serviceFactory);
        }

        foreach ($transientServiceFactories as $serviceId => $serviceFactory) {
            $container->factory($serviceId, $serviceFactory);
        }
    }
}
