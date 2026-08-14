<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Illuminate\Container\Container;
use Prototype\HandlerComposition\CurrentSessionQueryHandler;
use Prototype\HandlerComposition\DuplicateLoginCommandHandler;
use Prototype\HandlerComposition\LoginCommandHandler;
use Prototype\HandlerComposition\UserRegisteredSubscriber;

use function Prototype\HandlerComposition\runLane;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/shared.php';

runLane(
    'Laravel',
    ['illuminate/container' => InstalledVersions::getPrettyVersion('illuminate/container')],
    ['style' => 'service-provider bindings plus native container tags', 'scan' => 'none'],
    static function (string $scenario): array {
        $container = new Container();
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

        foreach (array_unique([...$commands, ...$queries, ...$events]) as $class) {
            $container->singleton($class, $class);
        }
        $container->tag($commands, 'fight.command_handlers');
        $container->tag($queries, 'fight.query_handlers');
        $container->tag($events, 'fight.event_subscribers');

        return [
            'commands' => $container->tagged('fight.command_handlers'),
            'queries' => $container->tagged('fight.query_handlers'),
            'events' => $container->tagged('fight.event_subscribers'),
        ];
    },
);
