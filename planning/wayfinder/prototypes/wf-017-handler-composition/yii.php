<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Prototype\HandlerComposition\CurrentSessionQueryHandler;
use Prototype\HandlerComposition\DuplicateLoginCommandHandler;
use Prototype\HandlerComposition\LoginCommandHandler;
use Prototype\HandlerComposition\UserRegisteredSubscriber;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Di\Reference\TagReference;

use function Prototype\HandlerComposition\runLane;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/shared.php';

runLane(
    'Yii',
    ['yiisoft/di' => InstalledVersions::getPrettyVersion('yiisoft/di')],
    ['style' => 'config/common/di tagged definitions', 'scan' => 'none'],
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

        $definitions = [];
        foreach (array_unique([...$commands, ...$queries, ...$events]) as $class) {
            $definitions[$class] = $class;
        }
        $container = new Container(
            ContainerConfig::create()
                ->withDefinitions($definitions)
                ->withTags([
                    'fight.command_handlers' => $commands,
                    'fight.query_handlers' => $queries,
                    'fight.event_subscribers' => $events,
                ]),
        );

        return [
            'commands' => $container->get(TagReference::id('fight.command_handlers')),
            'queries' => $container->get(TagReference::id('fight.query_handlers')),
            'events' => $container->get(TagReference::id('fight.event_subscribers')),
        ];
    },
);
