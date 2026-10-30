<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\Symfony\McpResponseFactory;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\Fixture\Mcp\TaskEndpoint;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use GuzzleHttp\Psr7\ServerRequest;

require dirname(__DIR__, 3).'/vendor/autoload.php';

ignore_user_abort(true);
$directory = getenv('FIGHT_TASK_HTTP_DIRECTORY');
$config = json_decode(file_get_contents($directory.'/config.json'), true, flags: JSON_THROW_ON_ERROR);
$connection = DriverManager::getConnection($config['params']);
$fixture = new TaskFixture($connection);
$flags = file_exists($directory.'/flags.json') ? json_decode(file_get_contents($directory.'/flags.json'), true, flags: JSON_THROW_ON_ERROR) : [];
foreach ($flags as $key => $value) { $fixture->$key = $value; }
$endpoint = new TaskEndpoint($fixture);
$scenario = $_GET['scenario'] ?? '';
if ($scenario === 'submission-failure') { $endpoint->tool->onStart = static fn () => throw new RuntimeException('private submission failure'); }
if ($scenario === 'publication-failure') { $fixture->events->addHandler(ClassName::underscore(TaskChanged::class), static fn () => throw new RuntimeException('private publication failure')); }
$response = $endpoint->handler->handle(ServerRequest::fromGlobals());
$composition = $_GET['composition'] ?? 'psr';
if ($composition === 'symfony') { new McpResponseFactory()->fromResponse($response)->send(); }
elseif ($composition === 'codeigniter') { require __DIR__.'/codeigniter.php'; new McpResponse($response)->send(); }
else { new McpResponseEmitter()->emit($response); }
file_put_contents($directory.'/requests.jsonl', json_encode(['pid' => getmypid(), 'scenario' => $scenario, 'composition' => $composition, 'starts' => $endpoint->tool->starts, 'diagnostics' => $endpoint->diagnostics, 'sapi' => PHP_SAPI], JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
