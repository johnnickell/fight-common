<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Observation;

use DateTimeImmutable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationExtensions;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Observation\ObservationOutcome;
use Fight\Common\Domain\Mcp\Observation\RecordedObservation;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Mcp\Value\ObservationFields;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Observation::class)]
#[CoversClass(ObservationExtensions::class)]
#[CoversClass(ObservationFields::class)]
#[CoversClass(ObservationId::class)]
#[CoversClass(ObservationOutcome::class)]
#[CoversClass(ObservationRecorded::class)]
#[CoversClass(PhaseReference::class)]
#[CoversClass(RecordedObservation::class)]
#[CoversClass(SessionReference::class)]
final class ObservationTest extends UnitTestCase
{
    public function test_that_complete_session_and_phase_evidence_roundtrips_without_inferred_outcomes(): void
    {
        foreach ([ObservationFixture::session(), ObservationFixture::phase()] as $observation) {
            self::assertSame(ObservationOutcome::UNKNOWN, $observation->outcome());
            self::assertNull($observation->startedAt());
            self::assertNull($observation->endedAt());
            self::assertSame($observation->toArray(), Observation::fromArray($observation->toArray())->toArray());
            self::assertSame($observation->comparison(), Observation::fromArray($observation->toArray())->comparison());
        }
        $phase = ObservationFixture::phase([
            'phase' => PhaseReference::fromStrings('session', 'child', 'parent', 'agent', 'review', 1000000)->toArray(),
            'started_at' => '2026-10-20T12:00:00.000000Z',
            'ended_at' => '2026-10-20T12:34:56.123456Z',
            'outcome' => 'succeeded',
            'extensions' => ['vendor/z' => null, 'vendor/b' => true, 'vendor/a' => 42, 'vendor/c' => 'safe label'],
        ]);
        self::assertSame('parent', $phase->phase()->parentPhaseId());
        self::assertSame('agent', $phase->phase()->agentId());
        self::assertSame(1000000, $phase->phase()->attempt());
        self::assertSame(['vendor/a', 'vendor/b', 'vendor/c', 'vendor/z'], array_keys($phase->extensions()->toArray()));
        self::assertSame('123456', $phase->endedAt()->format('u'));
        self::assertSame(ObservationOutcome::SUCCEEDED, $phase->outcome());
        $copy = $phase->toArray();
        $copy['extensions']['vendor/a'] = 0;
        self::assertSame(42, $phase->extensions()->toArray()['vendor/a']);
    }

    public function test_that_session_references_expose_private_state_through_typed_accessors(): void
    {
        $session = SessionReference::fromStrings('project:one', 'session:two', 'consumer:three');
        $expected = ['project_id' => 'project:one', 'session_id' => 'session:two', 'consumer_reference' => 'consumer:three'];
        foreach ([$session, SessionReference::fromArray($expected)] as $reference) {
            self::assertSame([], get_object_vars($reference));
            self::assertSame('project:one', $reference->projectId());
            self::assertSame('session:two', $reference->sessionId());
            self::assertSame('consumer:three', $reference->consumerReference());
            self::assertSame($expected, $reference->toArray());
        }
    }

    public function test_that_phase_references_expose_private_state_through_typed_accessors(): void
    {
        foreach ([null, 'parent:three'] as $parent) {
            $phase = PhaseReference::fromStrings('session:one', 'phase:two', $parent, 'agent:four', 'review', 17);
            $expected = [
                'session_id' => 'session:one', 'phase_id' => 'phase:two', 'parent_phase_id' => $parent,
                'agent_id' => 'agent:four', 'name' => 'review', 'attempt' => 17,
            ];
            foreach ([$phase, PhaseReference::fromArray($expected)] as $reference) {
                self::assertSame([], get_object_vars($reference));
                self::assertSame('session:one', $reference->sessionId());
                self::assertSame('phase:two', $reference->phaseId());
                self::assertSame($parent, $reference->parentPhaseId());
                self::assertSame('agent:four', $reference->agentId());
                self::assertSame('review', $reference->name());
                self::assertSame(17, $reference->attempt());
                self::assertSame($expected, $reference->toArray());
            }
        }
    }

