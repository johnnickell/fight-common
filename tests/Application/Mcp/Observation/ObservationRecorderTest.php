<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Observation;

use DateTimeImmutable;
use Fight\Common\Application\Mcp\Observation\Exception\CommittedObservationDispatchFailed;
use Fight\Common\Application\Mcp\Observation\Exception\InvalidObservationRelationship;
use Fight\Common\Application\Mcp\Observation\Exception\ObservationConflict;
use Fight\Common\Application\Mcp\Observation\Exception\ObservationStorageFailed;
use Fight\Common\Application\Mcp\Observation\Exception\RecordingDenied;
use Fight\Common\Application\Mcp\Observation\Service\ObservationAdmission;
use Fight\Common\Application\Mcp\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Mcp\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationRepository;
use Fight\Common\Domain\Mcp\Phase\Phase;
use Fight\Common\Domain\Mcp\Phase\PhaseId;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Phase\PhaseRepository;
use Fight\Common\Domain\Mcp\Session\Session;
use Fight\Common\Domain\Mcp\Session\SessionId;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Mcp\Session\SessionRepository;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(ObservationRecorder::class)]
#[CoversClass(ObservationConflict::class)]
#[CoversClass(InvalidObservationRelationship::class)]
#[CoversClass(RecordingDenied::class)]
#[CoversClass(ObservationStorageFailed::class)]
#[CoversClass(CommittedObservationDispatchFailed::class)]
final class ObservationRecorderTest extends UnitTestCase
{
    private mixed $transactionalUnitOfWork;
    private mixed $sessionRepository;
    private mixed $phaseRepository;
    private mixed $observationRepository;
    private mixed $admission;
    private mixed $eventDispatcher;
    private bool $committed = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionalUnitOfWork = $this->mock(TransactionalUnitOfWork::class);
        $this->sessionRepository = $this->mock(SessionRepository::class);
        $this->phaseRepository = $this->mock(PhaseRepository::class);
        $this->observationRepository = $this->mock(ObservationRepository::class);
        $this->admission = $this->mock(ObservationAdmission::class);
        $this->eventDispatcher = $this->mock(EventDispatcher::class);
    }

    public function test_that_activity_requires_an_existing_phase_after_current_admission(): void
    {
        $observation = ObservationFixture::activity();
        $this->admission->shouldReceive('admit')->once()->with($observation)->globally()->ordered();
        $this->sessionRepository->shouldReceive('getById')->once()->globally()->ordered()->andReturn(new Session($observation->session()));
        $this->phaseRepository->shouldReceive('getById')->once()->globally()->ordered()->andReturnNull();
        $this->phaseRepository->shouldNotReceive('add');
        $this->observationRepository->shouldNotReceive('getById', 'add');
        $this->eventDispatcher->shouldNotReceive('trigger');
        $this->expectException(InvalidObservationRelationship::class);
        $this->transactionalRecorder()->record($observation);
    }

    public function test_that_first_session_is_admitted_and_persisted_before_postcommit_dispatch(): void
    {
        $observation = ObservationFixture::session()->recorded(new DateTimeImmutable('2000-01-01Z'));
        $before = new DateTimeImmutable('now');
        $this->admission->shouldReceive('admit')->once()->with($observation)->globally()->ordered();
        $this->sessionRepository->shouldReceive('getById')->once()->withArgs(static fn (SessionId $id): bool => $id->equals(SessionId::fromStrings('project', 'session')))->globally()->ordered()->andReturnNull();
        $this->sessionRepository->shouldReceive('add')->once()->withArgs(static fn (Session $session): bool => $session->matches($observation->session()))->globally()->ordered();
        $this->observationRepository->shouldReceive('getById')->once()->with('instrumentation', $observation->id())->globally()->ordered()->andReturnNull();
        $saved = null;
        $this->observationRepository->shouldReceive('add')->once()->globally()->ordered()->andReturnUsing(static function (Observation $root) use (&$saved, $observation, $before): void {
            self::assertTrue($root->matches($observation));
            self::assertGreaterThanOrEqual($before, $root->recordedAt());
            self::assertLessThanOrEqual(new DateTimeImmutable('now'), $root->recordedAt());
            $saved = $root;
        });
        $this->eventDispatcher->shouldReceive('trigger')->once()->andReturnUsing(function (ObservationRecorded $event) use (&$saved, $observation): void {
            self::assertTrue($this->committed);
            self::assertTrue($event->id()->equals($observation->id()));
            self::assertNull($event->phaseId());
            self::assertSame($saved->recordedAt(), $event->recordedAt());
        });
        $this->transactionalRecorder()->record($observation);
    }

    public function test_that_matching_retry_rechecks_admission_and_never_redispatches(): void
    {
        $observation = ObservationFixture::session();
        $this->admission->shouldReceive('admit')->once()->with($observation);
        $this->sessionRepository->shouldReceive('getById')->once()->andReturn(new Session($observation->session()));
        $this->observationRepository->shouldReceive('getById')->once()->andReturn($observation->recorded(new DateTimeImmutable('2020-01-01Z')));
        $this->observationRepository->shouldNotReceive('add');
        $this->sessionRepository->shouldNotReceive('add');
        $this->eventDispatcher->shouldNotReceive('trigger');
        $this->transactionalRecorder()->record($observation);
    }

    public function test_that_conflicting_retry_does_not_overwrite_retained_evidence(): void
    {
        $observation = ObservationFixture::session(['outcome' => 'failed']);
        $this->admission->shouldReceive('admit')->once();
        $this->sessionRepository->shouldReceive('getById')->once()->andReturn(new Session($observation->session()));
        $this->observationRepository->shouldReceive('getById')->once()->andReturn(ObservationFixture::session()->recorded(new DateTimeImmutable('2020-01-01Z')));
        $this->observationRepository->shouldNotReceive('add');
        $this->eventDispatcher->shouldNotReceive('trigger');
        $this->expectException(ObservationConflict::class);
        $this->transactionalRecorder()->record($observation);
    }

    public function test_that_denied_retry_never_looks_up_concealed_relationships_or_retained_content(): void
    {
        $this->admission->shouldReceive('admit')->once()->andThrow(new RecordingDenied());
        $this->sessionRepository->shouldNotReceive('getById', 'add');
        $this->phaseRepository->shouldNotReceive('getById', 'add');
        $this->observationRepository->shouldNotReceive('getById', 'add');
        $this->eventDispatcher->shouldNotReceive('trigger');
        $this->expectException(RecordingDenied::class);
        $this->expectExceptionMessage('Observation recording is not permitted.');
        $this->transactionalRecorder()->record(ObservationFixture::session());
    }

    #[DataProvider('relationshipCases')]
    public function test_that_relationship_scope_is_validated_before_observation_storage(string $case, bool $valid): void
    {
        $observation = ObservationFixture::phase();
        if (str_starts_with($case, 'parent')) {
            $observation = ObservationFixture::phase(['phase' => PhaseReference::fromStrings('session', 'child', 'parent', 'agent', 'work', 1)->toArray()]);
        }
        if ($case === 'session-mismatch') { $observation = ObservationFixture::session(); }
        $this->admission->shouldReceive('admit')->once();
        $session = new Session($observation->session());
        if ($case === 'missing-session') {
            $session = null;
        } elseif ($case === 'session-mismatch') {
            $session = new Session(SessionReference::fromStrings('project', 'session', 'other'));
        }
        $this->sessionRepository->shouldReceive('getById')->once()->andReturn($session);
        if (str_starts_with($case, 'parent')) {
            $parent = match ($case) {
                'parent-missing' => null,
                'parent-cross-session' => new Phase('project', PhaseReference::fromStrings('other', 'parent', null, 'agent', 'work', 1)),
                default => new Phase('project', PhaseReference::fromStrings('session', 'parent', null, 'agent', 'work', 1)),
            };
            $this->phaseRepository->shouldReceive('getById')->withArgs(static fn (PhaseId $id): bool => $id->equals(PhaseId::fromStrings('project', 'parent')))->once()->andReturn($parent);
        }
        if (!in_array($case, ['session-mismatch', 'missing-session', 'parent-missing', 'parent-cross-session'], true)) {
            $existing = match ($case) {
                'phase-existing' => new Phase('project', $observation->phase()),
                'phase-mismatch' => new Phase('project', PhaseReference::fromStrings('session', 'phase', null, 'different-agent', 'review', 1)),
                default => null,
            };
            $this->phaseRepository->shouldReceive('getById')->withArgs(static fn (PhaseId $id): bool => $id->equals(PhaseId::fromStrings('project', $observation->phase()->phaseId())))->once()->andReturn($existing);
            if ($existing === null) {
                $this->phaseRepository->shouldReceive('add')->once()->withArgs(static fn (Phase $phase): bool => $phase->matches('project', $observation->phase()));
            }
        }
        if (!$valid) {
            $this->observationRepository->shouldNotReceive('add', 'getById');
            $this->eventDispatcher->shouldNotReceive('trigger');
            $this->expectException(InvalidObservationRelationship::class);
            $this->expectExceptionMessage('Observation relationship is unavailable or inconsistent.');
        } else {
            $this->observationRepository->shouldReceive('getById')->once()->andReturnNull();
            $this->observationRepository->shouldReceive('add')->once()->withArgs(static fn (Observation $root): bool => $root->matches($observation));
            $this->eventDispatcher->shouldReceive('trigger')->once()->withArgs(static fn (ObservationRecorded $event): bool => $event->phaseId() === $observation->phase()->phaseId());
        }
        $this->transactionalRecorder()->record($observation);
    }

    public static function relationshipCases(): iterable
    {
        foreach (['session-mismatch', 'missing-session', 'phase-mismatch', 'parent-missing', 'parent-cross-session'] as $case) { yield $case => [$case, false]; }
        foreach (['phase-new', 'phase-existing', 'parent-valid'] as $case) { yield $case => [$case, true]; }
    }

    public function test_that_dispatch_failure_is_explicitly_committed_and_preserves_private_cause(): void
    {
        $observation = ObservationFixture::session();
        $this->admission->shouldReceive('admit')->once();
        $this->sessionRepository->shouldReceive('getById')->once()->andReturn(new Session($observation->session()));
        $this->observationRepository->shouldReceive('getById')->once()->andReturnNull();
        $this->observationRepository->shouldReceive('add')->once();
        $failure = new RuntimeException('private subscriber diagnostic');
        $this->eventDispatcher->shouldReceive('trigger')->once()->andThrow($failure);
        try {
            $this->transactionalRecorder()->record($observation);
            self::fail('Expected committed dispatch failure.');
        } catch (CommittedObservationDispatchFailed $exception) {
            self::assertTrue($this->committed);
            self::assertSame($failure, $exception->getPrevious());
            self::assertSame('Observation committed but event dispatch failed.', $exception->getMessage());
            self::assertSame([], get_object_vars($exception));
            self::assertTrue($observation->id()->equals($exception->event()->id()));
            self::assertSame($exception->event(), $exception->event());
        }
    }

    public function test_that_storage_failure_prevents_event_dispatch_and_distinguishes_uncertainty(): void
    {
        foreach ([false, true] as $uncertain) {
            $transactionalUnitOfWork = $this->mock(TransactionalUnitOfWork::class);
            $cause = new RuntimeException('private storage diagnostic');
            $transactionalUnitOfWork->shouldReceive('commitTransactional')->once()->andThrow(new ObservationStorageFailed($uncertain, $cause));
            $this->eventDispatcher->shouldNotReceive('trigger');
            try {
                $this->recorder($transactionalUnitOfWork)->record(ObservationFixture::session());
                self::fail('Expected storage failure.');
            } catch (ObservationStorageFailed $exception) {
                self::assertSame($cause, $exception->getPrevious());
                self::assertSame([], get_object_vars($exception));
                self::assertSame($uncertain, $exception->outcomeUncertain());
                self::assertSame($uncertain ? 'Observation storage outcome is uncertain.' : 'Observation storage was rolled back.', $exception->getMessage());
            }
        }
    }

    private function transactionalRecorder(): ObservationRecorder
    {
        $this->transactionalUnitOfWork->shouldReceive('commitTransactional')->once()->andReturnUsing(function (callable $operation): mixed {
            $result = $operation();
            $this->committed = true;
            return $result;
        });
        return $this->recorder($this->transactionalUnitOfWork);
    }

    private function recorder(TransactionalUnitOfWork $transactionalUnitOfWork): ObservationRecorder
    {
        return new ObservationRecorder($transactionalUnitOfWork, $this->sessionRepository, $this->phaseRepository, $this->observationRepository, $this->admission, $this->eventDispatcher, $this->mock(ProtectedContentRepository::class));
    }
}
