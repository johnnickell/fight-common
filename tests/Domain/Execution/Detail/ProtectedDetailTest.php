<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Detail;

use DateTimeImmutable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\CaptureCompleteness;
use Fight\Common\Domain\Execution\Detail\DetailCapture;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\OutputSegment;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Observation\Command\RecordProtectedDetail;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationOccurrence;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DetailCapture::class)]
#[CoversClass(DetailKind::class)]
#[CoversClass(CaptureCompleteness::class)]
#[CoversClass(OutputSegment::class)]
#[CoversClass(ProtectedContent::class)]
#[CoversClass(RecordProtectedDetail::class)]
#[CoversClass(Observation::class)]
final class ProtectedDetailTest extends UnitTestCase
{
    public function test_that_typed_capture_roundtrips_all_kinds_and_honest_absence_without_body_in_safe_evidence(): void
    {
        foreach (DetailKind::cases() as $kind) {
            foreach (CaptureCompleteness::cases() as $state) {
                $segment = $kind === DetailKind::PROCESS_OUTPUT ? OutputSegment::create('stderr', 3, 42) : null;
                $capture = DetailCapture::create(ObservationFixture::activity()->id(), $kind, 'application/json', $state, $segment);
                $body = in_array($state, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true) ? "private-測\r\n\0" : null;
                $observation = ObservationFixture::detail(['detail' => $capture->toArray()]);
                $content = ProtectedContent::create($capture, $body);
                $command = new RecordProtectedDetail($observation, $content);
                foreach ([$command, RecordProtectedDetail::fromArray($command->toArray())] as $copy) {
                    self::assertSame([], get_object_vars($copy));
                    self::assertSame($observation->toArray(), $copy->observation()->toArray());
                    self::assertSame($body, $copy->content()->body());
                    self::assertTrue($content->matches($copy->content()));
                    $metadata = $copy->observation()->detail();
                    self::assertSame($kind, $metadata->kind());
                    self::assertSame($state, $metadata->completeness());
                    self::assertSame('application/json', $metadata->contentType());
                    self::assertTrue($metadata->activityId()->equals(ObservationFixture::activity()->id()));
                    self::assertSame($segment?->toArray(), $metadata->segment()?->toArray());
                    self::assertTrue($metadata->acceptsActivity($observation, ObservationFixture::activity()));
                    self::assertFalse($metadata->acceptsActivity($observation, ObservationFixture::phase()));
                    self::assertSame($capture->toArray(), $copy->observation()->recorded(new DateTimeImmutable('now'))->detail()->toArray());
                    self::assertStringNotContainsString('private-', $copy->observation()->comparison());
                }
                self::assertSame($observation, $command->observation());
                self::assertSame($content, $command->content());
            }
        }
        $segment = OutputSegment::create('combined', 1000000, 2147483647);
        self::assertSame('combined', $segment->stream());
        self::assertSame(1000000, $segment->index());
        self::assertSame(2147483647, $segment->offset());
        self::assertSame($segment->toArray(), OutputSegment::fromArray($segment->toArray())->toArray());
        self::assertSame('stdout', OutputSegment::create('stdout', 0, 0)->stream());
    }

    public function test_that_byte_limits_accept_exact_boundaries_and_do_not_claim_redaction_or_json_validation(): void
    {
        foreach ([DetailKind::MESSAGE, DetailKind::TOOL_ARGUMENTS, DetailKind::TOOL_RESULT, DetailKind::PROCESS_OUTPUT] as $kind) {
            $capture = DetailCapture::create(ObservationFixture::activity()->id(), $kind, 'application/json', CaptureCompleteness::PARTIAL, $kind === DetailKind::PROCESS_OUTPUT ? OutputSegment::create('stdout', 0, 0) : null);
            $limit = $kind === DetailKind::PROCESS_OUTPUT ? 16384 : 65536;
            self::assertSame(str_repeat('x', $limit), ProtectedContent::create($capture, str_repeat('x', $limit))->body());
            self::assertSame('', ProtectedContent::create($capture, '')->body());
            self::assertFalse(ProtectedContent::create($capture, 'a')->matches(ProtectedContent::create($capture, 'b')));
            try { ProtectedContent::create($capture, str_repeat('x', $limit + 1)); self::fail('Expected finite byte limit.'); }
            catch (DomainException $exception) { self::assertSame('Invalid or oversized protected content.', $exception->getMessage()); }
        }
        $capture = DetailCapture::create(ObservationFixture::activity()->id(), DetailKind::MESSAGE, str_repeat('a', 63).'/'.str_repeat('b', 64), CaptureCompleteness::COMPLETE);
        self::assertSame(128, strlen($capture->contentType()));
    }