    public function test_that_observations_expose_private_evidence_without_changing_identity_or_payload(): void
    {
        $id = ObservationId::fromString('019a1234-5678-4abc-8def-123456789abc');
        $session = SessionReference::fromStrings('project:one', 'session:two', 'consumer:three');
        $phase = PhaseReference::fromStrings('session:two', 'phase:four', 'parent:five', 'agent:six', 'work', 7);
        $extensions = ObservationExtensions::fromArray(['vendor/count' => 42]);
        foreach ([null, $phase] as $phaseReference) {
            $observation = Observation::create(
                $id, 'producer:seven', $session, $phaseReference,
                new DateTimeImmutable('2026-10-20T12:00:00.000001+02:00'),
                new DateTimeImmutable('2026-10-20T13:00:00.000002+02:00'),
                new DateTimeImmutable('2026-10-20T14:00:00.000003+02:00'),
                ObservationOutcome::CANCELLED, $extensions,
            );
            self::assertSame([], get_object_vars($observation));
            self::assertSame($id, $observation->id());
            self::assertSame($session, $observation->session());
            self::assertSame($phaseReference, $observation->phase());
            self::assertSame($extensions, $observation->extensions());
            foreach ([$observation, Observation::fromArray($observation->toArray())] as $evidence) {
                self::assertSame([], get_object_vars($evidence));
                self::assertSame('019a1234-5678-4abc-8def-123456789abc', $evidence->id()->toString());
                self::assertSame('producer:seven', $evidence->producerId());
                self::assertSame($session->toArray(), $evidence->session()->toArray());
                self::assertSame($phaseReference?->toArray(), $evidence->phase()?->toArray());
                self::assertSame('2026-10-20T10:00:00.000001Z', $evidence->occurredAt()->format('Y-m-d\TH:i:s.u\Z'));
                self::assertSame('2026-10-20T11:00:00.000002Z', $evidence->startedAt()->format('Y-m-d\TH:i:s.u\Z'));
                self::assertSame('2026-10-20T12:00:00.000003Z', $evidence->endedAt()->format('Y-m-d\TH:i:s.u\Z'));
                self::assertSame(ObservationOutcome::CANCELLED, $evidence->outcome());
                self::assertSame(['vendor/count' => 42], $evidence->extensions()->toArray());
                self::assertSame($observation->comparison(), $evidence->comparison());
            }
        }
    }

