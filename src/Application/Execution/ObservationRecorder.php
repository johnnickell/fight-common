<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution;

use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Execution\Observation;
use Fight\Common\Domain\Execution\ObservationRecorded;
use Fight\Common\Domain\Execution\RecordedObservation;
use Throwable;

/**
 * Class ObservationRecorder
 */
final readonly class ObservationRecorder
{
    /**
     * Constructs ObservationRecorder
     */
    public function __construct(
        private ObservationStore $store,
        private ObservationAdmission $admission,
        private EventDispatcher $events
    ) {
    }

    /**
     * Records admitted evidence and attempts dispatch after the real commit
     */
    public function record(Observation $observation): void
    {
        $recorded = $this->store->commitTransactional(function () use ($observation): ?RecordedObservation {
            $this->admission->admit($observation);
            $this->validateRelationships($observation);
            $existing = $this->store->get($observation->producerId, $observation->id);
            if ($existing !== null) {
                if ($existing->observation->comparison() !== $observation->comparison()) {
                    throw new ObservationConflict();
                }

                return null;
            }

            return $this->store->append($observation);
        });
        if ($recorded === null) {
            return;
        }

        $event = ObservationRecorded::fromRecorded($recorded);
        try {
            $this->events->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedObservationDispatchFailed($event, $throwable);
        }
    }

    /**
     * Validates and creates immutable subject relationships
     */
    private function validateRelationships(Observation $observation): void
    {
        $session = $observation->session;
        $existing = $this->store->session($session->projectId, $session->sessionId);
        if ($existing !== null && $existing->toArray() !== $session->toArray()) {
            throw new InvalidObservationRelationship();
        }

        $phase = $observation->phase;
        if ($phase === null) {
            if ($existing === null) {
                $this->store->addSession($session);
            }

            return;
        }

        if ($existing === null) {
            throw new InvalidObservationRelationship();
        }

        if ($phase->parentPhaseId !== null) {
            $parent = $this->store->phase($session->projectId, $phase->parentPhaseId);
            if ($parent === null || $parent->sessionId !== $session->sessionId) {
                throw new InvalidObservationRelationship();
            }
        }

        $existingPhase = $this->store->phase($session->projectId, $phase->phaseId);
        if ($existingPhase !== null && $existingPhase->toArray() !== $phase->toArray()) {
            throw new InvalidObservationRelationship();
        }

        if ($existingPhase === null) {
            $this->store->addPhase($session->projectId, $phase);
        }
    }
}
