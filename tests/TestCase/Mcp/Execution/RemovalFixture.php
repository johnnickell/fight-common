<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Execution;

use Closure;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalProtectedContentRepository;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Application\Mcp\Execution\Detail\CommandHandler\RemoveCapturedDetailHandler;
use Fight\Common\Application\Mcp\Execution\Detail\Exception\DetailRemovalRejected;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailRemovalAdmission;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailRemover;
use Fight\Common\Domain\Mcp\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Mcp\Observation\Observation;

final class RemovalFixture implements DetailRemovalAdmission
{
    public bool $allowed = true;
    public int $admissions = 0;
    public ?Closure $onAdmit = null;
    public readonly RoutingCommandBus $bus;

    public function __construct(DbalRecordingTransaction $transaction, ?Closure $afterDelete = null)
    {
        $repository = new DbalProtectedContentRepository($transaction);
        if ($afterDelete !== null) {
            $repository = new class($repository, $afterDelete) implements ProtectedContentRepository {
                public function __construct(private ProtectedContentRepository $inner, private Closure $afterDelete) {}
                public function getFor(Observation $detail): ?ProtectedContent { return $this->inner->getFor($detail); }
                public function add(Observation $detail, ProtectedContent $content): void { $this->inner->add($detail, $content); }
                public function removeFor(Observation $detail): void { $this->inner->removeFor($detail); ($this->afterDelete)(); }
            };
        }
        $remover = new DetailRemover($transaction, new DbalObservationRepository($transaction), $repository, $this);
        $router = new InMemoryCommandRouter();
        $router->registerHandler(RemoveCapturedDetailHandler::commandRegistration(), new RemoveCapturedDetailHandler($remover));
        $this->bus = new RoutingCommandBus($router);
    }

    public function admit(RemoveCapturedDetail $command): void
    {
        ++$this->admissions;
        if (!$this->allowed || $command->selection()->execution()->toArray() !== ['project_id' => 'project', 'session_id' => 'session'] || $command->selection()->phaseId() !== 'phase' || $command->selection()->producerId() !== 'instrumentation') {
            throw new DetailRemovalRejected();
        }
        if ($this->onAdmit !== null) { ($this->onAdmit)($command); }
    }

    public static function command(Observation $detail): RemoveCapturedDetail
    {
        return new RemoveCapturedDetail(DetailSelection::create($detail->session()->projectId(), $detail->session()->sessionId(), $detail->phase()->phaseId(), $detail->producerId(), $detail->detail()->activityId()->toString()), $detail->id(), $detail->detail()->kind());
    }
}
