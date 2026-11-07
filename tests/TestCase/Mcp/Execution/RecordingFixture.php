<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Execution;

use Closure;
use Fight\Common\Adapter\Mcp\Persistence\Detail\Dbal\DbalProtectedContentRepository;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Mcp\Persistence\Observation\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Mcp\Persistence\Phase\Dbal\DbalPhaseRepository;
use Fight\Common\Adapter\Mcp\Persistence\Session\Dbal\DbalSessionRepository;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Mcp\Observation\CommandHandler\RecordPhaseObservationHandler;
use Fight\Common\Application\Mcp\Observation\CommandHandler\RecordProtectedDetailHandler;
use Fight\Common\Application\Mcp\Observation\CommandHandler\RecordSessionObservationHandler;
use Fight\Common\Application\Mcp\Observation\Exception\RecordingDenied;
use Fight\Common\Application\Mcp\Observation\Service\ObservationAdmission;
use Fight\Common\Application\Mcp\Observation\Service\ObservationRecorder;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Mcp\Observation\Command\RecordProtectedDetail;
use Fight\Common\Domain\Mcp\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Mcp\Observation\Observation;

final class RecordingFixture implements ObservationAdmission
{
    private bool $allowed = true;
    private int $admissions = 0;
    private ?array $permittedSessions = null;
    private ?array $permittedPhases = null;
    private readonly RoutingCommandBus $commandBus;
    private readonly SimpleEventDispatcher $eventDispatcher;
    private ?Closure $onAdmit = null;

    public function __construct(DbalRecordingTransaction $recordingTransaction)
    {
        $this->eventDispatcher = new SimpleEventDispatcher();
        $recorder = new ObservationRecorder(
            $recordingTransaction,
            new DbalSessionRepository($recordingTransaction),
            new DbalPhaseRepository($recordingTransaction),
            new DbalObservationRepository($recordingTransaction),
            $this,
            $this->eventDispatcher,
            new DbalProtectedContentRepository($recordingTransaction)
        );
        $router = new InMemoryCommandRouter();
        $router->registerHandler(RecordSessionObservation::class, new RecordSessionObservationHandler($recorder));
        $router->registerHandler(RecordPhaseObservation::class, new RecordPhaseObservationHandler($recorder));
        $router->registerHandler(RecordProtectedDetail::class, new RecordProtectedDetailHandler($recorder));
        $this->commandBus = new RoutingCommandBus($router);
    }

    public function setAllowed(bool $allowed): void
    {
        $this->allowed = $allowed;
    }

    public function admissions(): int
    {
        return $this->admissions;
    }

    public function setPermittedSessions(?array $sessions): void
    {
        $this->permittedSessions = $sessions;
    }

    public function setPermittedPhases(?array $phases): void
    {
        $this->permittedPhases = $phases;
    }

    public function bus(): RoutingCommandBus
    {
        return $this->commandBus;
    }

    public function events(): SimpleEventDispatcher
    {
        return $this->eventDispatcher;
    }

    public function setOnAdmit(?Closure $onAdmit): void
    {
        $this->onAdmit = $onAdmit;
    }

    public function admit(Observation $observation): void
    {
        ++$this->admissions;
        if (!$this->allowed || $observation->producerId() !== 'instrumentation' || $observation->session()->projectId() !== 'project') {
            throw new RecordingDenied();
        }
        if ($this->permittedSessions !== null && !in_array($observation->session()->sessionId(), $this->permittedSessions, true)) {
            throw new RecordingDenied();
        }
        if ($this->permittedPhases !== null && $observation->phase() !== null) {
            foreach (array_filter([$observation->phase()->phaseId(), $observation->phase()->parentPhaseId()]) as $phaseId) {
                if (!in_array($phaseId, $this->permittedPhases, true)) {
                    throw new RecordingDenied();
                }
            }
        }
        ($this->onAdmit ?? static function (): void {})($observation);
    }

    public function record(Observation $observation, ?ProtectedContent $content = null): void
    {
        if ($content !== null) {
            $command = new RecordProtectedDetail($observation, $content);
        } elseif ($observation->phase() === null) {
            $command = new RecordSessionObservation($observation);
        } else {
            $command = new RecordPhaseObservation($observation);
        }
        $this->commandBus->execute($command);
    }
}
