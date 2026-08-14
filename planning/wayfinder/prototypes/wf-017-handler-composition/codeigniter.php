<?php

declare(strict_types=1);

namespace Config {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/shared.php';

    use CodeIgniter\Config\BaseService;
    use Prototype\HandlerComposition\CurrentSessionQueryHandler;
    use Prototype\HandlerComposition\DuplicateLoginCommandHandler;
    use Prototype\HandlerComposition\LoginCommandHandler;
    use Prototype\HandlerComposition\UserRegisteredSubscriber;

    final class Services extends BaseService
    {
        /** @return array{commands: list<object>, queries: list<object>, events: list<object>} */
        public static function handlerCatalog(string $scenario = 'valid', bool $getShared = true): array
        {
            if ($getShared) {
                return static::getSharedInstance('handlerCatalog', $scenario);
            }

            $commands = [new LoginCommandHandler()];
            $queries = [new CurrentSessionQueryHandler()];
            $events = [new UserRegisteredSubscriber()];
            if ($scenario === 'missing') {
                $commands = [];
            } elseif ($scenario === 'ambiguous') {
                $commands[] = new DuplicateLoginCommandHandler();
            } elseif ($scenario === 'duplicate-subscriber') {
                $events[] = $events[0];
            }

            return ['commands' => $commands, 'queries' => $queries, 'events' => $events];
        }
    }
}

namespace {
    use Composer\InstalledVersions;
    use Config\Services;

    use function Prototype\HandlerComposition\runLane;

    define('APPPATH', __DIR__ . '/codeigniter-app/');
    define('ENVIRONMENT', 'testing');

    runLane(
        'CodeIgniter',
        ['codeigniter4/framework' => InstalledVersions::getPrettyVersion('codeigniter4/framework')],
        ['style' => 'project-owned Config\\Services factory', 'scan' => 'none'],
        static fn (string $scenario): array => Services::handlerCatalog($scenario, false),
    );
}