    public function test_that_recorded_observations_expose_private_evidence_and_utc_recording_time(): void
    {
        $observation = ObservationFixture::session();
        $recorded = new RecordedObservation($observation, new DateTimeImmutable('2026-10-20T17:00:00.000123+02:00'));
        self::assertSame([], get_object_vars($recorded));
        self::assertNotSame($observation, $recorded->observation());
        self::assertSame($observation->toArray(), $recorded->observation()->toArray());
        self::assertSame($recorded->recordedAt(), $recorded->observation()->recordedAt());
        self::assertSame('2026-10-20T15:00:00.000123Z', $recorded->recordedAt()->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame($recorded->recordedAt(), $recorded->recordedAt());
    }

    public function test_that_recording_time_is_root_owned_immutable_and_excluded_from_submitted_content(): void
    {
        $submitted = ObservationFixture::session();
        $time = new DateTimeImmutable('2026-10-21T17:00:00.000001+02:00');
        $recorded = $submitted->recorded($time);
        self::assertSame([], get_object_vars($recorded));
        self::assertSame('2026-10-21T15:00:00.000001Z', $recorded->recordedAt()->format('Y-m-d\\TH:i:s.u\\Z'));
        self::assertSame($submitted->toArray(), $recorded->toArray());
        self::assertSame($submitted->comparison(), $recorded->comparison());
        self::assertTrue($recorded->matches($submitted));
        self::assertFalse($recorded->matches(ObservationFixture::session(['outcome' => 'failed'])));
        self::assertSame($recorded, $recorded->recorded(new DateTimeImmutable('2026-10-21T15:00:00.000001Z')));
        self::assertSame($recorded, (new RecordedObservation($recorded, $time))->observation());
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Observation recording time is immutable.');
        $recorded->recorded(new DateTimeImmutable('2026-10-21T15:00:00.000002Z'));
    }

    public function test_that_submitted_evidence_does_not_fabricate_a_recording_time(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Observation has not been recorded.');
        ObservationFixture::session()->recordedAt();
    }

    public function test_that_comparison_is_key_order_and_timezone_independent_but_content_sensitive(): void
    {
        $observation = ObservationFixture::session(['extensions' => ['v/b' => false, 'v/a' => 3]]);
        $other = Observation::create(
            $observation->id(), $observation->producerId(), $observation->session(), null,
            new DateTimeImmutable('2026-10-20T14:34:56.123456+02:00'),
            extensions: ObservationExtensions::fromArray(['v/a' => 3, 'v/b' => false]),
        );
        self::assertSame($observation->comparison(), $other->comparison());
        self::assertNotSame($observation->comparison(), ObservationFixture::session(['outcome' => 'failed'])->comparison());
        self::assertNotSame($observation->comparison(), ObservationFixture::session(['extensions' => ['v/a' => '3', 'v/b' => false]])->comparison());
        self::assertSame(36, strlen(ObservationId::generate()->toString()));
    }

    #[DataProvider('scalarExtensions')]
    public function test_that_referenced_input_cannot_change_the_extension_snapshot_or_comparison(string|int|bool|null $value): void
    {
        $expected = ['v/a' => $value, 'v/z' => 'control'];
        $observation = ObservationFixture::session(['extensions' => ['v/z' => 'control', 'v/a' => &$value]]);
        $comparison = $observation->comparison();

        foreach ([['body' => 'not a scalar'], str_repeat('x', 257)] as $replacement) {
            $value = $replacement;
            self::assertSame($expected, $observation->extensions()->toArray());
            self::assertSame($comparison, $observation->comparison());
            self::assertSame($comparison, Observation::fromArray($observation->toArray())->comparison());
        }
    }

    #[DataProvider('scalarExtensions')]
    public function test_that_output_copies_cannot_write_through_referenced_extensions(string|int|bool|null $value): void
    {
        $expected = ['v/a' => $value];
        $extensions = ObservationExtensions::fromArray(['v/a' => &$value]);
        $copy = $extensions->toArray();
        $copy['v/a'] = str_repeat('x', 257);
        self::assertSame($expected, $extensions->toArray());
        self::assertSame($expected['v/a'], $value);

        $outputReference = &$copy['v/a'];
        $outputReference = ['body' => 'not a scalar'];
        self::assertSame($expected, $extensions->toArray());
        self::assertSame($expected['v/a'], $value);
    }

    public static function scalarExtensions(): iterable
    {
        yield 'unicode byte boundary' => [str_repeat('測', 85).'x'];
        yield 'minimum integer' => [PHP_INT_MIN];
        yield 'zero' => [0];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'null' => [null];
    }

    public function test_that_events_contain_only_safe_committed_references_and_recording_time(): void
    {
        foreach ([ObservationFixture::session(), ObservationFixture::phase()] as $observation) {
            $event = ObservationRecorded::fromRecorded(new RecordedObservation(
                $observation, new DateTimeImmutable('2026-10-20T15:00:00.000123Z'),
            ));
            self::assertSame([
                'observation_id', 'producer_id', 'project_id', 'session_id', 'phase_id', 'recorded_at',
            ], array_keys($event->toArray()));
            self::assertSame('2026-10-20T15:00:00.000123Z', $event->toArray()['recorded_at']);
            self::assertSame($event->toArray(), ObservationRecorded::fromArray($event->toArray())->toArray());
            $message = EventMessage::create($event);
            self::assertSame('Fight.Common.Domain.Mcp.Observation.Event.ObservationRecorded', $message->toArray()['payload_type']);
            self::assertInstanceOf(ObservationRecorded::class, EventMessage::arrayDeserialize($message->toArray())->payload());
            self::assertSame($message->toArray(), EventMessage::arrayDeserialize($message->toArray())->toArray());
        }
    }

    public function test_that_recorded_events_expose_safe_state_only_through_accessors(): void
    {
        foreach ([ObservationFixture::session(), ObservationFixture::phase()] as $observation) {
            $recorded = new RecordedObservation($observation, new DateTimeImmutable('2026-10-20T17:00:00.000123+02:00'));
            $event = ObservationRecorded::fromRecorded($recorded);

            self::assertSame([], get_object_vars($event));
            self::assertSame($observation->id(), $event->id());
            self::assertSame($recorded->recordedAt(), $event->recordedAt());
            foreach ([$event, ObservationRecorded::fromArray($event->toArray())] as $fact) {
                self::assertSame([], get_object_vars($fact));
                self::assertTrue($observation->id()->equals($fact->id()));
                self::assertSame('instrumentation', $fact->producerId());
                self::assertSame('project', $fact->projectId());
                self::assertSame('session', $fact->sessionId());
                self::assertSame($observation->phase()?->phaseId(), $fact->phaseId());
                self::assertSame('2026-10-20T15:00:00.000123Z', $fact->recordedAt()->format('Y-m-d\TH:i:s.u\Z'));
                self::assertSame($event->toArray(), $fact->toArray());
            }
        }
    }

    #[DataProvider('invalidObservations')]
    public function test_that_unsupported_or_invalid_submitted_data_rejects_safely(array $changes): void
    {
        $this->expectException(DomainException::class);
        ObservationFixture::session($changes);
    }

    public static function invalidObservations(): iterable
    {
        yield 'newer version' => [['version' => 2]];
        yield 'noninteger version' => [['version' => '1']];
        yield 'extra body' => [['body' => 'concealed']];
        yield 'uuid' => [['observation_id' => 'not-a-uuid']];
        yield 'uuid type' => [['observation_id' => 12]];
        yield 'producer type' => [['producer_id' => []]];
        yield 'producer bound' => [['producer_id' => str_repeat('p', 129)]];
        yield 'empty reference' => [['producer_id' => '']];
        yield 'reference whitespace' => [['producer_id' => 'a b']];
        yield 'session shape' => [['session' => null]];
        yield 'session fields' => [['session' => []]];
        yield 'phase shape' => [['phase' => 12]];
        yield 'extension shape' => [['extensions' => null]];
        yield 'outcome type' => [['outcome' => 12]];
        yield 'outcome unknown value' => [['outcome' => 'complete']];
        yield 'timestamp type' => [['occurred_at' => 12]];
        yield 'timestamp parse' => [['occurred_at' => 'invalid']];
        yield 'timestamp rollover' => [['occurred_at' => '2026-02-30T12:00:00.000000Z']];
        yield 'timestamp year zero' => [['occurred_at' => '0000-01-01T00:00:00.000000Z']];
        yield 'timestamp precision' => [['occurred_at' => '2026-10-20T12:00:00Z']];
        yield 'reversed timing' => [['started_at' => '2026-10-20T13:00:00.000000Z', 'ended_at' => '2026-10-20T12:00:00.000000Z']];
        yield 'wrong session' => [['phase' => PhaseReference::fromStrings('other', 'phase', null, 'agent', 'review', 1)->toArray()]];
    }

    #[DataProvider('invalidPhases')]
    public function test_that_phase_bounds_and_relationships_reject(array $changes): void
    {
        $data = PhaseReference::fromStrings('session', 'phase', null, 'agent', 'review', 1)->toArray();
        $this->expectException(DomainException::class);
        PhaseReference::fromArray(array_replace($data, $changes));
    }

    public static function invalidPhases(): iterable
    {
        yield [['attempt' => 0]];
        yield [['attempt' => 1000001]];
        yield [['attempt' => '1']];
        yield [['name' => str_repeat('n', 65)]];
        yield [['parent_phase_id' => 'phase']];
        yield [['parent_phase_id' => 'bad parent']];
        yield [['agent_id' => '']];
    }

    #[DataProvider('invalidExtensions')]
    public function test_that_extension_bounds_and_shapes_reject(array $values): void
    {
        $this->expectException(DomainException::class);
        ObservationExtensions::fromArray($values);
    }

    public static function invalidExtensions(): iterable
    {
        yield [[0 => 'value']];
        yield [['unqualified' => 'value']];
        yield [[str_repeat('n', 97).'/v' => 'value']];
        yield [['v/n' => ['body']]];
        yield [['v/n' => new \stdClass()]];
        yield [['v/n' => 1.25]];
        yield [['v/n' => INF]];
        yield [['v/n' => str_repeat('x', 257)]];
        yield [['v/n' => "\xff"]];
        yield [array_fill_keys(array_map(static fn (int $n): string => 'v/k'.$n, range(1, 33)), null)];
        yield [array_fill_keys(array_map(static fn (int $n): string => 'v/k'.$n, range(1, 32)), str_repeat('x', 256))];
    }

    public function test_that_finite_boundaries_and_all_outcomes_are_explicit(): void
    {
        self::assertSame(128, strlen(SessionReference::fromStrings(str_repeat('p', 128), 's', 'r')->projectId()));
        self::assertCount(32, ObservationExtensions::fromArray(array_fill_keys(
            array_map(static fn (int $n): string => 'v/k'.$n, range(1, 32)), 1,
        ))->toArray());
        foreach (ObservationOutcome::cases() as $outcome) {
            self::assertSame($outcome, ObservationFixture::session(['outcome' => $outcome->value])->outcome());
        }
        $this->expectException(DomainException::class);
        Observation::create(ObservationId::generate(), 'p', SessionReference::fromStrings('p', 's', 'r'), null, new DateTimeImmutable('+10000-01-01T00:00:00Z'));
    }

    public function test_that_invalid_event_identity_is_rejected_without_echoing_it(): void
    {
        $data = ObservationRecorded::fromRecorded(new RecordedObservation(
            ObservationFixture::session(), new DateTimeImmutable('2026-10-20T15:00:00Z'),
        ))->toArray();
        $data['observation_id'] = 'invalid';
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid observation identity.');
        ObservationRecorded::fromArray($data);
    }
}
