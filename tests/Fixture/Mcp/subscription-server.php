<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\Symfony\McpResponseFactory;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
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

$directory = getenv('MCP_SUBSCRIPTION_DIRECTORY');
$scope = getenv('MCP_SUBSCRIPTION_SCOPE');
$params = json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
$db = DriverManager::getConnection($params);
if ($db->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\SQLitePlatform) { $db->executeStatement('PRAGMA busy_timeout = 3000'); }
$fixture = new SubscriptionFixture($db, $scope, (int) ($_GET['lifetime'] ?? 2), (int) ($_GET['frameBytes'] ?? 16384), prompts: ($_GET['prompts'] ?? '0') === '1', servePrompts: ($_GET['servePrompts'] ?? '0') === '1');
$fixture->policy = static function ($filter, $uri, $notice) use ($directory, $scope): bool {
    clearstatcache();
    if ($filter === 'promptsListChanged' && ($notice?->metadata()->toArray()['app/concealed'] ?? false)) { return false; }
    return !file_exists($directory.'/revoke-'.$scope) && ($filter !== 'promptsListChanged' || !file_exists($directory.'/deny-prompt-signals-'.$scope));
};
$fixture->promptPolicy = static function () use ($directory, $scope): bool { clearstatcache(); return !file_exists($directory.'/hide-prompts-'.$scope); };
$fixture->queryPolicy = static function () use ($directory): void {
    clearstatcache();
    if (file_exists($directory.'/source-failure')) { throw new RuntimeException('PRIVATE SOURCE SENTINEL'); }
    if (file_exists($directory.'/pause-source')) {
        file_put_contents($directory.'/source-paused', 'yes');
        $deadline = microtime(true) + 3;
        while (file_exists($directory.'/pause-source')) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Fixture barrier timeout'); }
            usleep(10000); clearstatcache();
        }
    }
};
$composition = $_GET['composition'] ?? 'psr';
$request = ServerRequest::fromGlobals(); $emitter = new McpResponseEmitter(); $factory = new HttpFactory();
if ($composition === 'slim') {
    $app = new App($factory); $app->post('/mcp', fn (ServerRequestInterface $request) => $fixture->endpoint->handle($request)); $response = $app->handle($request);
} elseif ($composition === 'yii') {
    $collector = new RouteCollector(); $collector->addRoute(Route::post('/mcp')->action(fn (ServerRequestInterface $request) => $fixture->endpoint->handle($request)));
    $router = new Router(new UrlMatcher(new RouteCollection($collector)), $factory, new MiddlewareFactory(new Container()), new CurrentRoute()); $response = $router->process($request, $fixture->endpoint);
} else { $response = $fixture->endpoint->handle($request); }
try {
    if (isset($_GET['buffered'])) { ob_start(); }
    if (isset($_GET['abrupt'])) {
        // Simulates external process death after a real opening/write without executing PHP finally blocks.
        echo ''; $emitter->prepare($response);
        header('Content-Type: text/event-stream');
        echo $response->getBody()->read(65536); flush();
        posix_kill(getmypid(), SIGKILL);
    }
    if ($composition === 'symfony' || $composition === 'laravel') {
        // Laravel and Symfony share the package's actual native StreamedResponse translation.
        new McpResponseFactory()->fromResponse($response)->send();
    } elseif ($composition === 'codeigniter') {
        require __DIR__.'/codeigniter.php'; new McpResponse($response)->send();
    } else { $emitter->emit($response); }
} catch (RuntimeException $failure) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(500);
} finally {
    file_put_contents($directory.'/closed-'.$scope.'.json', json_encode(['time' => microtime(true), 'diagnostics' => $fixture->diagnostics, 'sapi' => PHP_SAPI, 'buffering' => ini_get('output_buffering'), 'compression' => ini_get('zlib.output_compression'), 'prompt_calls' => $fixture->visiblePrompt->calls, 'hidden_prompt_calls' => $fixture->hiddenPrompt->calls, 'prompt_metadata_calls' => $fixture->visiblePrompt->metadataCalls], JSON_THROW_ON_ERROR));
}
