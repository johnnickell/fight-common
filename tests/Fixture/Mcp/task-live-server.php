<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\Symfony\McpResponseFactory;
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
$f = new TaskLiveFixture($db, mixed: ($_GET['mixed'] ?? '') === '1', lifetime: (int) ($_GET['lifetime'] ?? 8), scope: $scope);
$f->policy = static function () use ($directory, $scope): bool { clearstatcache(); return !file_exists($directory.'/revoke-'.$scope); };
$f->task->onRead = static function () use ($f, $directory): void {
    clearstatcache();
    if (file_exists($directory.'/source-failure')) { throw new RuntimeException('PRIVATE TASK SOURCE'); }
    $f->task->readAllowed = !file_exists($directory.'/deny-read');
    $f->task->discloseAllowed = !file_exists($directory.'/deny-payload');
};
$f->onGuard = static function () use ($f, $directory): void {
    clearstatcache();
    $f->task->readAllowed = !file_exists($directory.'/deny-read');
    $f->task->discloseAllowed = !file_exists($directory.'/deny-payload');
};
$faultTriggered = false;
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
if ($composition === 'slim') {
    $app = new App($factory); $app->post('/mcp', fn (ServerRequestInterface $request) => $f->endpoint->handler->handle($request)); $response = $app->handle($request);
} elseif ($composition === 'yii') {
    $collector = new RouteCollector(); $collector->addRoute(Route::post('/mcp')->action(fn (ServerRequestInterface $request) => $f->endpoint->handler->handle($request)));
    $router = new Router(new UrlMatcher(new RouteCollection($collector)), $factory, new MiddlewareFactory(new Container()), new CurrentRoute()); $response = $router->process($request, $f->endpoint->handler);
} else { $response = $f->endpoint->handler->handle($request); }
$emitter = new McpResponseEmitter();
if (isset($_GET['abrupt'])) {
    $emitter->prepare($response); header('Content-Type: text/event-stream');
    echo $response->getBody()->read(65536); flush(); posix_kill(getmypid(), SIGKILL);
}
if ($composition === 'symfony' || $composition === 'laravel') { new McpResponseFactory()->fromResponse($response)->send(); }
elseif ($composition === 'codeigniter') { require __DIR__.'/codeigniter.php'; new McpResponse($response)->send(); }
else { $emitter->emit($response); }
file_put_contents($directory.'/closed-'.getmypid().'.json', json_encode(['pid' => getmypid(), 'sapi' => PHP_SAPI, 'buffering' => ini_get('output_buffering'), 'compression' => ini_get('zlib.output_compression'), 'diagnostics' => $f->endpoint->diagnostics, 'faultTriggered' => $faultTriggered, 'guardedStates' => $f->guardedStates, 'guardReleased' => !$f->guarded], JSON_THROW_ON_ERROR));
