<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution;

use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Execution\Observation;
use Fight\Common\Domain\Execution\ObservationId;
use Fight\Common\Domain\Execution\PhaseReference;
use Fight\Common\Domain\Execution\RecordedObservation;
use Fight\Common\Domain\Execution\SessionReference;

/**
 * Interface ObservationStore
 *
 * Internal recording/reconstruction port, not an authorized inspection Query API.
 * The transaction exclusively owns the real commit and serializes recording operations.
 */
interface ObservationStore extends TransactionalUnitOfWork
{
    /**
     * Returns retained observation evidence
     */
    public function get(string $producerId, ObservationId $id): ?RecordedObservation;

    /**
     * Returns a session relationship within one project
     */
    public function session(string $projectId, string $sessionId): ?SessionReference;

    /**
     * Returns a phase relationship within one project
     */
    public function phase(string $projectId, string $phaseId): ?PhaseReference;

    /**
     * Creates a new session relationship inside the owned transaction
     */
    public function addSession(SessionReference $session): void;

    /**
     * Creates a new phase relationship inside the owned transaction
     */
    public function addPhase(string $projectId, PhaseReference $phase): void;

    /**
     * Creates new immutable evidence inside the owned transaction
     */
    public function append(Observation $observation): RecordedObservation;
}
