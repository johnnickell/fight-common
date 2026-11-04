<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\Symfony\McpResponseFactory;
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
$db = DriverManager::getConnection($config['params']);
if ($db->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\SQLitePlatform) { $db->executeStatement('PRAGMA busy_timeout = 3000'); }
$scope = $_GET['scope'] ?? 'scope';
$requestKey = $_SERVER['HTTP_X_FIGHT_TEST_REQUEST'] ?? bin2hex(random_bytes(8));
$diagnostics = new TaskLiveDiagnostics($db);
$diagnostics->event('request-start', ['request' => $requestKey, 'query' => $_SERVER['QUERY_STRING'] ?? '', 'lifetimeSeconds' => (int) ($_GET['lifetime'] ?? 8), 'internalDeadline' => 'unavailable; execution starts lazily inside emit', 'internalStopReason' => 'unavailable']);
$f = null;
$faultTriggered = false;
register_shutdown_function(static function () use ($directory, $requestKey, $diagnostics, $db, &$f, &$faultTriggered): void {
    $diagnostics->event('shutdown', ['lastPhpError' => error_get_last()]);
    // A unique request file cannot be overwritten by a later intentional source-failure request on this PID.
    file_put_contents($directory.'/closed-'.getmypid().'-'.$requestKey.'.json', json_encode([
        'request' => $requestKey, 'pid' => getmypid(), 'sapi' => PHP_SAPI,
        'buffering' => ini_get('output_buffering'), 'compression' => ini_get('zlib.output_compression'),
        'diagnostics' => $f?->endpoint->diagnostics ?? [], 'faultTriggered' => $faultTriggered,
        'guardedStates' => $f?->guardedStates ?? [], 'guardReleased' => !($f?->guarded ?? false),
        'events' => $diagnostics->events, 'heartbeats' => $diagnostics->heartbeats,
        'rows' => TaskLiveDiagnostics::rows($db)
    ], JSON_THROW_ON_ERROR));
});
$f = new TaskLiveFixture($db, mixed: ($_GET['mixed'] ?? '') === '1', store: $diagnostics, lifetime: (int) ($_GET['lifetime'] ?? 8), scope: $scope);
$f->endpoint->onDiagnostic = static fn (Throwable $failure) => $diagnostics->event('private-error', ['chain' => TaskLiveDiagnostics::chain($failure)]);
$f->policy = static function () use ($directory, $scope): bool { clearstatcache(); return !file_exists($directory.'/revoke-'.$scope); };
$f->task->onRead = static function () use ($f, $directory, $diagnostics): void {
    clearstatcache();
    if (file_exists($directory.'/source-failure')) { $diagnostics->event('source-failure-injected'); throw new RuntimeException('PRIVATE TASK SOURCE'); }
    $f->task->readAllowed = !file_exists($directory.'/deny-read');
    $f->task->discloseAllowed = !file_exists($directory.'/deny-payload');
};
$f->onGuard = static function () use ($f, $directory): void {
    clearstatcache();
    $f->task->readAllowed = !file_exists($directory.'/deny-read');
    $f->task->discloseAllowed = !file_exists($directory.'/deny-payload');
};
if (isset($_GET['fault'])) {
    $comparisons = 0;
    $f->task->onComparison = static function () use ($f, $directory, &$comparisons, &$faultTriggered): void {
        ++$comparisons; clearstatcache();
        $phase = $_GET['phase'];
        if ($faultTriggered || !match ($phase) {
            'opening' => $comparisons === 3,
            'baseline' => $comparisons === 4,
            'update' => file_exists($directory.'/arm-fault')
        }) { return; }
        $faultTriggered = true;
        if ($_GET['fault'] === 'signal') { $f->allowed = false; return; }
        $row = $f->notices->db->fetchAssociative('SELECT registration_id, owner FROM fight_mcp_subscriptions WHERE closed_at = 0');
        $f->notices->store->close($row['registration_id'], $row['owner'], time());
        if ($_GET['fault'] === 'cleanup') { $f->notices->store->cleanup(time(), 16); }
    };
}
$request = ServerRequest::fromGlobals(); $composition = $_GET['composition'] ?? 'psr'; $factory = new HttpFactory();
$diagnostics->event('handle-enter', ['composition' => $composition, 'taskClockMilliseconds' => $f->task->now]);
if ($composition === 'slim') {
    $app = new App($factory); $app->post('/mcp', fn (ServerRequestInterface $request) => $f->endpoint->handler->handle($request)); $response = $app->handle($request);
} elseif ($composition === 'yii') {
    $collector = new RouteCollector(); $collector->addRoute(Route::post('/mcp')->action(fn (ServerRequestInterface $request) => $f->endpoint->handler->handle($request)));
    $router = new Router(new UrlMatcher(new RouteCollection($collector)), $factory, new MiddlewareFactory(new Container()), new CurrentRoute()); $response = $router->process($request, $f->endpoint->handler);
} else { $response = $f->endpoint->handler->handle($request); }
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
