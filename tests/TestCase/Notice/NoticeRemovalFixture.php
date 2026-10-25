<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Notice;

use Closure;
use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Application\Notice\CommandHandler\RemoveNoticesHandler;
use Fight\Common\Application\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Notice\Service\NoticeRemovalAdmission;
use Fight\Common\Application\Notice\Service\NoticeRemover;
use Fight\Common\Domain\Notice\Command\RemoveNotices;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;

final class NoticeRemovalFixture implements NoticeRemovalAdmission
{
    public bool $allowed = true;
    public int $admissions = 0;
    public int $retainedAdmissions = 0;
    public array $deniedReferences = [];
    public ?Closure $onAdmit = null;
    public readonly NoticeFixture $recording;
    public readonly RoutingCommandBus $bus;

    public function __construct(Connection $connection)
    {
        $this->recording = new NoticeFixture($connection);
        $router = new InMemoryCommandRouter();
        $router->registerHandler(RemoveNotices::class, new RemoveNoticesHandler(new NoticeRemover($this->recording->store, $this)));
        $this->bus = new RoutingCommandBus($router);
    }

    public function selection(array $targets): NoticeRemovalSelection
    {
        $window = $this->recording->store->readWindow('scope', 0, 1);
        return NoticeRemovalSelection::fromArray(['version' => 1, 'namespace' => $window->namespace(), 'targets' => $targets]);
    }

    public static function target(int $position = 1, string $id = 'notice-1', string $scope = 'scope', string $producer = 'producer'): array
    {
        return ['scope' => $scope, 'producer' => $producer, 'id' => $id, 'position' => $position];
    }

    public function remove(NoticeRemovalSelection $selection): void
    {
        $this->bus->execute(new RemoveNotices($selection));
    }

    public function admit(NoticeRemovalSelection $selection): void
    {
        ++$this->admissions;
        ($this->onAdmit ?? static function (): void {})($selection);
        if (!$this->allowed) { throw new NoticeRemovalRejected(); }
        foreach ($selection->targets() as $target) {
            if (!in_array($target['scope'], ['scope', 'scope-2'], true) || !in_array($target['producer'], ['producer', 'producer-2'], true)) { throw new NoticeRemovalRejected(); }
        }
    }

    public function admitRetained(Notice $notice): void
    {
        ++$this->retainedAdmissions;
        $relations = $notice->relationships()->toArray();
        $references = $relations['recipients'] ?? [];
        unset($relations['recipients']);
        foreach ($relations as $reference) { if ($reference !== null) { $references[] = $reference; } }
        foreach ($references as $reference) { if (in_array($reference, $this->deniedReferences, true)) { throw new NoticeRemovalRejected(); } }
    }
}
