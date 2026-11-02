<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Task;

use Closure;
use Doctrine\DBAL\Connection;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionAdmission;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Test\Common\Fixture\Mcp\TaskEndpoint;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use GuzzleHttp\Psr7\ServerRequest;

final class TaskLiveFixture implements McpSubscriptionAdmission
{
    public readonly TaskFixture $task;
    public readonly SubscriptionFixture $notices;
    public readonly TaskEndpoint $endpoint;
    public bool $allowed = true;
    public ?Closure $policy = null;

    public function __construct(Connection $db, int $frameBytes = 65536, bool $mixed = false, ?McpSubscriptionStore $store = null, int $lifetime = 1, string $scope = 'scope')
    {
        $this->task = new TaskFixture($db);
        $this->notices = new SubscriptionFixture($db);
        $this->endpoint = new TaskEndpoint($this->task, subscriptions: fn (McpTasks $tasks) => new McpSubscriptions(
            $this->notices->queries, $store ?? $this->notices->store, $this, NoticeSelection::create([$scope]),
            tools: $mixed, resources: false, lifetimeSeconds: $lifetime, waitMicroseconds: 10000,
            maxFrameBytes: $frameBytes, tasks: $tasks
        ), scope: $scope);
    }

    public function subscriber(): string { return 'task-subscriber'; }
    public function allows(string $subscriber, string $filter, ?string $uri, ?Notice $notice): bool { return $this->allowed && ($this->policy === null || ($this->policy)($filter, $uri, $notice)); }
    public static function request(array $filters = ['taskIds' => [TaskFixture::ID]], int|string $id = 7, mixed $capabilities = null): ServerRequest
    {
        $request = TaskEndpoint::request('subscriptions/listen', ['notifications' => (object) $filters], $capabilities);
        $data = json_decode((string) $request->getBody(), flags: JSON_THROW_ON_ERROR);
        $data->id = $id;
        return $request->withBody((new \GuzzleHttp\Psr7\HttpFactory())->createStream(json_encode($data, JSON_THROW_ON_ERROR)));
    }
}
