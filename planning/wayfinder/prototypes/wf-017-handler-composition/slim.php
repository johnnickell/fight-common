<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use DI\ContainerBuilder;
use Prototype\HandlerComposition\CurrentSessionQueryHandler;
use Prototype\HandlerComposition\DuplicateLoginCommandHandler;
use Prototype\HandlerComposition\LoginCommandHandler;
use Prototype\HandlerComposition\UserRegisteredSubscriber;
use Psr\Container\ContainerInterface;

use function DI\create;
use function Prototype\HandlerComposition\containerServices;
use function Prototype\HandlerComposition\runLane;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/shared.php';

runLane(
    'Slim',
    ['php-di/php-di' => InstalledVersions::getPrettyVersion('php-di/php-di')],
    ['style' => 'explicit PHP-DI definitions', 'scan' => 'none', 'autowiring' => false],
    static function (string $scenario): array {
        $commands = [LoginCommandHandler::class];
        $queries = [CurrentSessionQueryHandler::class];
        $events = [UserRegisteredSubscriber::class];
        if ($scenario === 'missing') {
            $commands = [];
        } elseif ($scenario === 'ambiguous') {
            $commands[] = DuplicateLoginCommandHandler::class;
        } elseif ($scenario === 'duplicate-subscriber') {
            $events[] = UserRegisteredSubscriber::class;
        }

        $builder = new ContainerBuilder();
        $builder->useAutowiring(false);
        $definitions = [];
        foreach (array_unique([...$commands, ...$queries, ...$events]) as $class) {
            $definitions[$class] = create($class);
        }
        $definitions['fight.command_handler_ids'] = $commands;
        $definitions['fight.query_handler_ids'] = $queries;
        $definitions['fight.event_subscriber_ids'] = $events;
        $builder->addDefinitions($definitions);
        $container = $builder->build();

        return [
            'commands' => resolveExplicitList($container, 'fight.command_handler_ids'),
            'queries' => resolveExplicitList($container, 'fight.query_handler_ids'),
            'events' => resolveExplicitList($container, 'fight.event_subscriber_ids'),
        ];
    },
);

/** @return list<object> */
function resolveExplicitList(ContainerInterface $container, string $id): array
{
    $ids = $container->get($id);
    if (!is_array($ids)) {
        throw new LogicException(sprintf('Explicit handler list "%s" is invalid.', $id));
    }
    return containerServices($container, ...$ids);
}
