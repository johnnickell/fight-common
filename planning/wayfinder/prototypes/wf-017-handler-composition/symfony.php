<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Prototype\HandlerComposition\CommandHandler;
use Prototype\HandlerComposition\CurrentSessionQueryHandler;
use Prototype\HandlerComposition\DuplicateLoginCommandHandler;
use Prototype\HandlerComposition\EventSubscriber;
use Prototype\HandlerComposition\LoginCommandHandler;
use Prototype\HandlerComposition\QueryHandler;
use Prototype\HandlerComposition\UserRegisteredSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function Prototype\HandlerComposition\runLane;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/shared.php';

runLane(
    'Symfony',
    ['symfony/dependency-injection' => InstalledVersions::getPrettyVersion('symfony/dependency-injection')],
    ['style' => 'registerForAutoconfiguration plus native service tags', 'scan' => 'compile-time only'],
    static function (string $scenario): array {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(CommandHandler::class)->addTag('fight.command_handler');
        $container->registerForAutoconfiguration(QueryHandler::class)->addTag('fight.query_handler');
        $container->registerForAutoconfiguration(EventSubscriber::class)->addTag('fight.event_subscriber');

        $services = [
            LoginCommandHandler::class => LoginCommandHandler::class,
            CurrentSessionQueryHandler::class => CurrentSessionQueryHandler::class,
            UserRegisteredSubscriber::class => UserRegisteredSubscriber::class,
        ];
        if ($scenario === 'missing') {
            unset($services[LoginCommandHandler::class]);
        } elseif ($scenario === 'ambiguous') {
            $services[DuplicateLoginCommandHandler::class] = DuplicateLoginCommandHandler::class;
        } elseif ($scenario === 'duplicate-subscriber') {
            $services['prototype.duplicate_user_registered_subscriber'] = UserRegisteredSubscriber::class;
        }

        foreach ($services as $id => $class) {
            $definition = $container->register($id, $class);
            $definition->setAutoconfigured(true)->setAutowired(true)->setPublic(true);
        }
        $container->compile();

        return [
            'commands' => taggedSymfonyServices($container, 'fight.command_handler'),
            'queries' => taggedSymfonyServices($container, 'fight.query_handler'),
            'events' => taggedSymfonyServices($container, 'fight.event_subscriber'),
        ];
    },
);

/** @return array<string, object> */
function taggedSymfonyServices(ContainerBuilder $container, string $tag): array
{
    $services = [];
    foreach (array_keys($container->findTaggedServiceIds($tag, true)) as $id) {
        $service = $container->get($id);
        if (is_object($service)) {
            $services[$id] = $service;
        }
    }
    return $services;
}
