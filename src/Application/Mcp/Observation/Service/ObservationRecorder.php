<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Observation\Service;

use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Application\Mcp\Observation\Exception\CommittedObservationDispatchFailed;
use Fight\Common\Application\Mcp\Observation\Exception\InvalidObservationRelationship;
use Fight\Common\Application\Mcp\Observation\Exception\ObservationConflict;
use Fight\Common\Application\Mcp\Observation\Exception\ProtectedContentRemoved;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationRepository;
use Fight\Common\Domain\Mcp\Phase\Phase;
use Fight\Common\Domain\Mcp\Phase\PhaseId;
use Fight\Common\Domain\Mcp\Phase\PhaseRepository;
use Fight\Common\Domain\Mcp\Session\Session;
use Fight\Common\Domain\Mcp\Session\SessionId;
use Fight\Common\Domain\Mcp\Session\SessionRepository;
use Throwable;

/**
 * Class ObservationRecorder
 */
final readonly class ObservationRecorder
{
    /**
     * Constructs ObservationRecorder
     *
     * All repositories participate in the supplied recording transaction
     */
    public function __construct(
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private SessionRepository $sessionRepository,
        private PhaseRepository $phaseRepository,
        private ObservationRepository $observationRepository,
        private ObservationAdmission $observationAdmission,
        private EventDispatcher $eventDispatcher,
        private ProtectedContentRepository $protectedContentRepository
    ) {
    }

    /**
     * Records admitted evidence and attempts dispatch after the real commit
     */
    public function record(Observation $observation, ?ProtectedContent $content = null): void
    {
        $operation = function () use ($observation, $content): ?Observation {
            $this->observationAdmission->admit($observation);
            if (($observation->detail() !== null) !== ($content !== null)) {
                throw new DomainException('Detail recording requires paired capture and protected content.');
            }

            $this->validateRelationships($observation);
            $capture = $observation->detail();
            if ($capture !== null) {
                $capture->validateBody($content->body());
                $activity = $this->observationRepository->getById($observation->producerId(), $capture->activityId());
                if ($activity === null || !$capture->acceptsActivity($observation, $activity)) {
                    throw new InvalidObservationRelationship();
                }
            }

            $existing = $this->observationRepository->getById($observation->producerId(), $observation->id());
            if ($existing !== null) {
                if (!$existing->matches($observation)) {
                    throw new ObservationConflict();
                }

                if ($content !== null) {
                    $retained = $this->protectedContentRepository->getFor($existing);
                    if ($retained === null) {
                        throw new ProtectedContentRemoved();
                    }

                    if (!$retained->matches($content)) {
                        throw new ObservationConflict();
                    }
                }

                return null;
            }

            // Submitted receipt times never choose a new server recording time.
            $recorded = Observation::fromArray($observation->toArray())->recorded(
                new DateTimeImmutable('now', new DateTimeZone('UTC'))
            );
            $this->observationRepository->add($recorded);
            if ($content !== null) {
                $this->protectedContentRepository->add($recorded, $content);
            }

            return $recorded;
        };
        $recorded = $this->transactionalUnitOfWork->commitTransactional($operation);
        if ($recorded === null) {
            return;
        }

        $event = ObservationRecorded::fromRecorded($recorded);
        try {
            $this->eventDispatcher->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedObservationDispatchFailed($event, $throwable);
        }
    }

    /**
     * Validates and creates immutable subject relationships
     */
    private function validateRelationships(Observation $observation): void
    {
        $reference = $observation->session();
        $sessionIdentity = SessionId::fromStrings($reference->projectId(), $reference->sessionId());
        $session = $this->sessionRepository->getById($sessionIdentity);
        if ($session !== null && !$session->matches($reference)) {
            throw new InvalidObservationRelationship();
        }

        $phaseReference = $observation->phase();
        if ($phaseReference === null) {
            if ($session === null) {
                $this->sessionRepository->add(new Session($reference));
            }

            return;
        }

        if ($session === null) {
            throw new InvalidObservationRelationship();
        }

        $phase = new Phase($reference->projectId(), $phaseReference);
        if ($phase->parentPhaseId() !== null) {
            $parentIdentity = PhaseId::fromStrings($phase->projectId(), $phase->parentPhaseId());
            $parent = $this->phaseRepository->getById($parentIdentity);
            if ($parent === null || !$phase->acceptsParent($parent)) {
                throw new InvalidObservationRelationship();
            }
        }

        $existing = $this->phaseRepository->getById($phase->identity());
        if ($existing !== null && !$existing->matches($phase->projectId(), $phaseReference)) {
            throw new InvalidObservationRelationship();
        }

        if ($existing === null) {
            if ($observation->activity() !== null || $observation->detail() !== null) {
                throw new InvalidObservationRelationship();
            }

            $this->phaseRepository->add($phase);
        }
    }
}
