<?php

declare(strict_types=1);

use Fight\Common\Adapter\Http\CodeIgniter\McpResponse;
use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Fight\Common\Adapter\Http\Symfony\McpResponseFactory;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Test\Common\Fixture\Mcp\ProgressEndpoint;
use Fight\Test\Common\Fixture\Mcp\ProgressTool;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\ResponseFactory;
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

$directory = getenv('MCP_WIRE_DIRECTORY');
$composition = $_GET['composition'] ?? 'psr';
$scenario = $_GET['scenario'] ?? 'success';
$record = static function (array $event) use ($directory): void {
    file_put_contents($directory.'/events.jsonl', json_encode($event + ['time' => microtime(true)], JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
};
$tool = new ProgressTool(static function (McpProgressReporter $progress) use ($scenario, $record): McpToolOutput {
    $record(['event' => 'start', 'reporter' => $progress::class]);
    for ($index = 0; $index < ($scenario === 'disconnect' ? 20 : 2); ++$index) {
        $progress->report($index, message: 'Working');
        if ($progress->isCancelled()) {
            $record(['event' => 'cancelled', 'at' => $index]);
            $progress->report($index + 100, message: 'must not reach client');
            $record(['event' => 'cooperative-stop']);
            return McpToolOutput::structured('cancelled-not-delivered');
        }
        usleep(150000);
    }
    $record(['event' => 'finish']);
    if ($scenario === 'expected') { throw new RuntimeException('private expected detail'); }
    if ($scenario === 'unexpected') { throw new LogicException('private unexpected detail'); }
    return McpToolOutput::structured('done');
});
$fixture = new ProgressEndpoint($tool, $scenario !== 'legacy');
$factory = new HttpFactory();
$request = ServerRequest::fromGlobals();
$emitter = new McpResponseEmitter();
if ($composition === 'slim') {
    $app = new App($factory);
    $app->post('/mcp', fn(ServerRequestInterface $request) => $fixture->handler->handle($request));
    $response = $app->handle($request);
} elseif ($composition === 'yii') {
    $collector = new RouteCollector();
    $collector->addRoute(Route::post('/mcp')->action(fn(ServerRequestInterface $request) => $fixture->handler->handle($request)));
    $router = new Router(new UrlMatcher(new RouteCollection($collector)), $factory,
        new MiddlewareFactory(new Container()), new CurrentRoute());
    $response = $router->process($request, $fixture->handler);
} else {
    $response = $fixture->handler->handle($request);
}

try {
    if ($scenario === 'buffered') { ob_start(); }
    if ($composition === 'symfony') {
        new McpResponseFactory()->fromResponse($response)->send();
    } elseif ($composition === 'laravel') {
        // Laravel's stream callback yields the same native Symfony StreamedResponse, not eventStream's sentinel.
        $emitter->prepare($response);
        $views = new class implements Factory {
            public function exists($view) {} public function file($path, $data = [], $mergeData = []) {}
            public function make($view, $data = [], $mergeData = []) {} public function share($key, $value = null) {}
            public function composer($views, $callback) {} public function creator($views, $callback) {}
            public function addNamespace($namespace, $hints) {} public function replaceNamespace($namespace, $hints) {}
        };
        $redirector = new Illuminate\Routing\Redirector(new Illuminate\Routing\UrlGenerator(new Illuminate\Routing\RouteCollection(), Illuminate\Http\Request::create('/')));
        $native = new ResponseFactory($views, $redirector)->stream(fn() => $emitter->emitBody($response), $response->getStatusCode(), $response->getHeaders());
        $native->send();
    } elseif ($composition === 'codeigniter') {
        require __DIR__.'/codeigniter.php';
        new McpResponse($response)->send();
    } else {
        $emitter->emit($response);
    }
} catch (RuntimeException $exception) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(500);
    $record(['event' => 'runtime-rejected', 'message' => $exception->getMessage()]);
} finally {
    $record(['event' => 'closed', 'calls' => $tool->calls, 'diagnostics' => count($fixture->diagnostics),
        'sapi' => PHP_SAPI, 'php' => PHP_VERSION, 'buffering' => ini_get('output_buffering'),
        'compression' => ini_get('zlib.output_compression')]);
}
