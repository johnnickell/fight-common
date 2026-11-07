<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Task;

use Closure;
use Doctrine\DBAL\Connection;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionAdmission;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\McpTaskDisclosureGuard;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Test\Common\Fixture\Mcp\TaskEndpoint;
use Fight\Test\Common\TestCase\Mcp\LifecycleTables;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use GuzzleHttp\Psr7\ServerRequest;
use RuntimeException;

final class TaskLiveFixture implements McpSubscriptionAdmission, McpTaskDisclosureGuard
{
    public readonly TaskFixture $taskFixture;
    public readonly SubscriptionFixture $subscriptionFixture;
    public readonly TaskEndpoint $endpoint;
    public bool $allowed = true;
    public ?Closure $policy = null;
    public ?Closure $onGuard = null;
    public bool $guarded = false;
    public array $guardedStates = [];
    public array $writers = [];

    public function __construct(Connection $connection, int $frameBytes = 65536, bool $mixed = false, ?McpSubscriptionStore $subscriptionStore = null, int $lifetime = 1, string $scope = 'scope', LifecycleTables $tables = new LifecycleTables())
    {
        $this->taskFixture = new TaskFixture($connection, $tables->task);
        $this->subscriptionFixture = new SubscriptionFixture($connection, noticeTables: $tables->notice, subscriptionTables: $tables->subscription);
        $this->endpoint = new TaskEndpoint($this->taskFixture, subscriptionCompositionFactory: fn (McpTasks $taskCapability) => new McpSubscriptions(
            $this->subscriptionFixture->queryBus, $subscriptionStore ?? $this->subscriptionFixture->subscriptionStore, $this, NoticeSelection::create([$scope]),
            tools: $mixed, resources: false, lifetimeSeconds: $lifetime, waitMicroseconds: 10000,
            maxFrameBytes: $frameBytes, taskCapability: $taskCapability, taskDisclosureGuard: $this
        ), scope: $scope);
    }

    public function run(string $subscriber, array $states, Closure $disclose): void
    {
        // Controlled consumer conformance fixture, not a production identity/policy/synchronization engine.
        ($this->onGuard ?? static fn () => null)($states, $disclose);
        foreach ($states as $id => $state) {
            if (!$this->allows($subscriber, 'taskIds', $id, null) || !$this->taskFixture->readAllowed || !$this->taskFixture->discloseAllowed) {
                throw new RuntimeException('Fixture disclosure unavailable');
            }
        }
        $this->guardedStates[] = array_keys($states);
        $this->guarded = true;
        try { $disclose(); } finally {
            $this->guarded = false;
            $writers = $this->writers; $this->writers = [];
            foreach ($writers as $writer) { $writer(); }
        }
    }

    public function writeAuthority(Closure $writer): void
    {
        if ($this->guarded) { $this->writers[] = $writer; } else { $writer(); }
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
