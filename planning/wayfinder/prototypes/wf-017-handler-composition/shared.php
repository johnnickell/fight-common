<?php

declare(strict_types=1);

namespace Prototype\HandlerComposition;

use LogicException;
use Psr\Container\ContainerInterface;

interface CommandHandler
{
    public static function message(): string;

    public function handle(): string;
}

interface QueryHandler
{
    public static function message(): string;

    public function handle(): string;
}

interface EventSubscriber
{
    /** @return list<string> */
    public static function events(): array;

    public function receive(string $event): string;
}

final class LoginCommand {}

final class CurrentSessionQuery {}

final class UserRegistered {}

final class LoginCommandHandler implements CommandHandler
{
    public static function message(): string
    {
        return LoginCommand::class;
    }

    public function handle(): string
    {
        return 'login-command-handled';
    }
}

final class DuplicateLoginCommandHandler implements CommandHandler
{
    public static function message(): string
    {
        return LoginCommand::class;
    }

    public function handle(): string
    {
        return 'duplicate-login-command-handled';
    }
}

final class CurrentSessionQueryHandler implements QueryHandler
{
    public static function message(): string
    {
        return CurrentSessionQuery::class;
    }

    public function handle(): string
    {
        return 'current-session-query-handled';
    }
}

final class UserRegisteredSubscriber implements EventSubscriber
{
    public static function events(): array
    {
        return [UserRegistered::class];
    }

    public function receive(string $event): string
    {
        return 'received:' . $event;
    }
}

final readonly class ServiceReference
{
    public function __construct(
        public string $id,
        public object $service,
    ) {}
}

final readonly class ResolvedMap
{
    /**
     * @param array<class-string, ServiceReference> $commands
     * @param array<class-string, ServiceReference> $queries
     * @param array<class-string, list<ServiceReference>> $events
     */
    public function __construct(
        public array $commands,
        public array $queries,
        public array $events,
    ) {}

    /** @return array<string, mixed> */
    public function inspect(): array
    {
        return [
            'commands' => mapSingleHandlers($this->commands),
            'queries' => mapSingleHandlers($this->queries),
            'events' => array_map(
                static fn (array $references): array => array_map(
                    static fn (ServiceReference $reference): string => $reference->id,
                    $references,
                ),
                $this->events,
            ),
        ];
    }
}

/**
 * @param iterable<string, object>|iterable<int, object> $commandHandlers
 * @param iterable<string, object>|iterable<int, object> $queryHandlers
 * @param iterable<string, object>|iterable<int, object> $eventSubscribers
 * @param list<class-string> $expectedCommands
 * @param list<class-string> $expectedQueries
 * @param list<class-string> $expectedEvents
 */
function compileResolvedMap(
    iterable $commandHandlers,
    iterable $queryHandlers,
    iterable $eventSubscribers,
    array $expectedCommands = [LoginCommand::class],
    array $expectedQueries = [CurrentSessionQuery::class],
    array $expectedEvents = [UserRegistered::class],
): ResolvedMap {
    $commands = compileSingleHandlers($commandHandlers, CommandHandler::class, 'command');
    $queries = compileSingleHandlers($queryHandlers, QueryHandler::class, 'query');
    $events = [];
    $seenSubscribers = [];

    foreach ($eventSubscribers as $key => $subscriber) {
        if (!$subscriber instanceof EventSubscriber) {
            throw new LogicException('Event subscriber registration has the wrong type.');
        }

        $id = serviceId($key, $subscriber);
        $subscriberClass = $subscriber::class;
        if (isset($seenSubscribers[$subscriberClass])) {
            throw new LogicException(sprintf('Duplicate event subscriber registration "%s".', $subscriberClass));
        }
        $seenSubscribers[$subscriberClass] = true;

        foreach ($subscriber::events() as $event) {
            $events[$event][] = new ServiceReference($id, $subscriber);
        }
    }

    assertComplete($commands, $expectedCommands, 'command');
    assertComplete($queries, $expectedQueries, 'query');
    assertComplete($events, $expectedEvents, 'event');

    ksort($commands);
    ksort($queries);
    ksort($events);

    return new ResolvedMap($commands, $queries, $events);
}