    #[DataProvider('invalidCaptures')]
    public function test_that_invalid_capture_metadata_rejects_without_echoing_input(array $changes): void
    {
        $this->expectException(DomainException::class);
        DetailCapture::fromArray(array_replace(ObservationFixture::detail()->detail()->toArray(), $changes));
    }

    public static function invalidCaptures(): iterable
    {
        foreach ([['version' => 2], ['version' => '1'], ['body' => 'private'], ['activity_id' => 3], ['activity_id' => 'private'], ['kind' => null], ['kind' => 'private'], ['content_type' => null], ['content_type' => 'text/plain;secret=private'], ['content_type' => str_repeat('a', 129).'/b'], ['completeness' => false], ['completeness' => 'private'], ['segment' => false], ['segment' => ['stream' => 'stdout', 'index' => 0, 'offset' => 0]], ['kind' => 'process_output']] as $data) { yield [$data]; }
    }

    #[DataProvider('invalidSegments')]
    public function test_that_invalid_segment_coordinates_reject(array $changes): void
    {
        $this->expectException(DomainException::class);
        OutputSegment::fromArray(array_replace(['stream' => 'stdout', 'index' => 0, 'offset' => 0], $changes));
    }

    public static function invalidSegments(): iterable
    {
        foreach ([['body' => 'private'], ['stream' => 1], ['stream' => 'private'], ['index' => '0'], ['index' => -1], ['index' => 1000001], ['offset' => '0'], ['offset' => -1], ['offset' => 2147483648]] as $data) { yield [$data]; }
    }

    public function test_that_content_and_command_structure_reject_before_any_storage(): void
    {
        $observation = ObservationFixture::detail();
        $capture = $observation->detail();
        foreach ([['version' => 2, 'body' => 'private'], ['version' => 1, 'body' => 42], ['version' => 1, 'body' => null], ['version' => 1, 'body' => "\xff"], ['version' => 1, 'body' => 'private', 'extra' => true]] as $data) {
            try { ProtectedContent::fromArray($capture, $data); self::fail('Expected safe content rejection.'); }
            catch (DomainException $exception) { self::assertStringNotContainsString('private', $exception->getMessage()); }
        }
        foreach ([CaptureCompleteness::ABSENT, CaptureCompleteness::UNAVAILABLE, CaptureCompleteness::EXPIRED] as $state) {
            try { ProtectedContent::create(DetailCapture::create($capture->activityId(), DetailKind::MESSAGE, 'text/plain', $state), 'private'); self::fail('Absence cannot carry bytes.'); }
            catch (DomainException $exception) { self::assertSame('Invalid or oversized protected content.', $exception->getMessage()); }
        }
        $end = DetailCapture::create($capture->activityId(), DetailKind::PROCESS_OUTPUT, 'text/plain', CaptureCompleteness::COMPLETE, OutputSegment::create('stdout', 0, 2147483647));
        self::assertSame('', ProtectedContent::create($end, '')->body());
        try { ProtectedContent::create($end, 'x'); self::fail('Expected bounded ending offset.'); } catch (DomainException) {}
        $content = ProtectedContent::create($capture, 'private');
        try { new RecordProtectedDetail(ObservationFixture::phase(), $content); self::fail('Expected detail-only command.'); } catch (DomainException) {}
        foreach ([['observation' => null, 'content' => []], ['observation' => $observation->toArray(), 'content' => null], ['observation' => ObservationFixture::phase()->toArray(), 'content' => $content->toArray()]] as $data) {
            try { RecordProtectedDetail::fromArray($data); self::fail('Expected command rejection.'); } catch (DomainException) {}
        }
    }

    #[DataProvider('invalidObservations')]
    public function test_that_detail_observation_structure_cannot_smuggle_other_evidence(array $changes): void
    {
        $this->expectException(DomainException::class);
        Observation::fromArray(array_replace(ObservationFixture::detail()->toArray(), $changes));
    }

    public static function invalidObservations(): iterable
    {
        foreach ([['detail' => false], ['phase' => null], ['activity' => []], ['started_at' => '2026-10-20T00:00:00.000000Z'], ['ended_at' => '2026-10-20T00:00:00.000000Z'], ['occurred_at' => false], ['outcome' => 'succeeded'], ['extensions' => ['v/body' => 'private']], ['observation_id' => '019a1234-5678-4abc-8def-123456789abe']] as $changes) { yield [$changes]; }
    }
}
