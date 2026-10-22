<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Observation\Service;

use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Application\Execution\Observation\Exception\CommittedObservationDispatchFailed;
use Fight\Common\Application\Execution\Observation\Exception\InvalidObservationRelationship;
use Fight\Common\Application\Execution\Observation\Exception\ObservationConflict;
use Fight\Common\Application\Execution\Observation\Exception\ProtectedContentRemoved;
use Fight\Common\Application\Execution\ObservationAdmission;
use Fight\Common\Application\Execution\RecordingTransaction;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Execution\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationRepository;
use Fight\Common\Domain\Execution\Observation\RecordedObservation;
use Fight\Common\Domain\Execution\Phase\Phase;
use Fight\Common\Domain\Execution\Phase\PhaseRepository;
use Fight\Common\Domain\Execution\Session\Session;
use Fight\Common\Domain\Execution\Session\SessionRepository;
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
        private RecordingTransaction $transaction,
        private SessionRepository $sessions,
        private PhaseRepository $phases,
        private ObservationRepository $observations,
        private ObservationAdmission $admission,
        private EventDispatcher $events,
        private ProtectedContentRepository $contents
    ) {
    }

    /**
     * Records admitted evidence and attempts dispatch after the real commit
     */
    public function record(Observation $observation, ?ProtectedContent $content = null): void
    {
        $operation = function () use ($observation, $content): ?RecordedObservation {
            $this->admission->admit($observation);
            if (($observation->detail() !== null) !== ($content !== null)) {
                throw new DomainException('Detail recording requires paired capture and protected content.');
            }

            $this->validateRelationships($observation);
            $capture = $observation->detail();
            if ($capture !== null) {
                $capture->validateBody($content->body());
                $activity = $this->observations->getById($observation->producerId(), $capture->activityId());
                if ($activity === null || !$capture->acceptsActivity($observation, $activity)) {
                    throw new InvalidObservationRelationship();
                }
            }

            $existing = $this->observations->getById($observation->producerId(), $observation->id());
            if ($existing !== null) {
                if (!$existing->matches($observation)) {
                    throw new ObservationConflict();
                }

                if ($content !== null) {
                    $retained = $this->contents->getFor($existing);
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
            $recorded = new RecordedObservation(
                Observation::fromArray($observation->toArray()),
                new DateTimeImmutable('now', new DateTimeZone('UTC'))
            );
            $this->observations->add($recorded->observation());
            if ($content !== null) {
                $this->contents->add($recorded->observation(), $content);
            }

            return $recorded;
        };
        $recorded = $this->transaction->commitTransactional($operation);
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
        $reference = $observation->session();
        $session = $this->sessions->getById($reference->projectId(), $reference->sessionId());
        if ($session !== null && !$session->matches($reference)) {
            throw new InvalidObservationRelationship();
        }

        $phaseReference = $observation->phase();
        if ($phaseReference === null) {
            if ($session === null) {
                $this->sessions->add(new Session($reference));
            }

            return;
        }

        if ($session === null) {
            throw new InvalidObservationRelationship();
        }

        $phase = new Phase($reference->projectId(), $phaseReference);
        if ($phase->parentPhaseId() !== null) {
            $parent = $this->phases->getById($phase->projectId(), $phase->parentPhaseId());
            if ($parent === null || !$phase->acceptsParent($parent)) {
                throw new InvalidObservationRelationship();
            }
        }

        $existing = $this->phases->getById($phase->projectId(), $phase->id());
        if ($existing !== null && !$existing->matches($phase->projectId(), $phaseReference)) {
            throw new InvalidObservationRelationship();
        }

        if ($existing === null) {
            if ($observation->activity() !== null || $observation->detail() !== null) {
                throw new InvalidObservationRelationship();
            }

            $this->phases->add($phase);
        }
    }
}