/**
 * @param callable(string): array{commands: iterable, queries: iterable, events: iterable} $resolve
 * @param array<string, string|null> $versions
 * @param array<string, mixed> $composition
 */
function runLane(string $framework, array $versions, array $composition, callable $resolve): array
{
    $valid = $resolve('valid');
    $map = compileResolvedMap($valid['commands'], $valid['queries'], $valid['events']);

    $command = $map->commands[LoginCommand::class]->service;
    $query = $map->queries[CurrentSessionQuery::class]->service;
    $subscriber = $map->events[UserRegistered::class][0]->service;

    if (!$command instanceof CommandHandler || !$query instanceof QueryHandler || !$subscriber instanceof EventSubscriber) {
        throw new LogicException('Resolved services crossed the wrong handler boundary.');
    }

    $failures = [];
    foreach (['missing', 'ambiguous', 'duplicate-subscriber'] as $scenario) {
        try {
            $candidate = $resolve($scenario);
            compileResolvedMap($candidate['commands'], $candidate['queries'], $candidate['events']);
            throw new LogicException(sprintf('Scenario "%s" unexpectedly compiled.', $scenario));
        } catch (LogicException $exception) {
            if (str_contains($exception->getMessage(), 'unexpectedly compiled')) {
                throw $exception;
            }
            $failures[$scenario] = $exception->getMessage();
        }
    }

    $receipt = [
        'framework' => $framework,
        'versions' => $versions,
        'composition' => $composition,
        'resolved_map' => $map->inspect(),
        'dispatch' => [
            'command' => $command->handle(),
            'query' => $query->handle(),
            'event' => $subscriber->receive(UserRegistered::class),
        ],
        'boot_failures' => $failures,
        'portable_application_classes' => [
            LoginCommandHandler::class,
            CurrentSessionQueryHandler::class,
            UserRegisteredSubscriber::class,
        ],
        'pass' => count($failures) === 3,
    ];

    $path = getenv('PROTOTYPE_RECEIPT');
    if (!is_string($path) || $path === '') {
        throw new LogicException('PROTOTYPE_RECEIPT is required.');
    }
    file_put_contents($path, json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
    echo sprintf("%s: PASS\n", $framework);

    return $receipt;
}

/**
 * @template T of object
 * @param iterable<string, object>|iterable<int, object> $handlers
 * @param class-string<T> $type
 * @return array<class-string, ServiceReference>
 */
function compileSingleHandlers(iterable $handlers, string $type, string $kind): array
{
    $compiled = [];
    foreach ($handlers as $key => $handler) {
        if (!$handler instanceof $type) {
            throw new LogicException(sprintf('%s handler registration has the wrong type.', ucfirst($kind)));
        }
        $message = $handler::message();
        if (isset($compiled[$message])) {
            throw new LogicException(sprintf(
                'Ambiguous %s handler for "%s": "%s" and "%s".',
                $kind,
                $message,
                $compiled[$message]->id,
                serviceId($key, $handler),
            ));
        }
        $compiled[$message] = new ServiceReference(serviceId($key, $handler), $handler);
    }

    return $compiled;
}

/** @param array<class-string, mixed> $actual @param list<class-string> $expected */
function assertComplete(array $actual, array $expected, string $kind): void
{
    $missing = array_values(array_diff($expected, array_keys($actual)));
    if ($missing !== []) {
        throw new LogicException(sprintf('Missing %s registration for "%s".', $kind, $missing[0]));
    }
}

/** @param string|int $key */
function serviceId(string|int $key, object $service): string
{
    return is_string($key) ? $key : $service::class;
}

/** @param array<class-string, ServiceReference> $handlers @return array<class-string, string> */
function mapSingleHandlers(array $handlers): array
{
    return array_map(static fn (ServiceReference $reference): string => $reference->id, $handlers);
}

/** @return list<object> */
function containerServices(ContainerInterface $container, string ...$ids): array
{
    return array_map(static fn (string $id): object => $container->get($id), $ids);
}
