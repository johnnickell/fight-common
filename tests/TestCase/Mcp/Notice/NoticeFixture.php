<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Notice;

use Closure;
use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeStore;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Mcp\Notice\CommandHandler\RecordNoticeHandler;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRejected;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAdmission;
use Fight\Common\Application\Mcp\Notice\Service\NoticeRecorder;
use Fight\Common\Domain\Mcp\Notice\Command\RecordNotice;
use Fight\Common\Domain\Mcp\Notice\Notice;

final class NoticeFixture implements NoticeAdmission
{
    public bool $allowed = true;
    public int $admissions = 0;
    public array $references = ['subject-agent', 'recipient-agent', 'other-recipient', 'author-agent', 'command-id', 'event-id', 'action-id'];
    public ?Closure $onAdmit = null;
    public readonly DbalNoticeStore $store;
    public readonly RoutingCommandBus $bus;
    public readonly SimpleEventDispatcher $events;

    public function __construct(Connection $connection)
    {
        $this->store = new DbalNoticeStore($connection);
        $this->events = new SimpleEventDispatcher();
        $router = new InMemoryCommandRouter();
        $router->registerHandler(RecordNotice::class, new RecordNoticeHandler(new NoticeRecorder($this->store, $this, $this->events)));
        $this->bus = new RoutingCommandBus($router);
    }

    public static function data(array $changes = []): array
    {
        return array_replace([
            'version' => 1, 'id' => 'notice-1', 'producer' => 'producer', 'scope' => 'scope',
            'topic' => 'catalog/tools', 'type' => 'catalog-changed', 'occurred_at' => '2026-10-25T01:02:03.12Z',
            'metadata' => ['app/revision' => 3, 'app/ready' => true, 'app/label' => 'safe', 'app/unknown' => null],
            'relationships' => ['subject' => 'subject-agent', 'recipients' => ['recipient-agent', 'other-recipient'], 'author' => 'author-agent', 'command' => 'command-id', 'event' => 'event-id', 'action' => 'action-id']
        ], $changes);
    }

    public function record(array $changes = []): void
    {
        $this->bus->execute(RecordNotice::fromArray(self::data($changes)));
    }

    public function admit(Notice $notice): void
    {
        ++$this->admissions;
        if (!$this->allowed || !in_array($notice->producer(), ['producer', 'producer-2'], true) || !in_array($notice->scope(), ['scope', 'scope-2'], true)) {
            throw new NoticeRejected();
        }
        $relations = $notice->relationships()->toArray();
        $references = $relations['recipients'] ?? [];
        unset($relations['recipients']);
        foreach ($relations as $reference) { if ($reference !== null) { $references[] = $reference; } }
        foreach ($references as $reference) {
            if (!in_array($reference, $this->references, true)) { throw new NoticeRejected(); }
        }
        ($this->onAdmit ?? static function (): void {})($notice);
    }
}
