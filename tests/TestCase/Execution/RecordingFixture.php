<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Execution;

use Closure;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationStore;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Execution\ObservationAdmission;
use Fight\Common\Application\Execution\ObservationRecorder;
use Fight\Common\Application\Execution\RecordPhaseObservation;
use Fight\Common\Application\Execution\RecordPhaseObservationHandler;
use Fight\Common\Application\Execution\RecordSessionObservation;
use Fight\Common\Application\Execution\RecordSessionObservationHandler;
use Fight\Common\Application\Execution\RecordingDenied;
use Fight\Common\Domain\Execution\Observation;

final class RecordingFixture implements ObservationAdmission
{
    public bool $allowed = true;
    public int $admissions = 0;
    public ?array $permittedSessions = null;
    public ?array $permittedPhases = null;
    public readonly RoutingCommandBus $bus;
    public readonly SimpleEventDispatcher $events;
    public ?Closure $onAdmit = null;

    public function __construct(DbalObservationStore $store)
    {
        $this->events = new SimpleEventDispatcher();
        $recorder = new ObservationRecorder($store, $this, $this->events);
        $router = new InMemoryCommandRouter();
        $router->registerHandler(RecordSessionObservation::class, new RecordSessionObservationHandler($recorder));
        $router->registerHandler(RecordPhaseObservation::class, new RecordPhaseObservationHandler($recorder));
        $this->bus = new RoutingCommandBus($router);
    }

    public function admit(Observation $observation): void
    {
        ++$this->admissions;
        if (!$this->allowed || $observation->producerId !== 'instrumentation' || $observation->session->projectId !== 'project') {
            throw new RecordingDenied();
        }
        if ($this->permittedSessions !== null && !in_array($observation->session->sessionId, $this->permittedSessions, true)) {
            throw new RecordingDenied();
        }
        if ($this->permittedPhases !== null && $observation->phase !== null) {
            foreach (array_filter([$observation->phase->phaseId, $observation->phase->parentPhaseId]) as $phaseId) {
                if (!in_array($phaseId, $this->permittedPhases, true)) {
                    throw new RecordingDenied();
                }
            }
        }
        ($this->onAdmit ?? static function (): void {})($observation);
    }

    public function record(Observation $observation): void
    {
        if ($observation->phase === null) {
            $command = new RecordSessionObservation($observation);
        } else {
            $command = new RecordPhaseObservation($observation);
        }
        $this->bus->execute($command);
    }
}
