<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Execution;

use DateTimeImmutable;
use Fight\Common\Application\Execution\CommittedObservationDispatchFailed;
use Fight\Common\Application\Execution\InvalidObservationRelationship;
use Fight\Common\Application\Execution\ObservationAdmission;
use Fight\Common\Application\Execution\ObservationConflict;
use Fight\Common\Application\Execution\ObservationRecorder;
use Fight\Common\Application\Execution\ObservationStorageFailed;
use Fight\Common\Application\Execution\ObservationStore;
use Fight\Common\Application\Execution\RecordingDenied;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Execution\ObservationRecorded;
use Fight\Common\Domain\Execution\PhaseReference;
use Fight\Common\Domain\Execution\RecordedObservation;
use Fight\Common\Domain\Execution\SessionReference;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
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
    public function test_that_first_session_is_admitted_and_persisted_before_postcommit_dispatch(): void
    {
        $observation = ObservationFixture::session();
        $recorded = new RecordedObservation($observation, new DateTimeImmutable('2026-10-20T15:00:00Z'));
        $store = $this->mock(ObservationStore::class);
        $admission = $this->mock(ObservationAdmission::class);
        $events = $this->mock(EventDispatcher::class);
        $committed = false;
        $store->shouldReceive('commitTransactional')->once()->andReturnUsing(static function (callable $operation) use (&$committed) {
            $result = $operation();
            $committed = true;
            return $result;
        });
        $admission->shouldReceive('admit')->once()->with($observation)->globally()->ordered();
        $store->shouldReceive('session')->once()->with('project', 'session')->globally()->ordered()->andReturnNull();
        $store->shouldReceive('addSession')->once()->with($observation->session)->globally()->ordered();
        $store->shouldReceive('get')->once()->with('instrumentation', $observation->id)->globally()->ordered()->andReturnNull();
        $store->shouldReceive('append')->once()->with($observation)->globally()->ordered()->andReturn($recorded);
        $events->shouldReceive('trigger')->once()->andReturnUsing(static function (ObservationRecorded $event) use (&$committed, $observation): void {
            self::assertTrue($committed);
            self::assertTrue($event->id()->equals($observation->id));
            self::assertNull($event->phaseId());
        });
        (new ObservationRecorder($store, $admission, $events))->record($observation);
    }

    public function test_that_matching_retry_rechecks_admission_and_never_redispatches(): void
    {
        $observation = ObservationFixture::session();
        $store = $this->transactionalStore();
        $admission = $this->mock(ObservationAdmission::class);
        $events = $this->mock(EventDispatcher::class);
        $admission->shouldReceive('admit')->once()->with($observation);
        $store->shouldReceive('session')->once()->andReturn($observation->session);
        $store->shouldReceive('get')->once()->andReturn(new RecordedObservation($observation, new DateTimeImmutable('2020-01-01Z')));
        $store->shouldNotReceive('append', 'addSession');
        $events->shouldNotReceive('trigger');
        (new ObservationRecorder($store, $admission, $events))->record($observation);
    }

    public function test_that_conflicting_retry_does_not_overwrite_retained_evidence(): void
    {
        $observation = ObservationFixture::session(['outcome' => 'failed']);
        $store = $this->transactionalStore();
        $admission = $this->mock(ObservationAdmission::class);
        $events = $this->mock(EventDispatcher::class);
        $admission->shouldReceive('admit')->once();
        $store->shouldReceive('session')->once()->andReturn($observation->session);
        $store->shouldReceive('get')->once()->andReturn(new RecordedObservation(ObservationFixture::session(), new DateTimeImmutable('2020-01-01Z')));
        $store->shouldNotReceive('append');
        $events->shouldNotReceive('trigger');
        $this->expectException(ObservationConflict::class);
        (new ObservationRecorder($store, $admission, $events))->record($observation);
    }

    public function test_that_denied_retry_never_looks_up_concealed_relationships_or_retained_content(): void
    {
        $store = $this->transactionalStore();
        $admission = $this->mock(ObservationAdmission::class);
        $events = $this->mock(EventDispatcher::class);
        $admission->shouldReceive('admit')->once()->andThrow(new RecordingDenied());
        $store->shouldNotReceive('session', 'phase', 'get', 'append');
        $events->shouldNotReceive('trigger');
        $this->expectException(RecordingDenied::class);
        $this->expectExceptionMessage('Observation recording is not permitted.');
        (new ObservationRecorder($store, $admission, $events))->record(ObservationFixture::session());
    }

    #[DataProvider('relationshipCases')]
    public function test_that_relationship_scope_is_validated_before_observation_storage(string $case, bool $valid): void
    {
        $observation = ObservationFixture::phase();
        if (str_starts_with($case, 'parent')) {
            $observation = ObservationFixture::phase(['phase' => PhaseReference::fromStrings('session', 'child', 'parent', 'agent', 'work', 1)->toArray()]);
        }
        if ($case === 'session-mismatch') {
            $observation = ObservationFixture::session();
        }
        $store = $this->transactionalStore();
        $admission = $this->mock(ObservationAdmission::class);
        $events = $this->mock(EventDispatcher::class);
        $admission->shouldReceive('admit')->once();
        $session = $observation->session;
        if ($case === 'missing-session') {
            $session = null;
        } elseif ($case === 'session-mismatch') {
            $session = SessionReference::fromStrings('project', 'session', 'other');
        }
        $store->shouldReceive('session')->once()->andReturn($session);
        if (str_starts_with($case, 'parent')) {
            $parent = match ($case) {
                'parent-missing' => null,
                'parent-cross-session' => PhaseReference::fromStrings('other', 'parent', null, 'agent', 'work', 1),
                default => PhaseReference::fromStrings('session', 'parent', null, 'agent', 'work', 1),
            };
            $store->shouldReceive('phase')->with('project', 'parent')->once()->andReturn($parent);
        }
        if (!in_array($case, ['session-mismatch', 'missing-session', 'parent-missing', 'parent-cross-session'], true)) {
            $existing = match ($case) {
                'phase-existing' => $observation->phase,
                'phase-mismatch' => PhaseReference::fromStrings('session', 'phase', null, 'different-agent', 'review', 1),
                default => null,
            };
            $store->shouldReceive('phase')->with('project', $observation->phase->phaseId)->once()->andReturn($existing);
            if ($existing === null) {
                $store->shouldReceive('addPhase')->with('project', $observation->phase)->once();
            }
        }
        if (!$valid) {
            $store->shouldNotReceive('append', 'get');
            $events->shouldNotReceive('trigger');
            $this->expectException(InvalidObservationRelationship::class);
            $this->expectExceptionMessage('Observation relationship is unavailable or inconsistent.');
        } else {
            $store->shouldReceive('get')->once()->andReturnNull();
            $store->shouldReceive('append')->once()->andReturn(new RecordedObservation($observation, new DateTimeImmutable('2026-10-20Z')));
            $events->shouldReceive('trigger')->once()->withArgs(static fn (ObservationRecorded $event): bool => $event->phaseId() === $observation->phase->phaseId);
        }
        (new ObservationRecorder($store, $admission, $events))->record($observation);
    }

    public static function relationshipCases(): iterable
    {
        foreach (['session-mismatch', 'missing-session', 'phase-mismatch', 'parent-missing', 'parent-cross-session'] as $case) {
            yield $case => [$case, false];
        }
        foreach (['phase-new', 'phase-existing', 'parent-valid'] as $case) {
            yield $case => [$case, true];
        }
    }

    public function test_that_dispatch_failure_is_explicitly_committed_and_preserves_private_cause(): void
    {
        $observation = ObservationFixture::session();
        $store = $this->transactionalStore();
        $admission = $this->mock(ObservationAdmission::class);
        $events = $this->mock(EventDispatcher::class);
        $admission->shouldReceive('admit')->once();
        $store->shouldReceive('session')->once()->andReturn($observation->session);
        $store->shouldReceive('get')->once()->andReturnNull();
        $store->shouldReceive('append')->once()->andReturn(new RecordedObservation($observation, new DateTimeImmutable('2026-10-20Z')));
        $failure = new RuntimeException('private subscriber diagnostic');
        $events->shouldReceive('trigger')->once()->andThrow($failure);
        try {
            (new ObservationRecorder($store, $admission, $events))->record($observation);
            self::fail('Expected committed dispatch failure.');
        } catch (CommittedObservationDispatchFailed $exception) {
            self::assertSame($failure, $exception->getPrevious());
            self::assertSame('Observation committed but event dispatch failed.', $exception->getMessage());
            self::assertTrue($observation->id->equals($exception->event->id()));
        }
    }

    public function test_that_storage_failure_prevents_event_dispatch_and_distinguishes_uncertainty(): void
    {
        foreach ([false, true] as $uncertain) {
            $store = $this->mock(ObservationStore::class);
            $admission = $this->mock(ObservationAdmission::class);
            $events = $this->mock(EventDispatcher::class);
            $cause = new RuntimeException('private storage diagnostic');
            $failure = new ObservationStorageFailed($uncertain, $cause);
            $store->shouldReceive('commitTransactional')->once()->andThrow($failure);
            $events->shouldNotReceive('trigger');
            try {
                (new ObservationRecorder($store, $admission, $events))->record(ObservationFixture::session());
                self::fail('Expected storage failure.');
            } catch (ObservationStorageFailed $exception) {
                self::assertSame($cause, $exception->getPrevious());
                self::assertSame($uncertain, $exception->outcomeUncertain);
                self::assertSame($uncertain ? 'Observation storage outcome is uncertain.' : 'Observation storage was rolled back.', $exception->getMessage());
            }
        }
    }

    private function transactionalStore(): mixed
    {
        $store = $this->mock(ObservationStore::class);
        $store->shouldReceive('commitTransactional')->once()->andReturnUsing(static fn (callable $operation): mixed => $operation());
        return $store;
    }
}
