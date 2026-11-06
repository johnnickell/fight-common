<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\Symfony\McpResponseFactory;
use Fight\Test\Common\TestCase\Mcp\LifecycleTables;
use Fight\Test\Common\TestCase\Mcp\Task\TaskLiveDiagnostics;
use Fight\Test\Common\TestCase\Mcp\Task\TaskLiveFixture;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Yiisoft\Di\Container;
use Yiisoft\Middleware\Dispatcher\MiddlewareFactory;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$directory = getenv('FIGHT_TASK_LIVE_DIRECTORY');
$config = json_decode(file_get_contents($directory.'/config.json'), true, flags: JSON_THROW_ON_ERROR);
$connection = DriverManager::getConnection($config['params']);
$tables = new LifecycleTables($config['tables'] ?? []);
if ($connection->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\SQLitePlatform) { $connection->executeStatement('PRAGMA busy_timeout = 3000'); }
$scope = $_GET['scope'] ?? 'scope';
$requestKey = $_SERVER['HTTP_X_FIGHT_TEST_REQUEST'] ?? bin2hex(random_bytes(8));
$diagnostics = new TaskLiveDiagnostics($connection, $tables);
$diagnostics->event('request-start', ['request' => $requestKey, 'query' => $_SERVER['QUERY_STRING'] ?? '', 'lifetimeSeconds' => (int) ($_GET['lifetime'] ?? 8), 'internalDeadline' => 'unavailable; execution starts lazily inside emit', 'internalStopReason' => 'unavailable']);
$fixture = null;
$faultTriggered = false;
register_shutdown_function(static function () use ($directory, $requestKey, $diagnostics, $connection, $tables, &$fixture, &$faultTriggered): void {
    $diagnostics->event('shutdown', ['lastPhpError' => error_get_last()]);
    // A unique request file cannot be overwritten by a later intentional source-failure request on this PID.
    file_put_contents($directory.'/closed-'.getmypid().'-'.$requestKey.'.json', json_encode([
        'request' => $requestKey, 'pid' => getmypid(), 'sapi' => PHP_SAPI,
        'buffering' => ini_get('output_buffering'), 'compression' => ini_get('zlib.output_compression'),
        'diagnostics' => $fixture?->endpoint->diagnostics ?? [], 'faultTriggered' => $faultTriggered,
        'guardedStates' => $fixture?->guardedStates ?? [], 'guardReleased' => !($fixture?->guarded ?? false),
        'events' => $diagnostics->events, 'heartbeats' => $diagnostics->heartbeats,
        'rows' => TaskLiveDiagnostics::rows($connection, $tables)
    ], JSON_THROW_ON_ERROR));
});
$fixture = new TaskLiveFixture($connection, mixed: ($_GET['mixed'] ?? '') === '1', subscriptionStore: $diagnostics, lifetime: (int) ($_GET['lifetime'] ?? 8), scope: $scope, tables: $tables);
$fixture->endpoint->onDiagnostic = static fn (Throwable $failure) => $diagnostics->event('private-error', ['chain' => TaskLiveDiagnostics::chain($failure)]);
$fixture->policy = static function () use ($directory, $scope): bool { clearstatcache(); return !file_exists($directory.'/revoke-'.$scope); };
$fixture->taskFixture->onRead = static function () use ($fixture, $directory, $diagnostics): void {
    clearstatcache();
    if (file_exists($directory.'/source-failure')) { $diagnostics->event('source-failure-injected'); throw new RuntimeException('PRIVATE TASK SOURCE'); }
    $fixture->taskFixture->readAllowed = !file_exists($directory.'/deny-read');
    $fixture->taskFixture->discloseAllowed = !file_exists($directory.'/deny-payload');
};
$fixture->onGuard = static function () use ($fixture, $directory): void {
    clearstatcache();
    $fixture->taskFixture->readAllowed = !file_exists($directory.'/deny-read');
    $fixture->taskFixture->discloseAllowed = !file_exists($directory.'/deny-payload');
};
if (isset($_GET['fault'])) {
    $comparisons = 0;
    $fixture->taskFixture->onComparison = static function () use ($fixture, $directory, $tables, &$comparisons, &$faultTriggered): void {
        ++$comparisons; clearstatcache();
        $phase = $_GET['phase'];
        if ($faultTriggered || !match ($phase) {
            'opening' => $comparisons === 3,
            'baseline' => $comparisons === 4,
            'update' => file_exists($directory.'/arm-fault')
        }) { return; }
        $faultTriggered = true;
        if ($_GET['fault'] === 'signal') { $fixture->allowed = false; return; }
        $row = $fixture->subscriptionFixture->connection->fetchAssociative($tables->sql($fixture->subscriptionFixture->connection, 'SELECT registration_id, owner FROM fight_mcp_subscriptions WHERE closed_at = 0'));
        $fixture->subscriptionFixture->subscriptionStore->close($row['registration_id'], $row['owner'], time());
        if ($_GET['fault'] === 'cleanup') { $fixture->subscriptionFixture->subscriptionStore->cleanup(time(), 16); }
    };
}
$request = ServerRequest::fromGlobals(); $composition = $_GET['composition'] ?? 'psr'; $factory = new HttpFactory();
$diagnostics->event('handle-enter', ['composition' => $composition, 'taskClockMilliseconds' => $fixture->taskFixture->now]);
if ($composition === 'slim') {
    $app = new App($factory); $app->post('/mcp', fn (ServerRequestInterface $request) => $fixture->endpoint->handler->handle($request)); $response = $app->handle($request);
} elseif ($composition === 'yii') {
    $routeCollector = new RouteCollector(); $routeCollector->addRoute(Route::post('/mcp')->action(fn (ServerRequestInterface $request) => $fixture->endpoint->handler->handle($request)));
    $router = new Router(new UrlMatcher(new RouteCollection($routeCollector)), $factory, new MiddlewareFactory(new Container()), new CurrentRoute()); $response = $router->process($request, $fixture->endpoint->handler);
} else { $response = $fixture->endpoint->handler->handle($request); }
$diagnostics->event('handle-return');
$emitter = new McpResponseEmitter();
$diagnostics->event('emit-enter');
if (isset($_GET['abrupt'])) {
    $emitter->prepare($response); header('Content-Type: text/event-stream');
    echo $response->getBody()->read(65536); flush(); posix_kill(getmypid(), SIGKILL);
}
if ($composition === 'symfony' || $composition === 'laravel') { new McpResponseFactory()->fromResponse($response)->send(); }
elseif ($composition === 'codeigniter') { require __DIR__.'/codeigniter.php'; new McpResponse($response)->send(); }
else { $emitter->emit($response); }
$diagnostics->event('emit-return');
