<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Execution\Observation;

use DateTimeImmutable;
use Fight\Common\Application\Mcp\Execution\Observation\Exception\CommittedObservationDispatchFailed;
use Fight\Common\Application\Mcp\Execution\Observation\Exception\InvalidObservationRelationship;
use Fight\Common\Application\Mcp\Execution\Observation\Exception\ObservationConflict;
use Fight\Common\Application\Mcp\Execution\Observation\Exception\ObservationStorageFailed;
use Fight\Common\Application\Mcp\Execution\Observation\Exception\RecordingDenied;
use Fight\Common\Application\Mcp\Execution\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Mcp\Execution\ObservationAdmission;
use Fight\Common\Application\Mcp\Execution\RecordingTransaction;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Mcp\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationRepository;
use Fight\Common\Domain\Mcp\Phase\Phase;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Phase\PhaseRepository;
use Fight\Common\Domain\Mcp\Session\Session;
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
    private mixed $transaction;
    private mixed $sessions;
    private mixed $phases;
    private mixed $observations;
    private mixed $admission;
    private mixed $events;
    private bool $committed = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transaction = $this->mock(RecordingTransaction::class);
        $this->sessions = $this->mock(SessionRepository::class);
        $this->phases = $this->mock(PhaseRepository::class);
        $this->observations = $this->mock(ObservationRepository::class);
        $this->admission = $this->mock(ObservationAdmission::class);
        $this->events = $this->mock(EventDispatcher::class);
    }

    public function test_that_activity_requires_an_existing_phase_after_current_admission(): void
    {
        $observation = ObservationFixture::activity();
        $this->admission->shouldReceive('admit')->once()->with($observation)->globally()->ordered();
        $this->sessions->shouldReceive('getById')->once()->globally()->ordered()->andReturn(new Session($observation->session()));
        $this->phases->shouldReceive('getById')->once()->globally()->ordered()->andReturnNull();
        $this->phases->shouldNotReceive('add');
        $this->observations->shouldNotReceive('getById', 'add');
        $this->events->shouldNotReceive('trigger');
        $this->expectException(InvalidObservationRelationship::class);
        $this->transactionalRecorder()->record($observation);
    }

    public function test_that_first_session_is_admitted_and_persisted_before_postcommit_dispatch(): void
    {
        $observation = ObservationFixture::session()->recorded(new DateTimeImmutable('2000-01-01Z'));
        $before = new DateTimeImmutable('now');
        $this->admission->shouldReceive('admit')->once()->with($observation)->globally()->ordered();
        $this->sessions->shouldReceive('getById')->once()->with('project', 'session')->globally()->ordered()->andReturnNull();
        $this->sessions->shouldReceive('add')->once()->withArgs(static fn (Session $session): bool => $session->matches($observation->session()))->globally()->ordered();
        $this->observations->shouldReceive('getById')->once()->with('instrumentation', $observation->id())->globally()->ordered()->andReturnNull();
        $saved = null;
        $this->observations->shouldReceive('add')->once()->globally()->ordered()->andReturnUsing(static function (Observation $root) use (&$saved, $observation, $before): void {
            self::assertTrue($root->matches($observation));
            self::assertGreaterThanOrEqual($before, $root->recordedAt());
            self::assertLessThanOrEqual(new DateTimeImmutable('now'), $root->recordedAt());
            $saved = $root;
        });
        $this->events->shouldReceive('trigger')->once()->andReturnUsing(function (ObservationRecorded $event) use (&$saved, $observation): void {
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
        $this->sessions->shouldReceive('getById')->once()->andReturn(new Session($observation->session()));
        $this->observations->shouldReceive('getById')->once()->andReturn($observation->recorded(new DateTimeImmutable('2020-01-01Z')));
        $this->observations->shouldNotReceive('add');
        $this->sessions->shouldNotReceive('add');
        $this->events->shouldNotReceive('trigger');
        $this->transactionalRecorder()->record($observation);
    }

    public function test_that_conflicting_retry_does_not_overwrite_retained_evidence(): void
    {
        $observation = ObservationFixture::session(['outcome' => 'failed']);
        $this->admission->shouldReceive('admit')->once();
        $this->sessions->shouldReceive('getById')->once()->andReturn(new Session($observation->session()));
        $this->observations->shouldReceive('getById')->once()->andReturn(ObservationFixture::session()->recorded(new DateTimeImmutable('2020-01-01Z')));
        $this->observations->shouldNotReceive('add');
        $this->events->shouldNotReceive('trigger');
        $this->expectException(ObservationConflict::class);
        $this->transactionalRecorder()->record($observation);
    }

    public function test_that_denied_retry_never_looks_up_concealed_relationships_or_retained_content(): void
    {
        $this->admission->shouldReceive('admit')->once()->andThrow(new RecordingDenied());
        $this->sessions->shouldNotReceive('getById', 'add');
        $this->phases->shouldNotReceive('getById', 'add');
        $this->observations->shouldNotReceive('getById', 'add');
        $this->events->shouldNotReceive('trigger');
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
        $this->sessions->shouldReceive('getById')->once()->andReturn($session);
        if (str_starts_with($case, 'parent')) {
            $parent = match ($case) {
                'parent-missing' => null,
                'parent-cross-session' => new Phase('project', PhaseReference::fromStrings('other', 'parent', null, 'agent', 'work', 1)),
                default => new Phase('project', PhaseReference::fromStrings('session', 'parent', null, 'agent', 'work', 1)),
            };
            $this->phases->shouldReceive('getById')->with('project', 'parent')->once()->andReturn($parent);
        }
        if (!in_array($case, ['session-mismatch', 'missing-session', 'parent-missing', 'parent-cross-session'], true)) {
            $existing = match ($case) {
                'phase-existing' => new Phase('project', $observation->phase()),
                'phase-mismatch' => new Phase('project', PhaseReference::fromStrings('session', 'phase', null, 'different-agent', 'review', 1)),
                default => null,
            };
            $this->phases->shouldReceive('getById')->with('project', $observation->phase()->phaseId())->once()->andReturn($existing);
            if ($existing === null) {
                $this->phases->shouldReceive('add')->once()->withArgs(static fn (Phase $phase): bool => $phase->matches('project', $observation->phase()));
            }
        }
        if (!$valid) {
            $this->observations->shouldNotReceive('add', 'getById');
            $this->events->shouldNotReceive('trigger');
            $this->expectException(InvalidObservationRelationship::class);
            $this->expectExceptionMessage('Observation relationship is unavailable or inconsistent.');
        } else {
            $this->observations->shouldReceive('getById')->once()->andReturnNull();
            $this->observations->shouldReceive('add')->once()->withArgs(static fn (Observation $root): bool => $root->matches($observation));
            $this->events->shouldReceive('trigger')->once()->withArgs(static fn (ObservationRecorded $event): bool => $event->phaseId() === $observation->phase()->phaseId());
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
        $this->sessions->shouldReceive('getById')->once()->andReturn(new Session($observation->session()));
        $this->observations->shouldReceive('getById')->once()->andReturnNull();
        $this->observations->shouldReceive('add')->once();
        $failure = new RuntimeException('private subscriber diagnostic');
        $this->events->shouldReceive('trigger')->once()->andThrow($failure);
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
            $transaction = $this->mock(RecordingTransaction::class);
            $cause = new RuntimeException('private storage diagnostic');
            $transaction->shouldReceive('commitTransactional')->once()->andThrow(new ObservationStorageFailed($uncertain, $cause));
            $this->events->shouldNotReceive('trigger');
            try {
                $this->recorder($transaction)->record(ObservationFixture::session());
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
        $this->transaction->shouldReceive('commitTransactional')->once()->andReturnUsing(function (callable $operation): mixed {
            $result = $operation();
            $this->committed = true;
            return $result;
        });
        return $this->recorder($this->transaction);
    }

    private function recorder(RecordingTransaction $transaction): ObservationRecorder
    {
        return new ObservationRecorder($transaction, $this->sessions, $this->phases, $this->observations, $this->admission, $this->events, $this->mock(ProtectedContentRepository::class));
    }
}
