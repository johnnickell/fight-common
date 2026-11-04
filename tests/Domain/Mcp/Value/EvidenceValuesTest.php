<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Value;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Activity\PhaseActivity;
use Fight\Common\Domain\Mcp\Detail\CaptureCompleteness;
use Fight\Common\Domain\Mcp\Detail\DetailCapture;
use Fight\Common\Domain\Mcp\Detail\DetailKind;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\OutputSegment;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Notice\NoticeMetadata;
use Fight\Common\Domain\Mcp\Notice\NoticeOccurrence;
use Fight\Common\Domain\Mcp\Notice\NoticeRelationships;
use Fight\Common\Domain\Mcp\Notice\NoticeRemovalSelection;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Observation\Command\RecordProtectedDetail;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationExtensions;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Observation\ObservationOccurrence;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;
use Fight\Common\Domain\Mcp\Usage\UsageCompleteness;
use Fight\Common\Domain\Mcp\Usage\UsageMeasurement;
use Fight\Common\Domain\Mcp\Usage\UsageTotal;
use Fight\Common\Domain\Mcp\Value\ObservationName;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\Value;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Observation::class)]
#[CoversClass(RecordProtectedDetail::class)]
#[CoversClass(ObservationRecorded::class)]
#[CoversClass(ListExecutions::class)]
#[CoversClass(SessionReference::class)]
#[CoversClass(PhaseReference::class)]
#[CoversClass(ExecutionSelection::class)]
#[CoversClass(DetailSelection::class)]
#[CoversClass(ObservationExtensions::class)]
#[CoversClass(ObservationOccurrence::class)]
#[CoversClass(PhaseActivity::class)]
#[CoversClass(UsageMeasurement::class)]
#[CoversClass(UsageTotal::class)]
#[CoversClass(DetailCapture::class)]
#[CoversClass(OutputSegment::class)]
#[CoversClass(ProtectedContent::class)]
#[CoversClass(NoticeMetadata::class)]
#[CoversClass(NoticeRelationships::class)]
#[CoversClass(NoticeOccurrence::class)]
#[CoversClass(NoticeSelection::class)]
#[CoversClass(NoticeRemovalSelection::class)]
#[CoversClass(SubscriberSelection::class)]
#[CoversClass(ObservationReference::class)]
#[CoversClass(ObservationName::class)]
final class EvidenceValuesTest extends UnitTestCase
{
    #[DataProvider('structuredValues')]
    public function test_that_structured_values_reconstruct_compare_hash_and_emit_deliberate_json(string $class, array $expected): void
    {
        $text = json_encode($expected, JSON_THROW_ON_ERROR);
        $value = $class::fromString($text);
        self::assertInstanceOf(Value::class, $value);
        self::assertSame($expected, $value->toArray());
        self::assertSame($text, $value->toString());
        self::assertSame($text, (string) $value);
        self::assertSame($text, $value->hashValue());
        self::assertSame($expected, $value->jsonSerialize());
        self::assertSame($text, json_encode($value, JSON_THROW_ON_ERROR));
        self::assertTrue($value->equals($class::fromString($text)));
        self::assertTrue($value->equals($value));
        self::assertFalse($value->equals($text));
        self::assertFalse($value->equals(ObservationReference::fromString('same')));
    }

    public static function structuredValues(): iterable
    {
        $id = '019a1234-5678-4abc-8def-123456789abc';
        yield 'session relationship' => [SessionReference::class, ['project_id' => 'P:1', 'session_id' => 'S:1', 'consumer_reference' => 'consumer']];
        yield 'phase relationship' => [PhaseReference::class, ['session_id' => 'S:1', 'phase_id' => 'phase', 'parent_phase_id' => null, 'agent_id' => 'agent', 'name' => 'review', 'attempt' => 2]];
        yield 'execution selection' => [ExecutionSelection::class, ['projects' => ['P:1'], 'filters' => ['session_id' => 'S:1']]];
        yield 'detail selection' => [DetailSelection::class, ['project_id' => 'P:1', 'session_id' => 'S:1', 'phase_id' => 'phase', 'producer_id' => 'producer', 'activity_id' => $id]];
        yield 'extensions' => [ObservationExtensions::class, ['vendor/a' => null, 'vendor/b' => 0, 'vendor/c' => false]];
        yield 'empty extensions' => [ObservationExtensions::class, []];
        yield 'activity' => [PhaseActivity::class, ['version' => 1, 'kind' => 'vendor/call', 'summary' => '', 'model_id' => null, 'usage' => []]];
        yield 'measured zero' => [UsageMeasurement::class, ['version' => 1, 'metric' => 'vendor/tokens', 'unit' => 'token', 'provenance' => 'provider', 'completeness' => 'complete', 'value' => '0']];
        yield 'missing measurement' => [UsageMeasurement::class, ['version' => 1, 'metric' => 'vendor/tokens', 'unit' => 'token', 'provenance' => 'provider', 'completeness' => 'unknown', 'value' => null]];
        yield 'usage total' => [UsageTotal::class, ['metric' => 'vendor/tokens', 'unit' => 'token', 'provenance' => 'provider', 'measured' => '12.5', 'counts' => ['complete' => 1, 'partial' => 0, 'unknown' => 1, 'unavailable' => 0]]];
        yield 'unknown total' => [UsageTotal::class, ['metric' => 'vendor/tokens', 'unit' => 'token', 'provenance' => 'provider', 'measured' => null, 'counts' => ['complete' => 0, 'partial' => 0, 'unknown' => 1, 'unavailable' => 0]]];
        yield 'capture' => [DetailCapture::class, ['version' => 1, 'activity_id' => $id, 'kind' => 'message', 'content_type' => 'text/plain', 'completeness' => 'complete', 'segment' => null]];
        yield 'segment' => [OutputSegment::class, ['stream' => 'stderr', 'index' => 2, 'offset' => 2147483647]];
        yield 'empty protected body' => [ProtectedContent::class, ['version' => 1, 'body' => '']];
        yield 'absent protected body' => [ProtectedContent::class, ['version' => 1, 'body' => null]];
        yield 'notice metadata' => [NoticeMetadata::class, ['vendor/a' => '', 'vendor/b' => false]];
        yield 'empty notice metadata' => [NoticeMetadata::class, []];
        yield 'unknown audience' => [NoticeRelationships::class, ['subject' => null, 'recipients' => null, 'author' => null, 'command' => null, 'event' => null, 'action' => null]];
        yield 'broadcast audience' => [NoticeRelationships::class, ['subject' => null, 'recipients' => [], 'author' => null, 'command' => null, 'event' => null, 'action' => null]];
        yield 'notice selection' => [NoticeSelection::class, ['scopes' => ['scope/a'], 'filters' => []]];
        yield 'removal incarnation' => [NoticeRemovalSelection::class, ['version' => 1, 'namespace' => str_repeat('a', 32), 'targets' => [['scope' => 'scope/a', 'producer' => 'producer', 'id' => 'id', 'position' => 12]]]];
        yield 'subscriber selection' => [SubscriberSelection::class, ['scopes' => ['scope/a'], 'filters' => ['opened_from' => 1, 'requested_resource' => 'file:///A%20B']]];
    }

    #[DataProvider('structuredValues')]
    public function test_that_structured_values_reject_non_structured_text(string $class, array $unused): void
    {
        $this->expectException(DomainException::class);
        $class::fromString('{broken');
    }

    public function test_that_value_equality_distinguishes_semantic_changes_and_preserves_source_precision(): void
    {
        $extensions = ObservationExtensions::fromArray(['v/b' => 0, 'v/a' => false]);
        self::assertTrue($extensions->equals(ObservationExtensions::fromArray(['v/a' => false, 'v/b' => 0])));
        self::assertFalse($extensions->equals(ObservationExtensions::fromArray(['v/a' => false, 'v/b' => '0'])));
        $measurement = UsageMeasurement::create('v/tokens', 'token', 'provider', UsageCompleteness::COMPLETE, '0012.500');
        self::assertSame('12.5', $measurement->value());
        self::assertTrue($measurement->equals(UsageMeasurement::fromString($measurement->toString())));
        self::assertFalse($measurement->equals(UsageMeasurement::create('v/tokens', 'token', 'provider', UsageCompleteness::PARTIAL, '12.5')));
        $total = UsageTotal::fromMeasurement($measurement)->plus(UsageTotal::fromMeasurement($measurement));
        self::assertSame('25', $total->toArray()['measured']);
        self::assertTrue($total->equals(UsageTotal::fromString($total->toString())));
        foreach ([ObservationOccurrence::class, NoticeOccurrence::class] as $class) {
            foreach (['', '.0', '.12', '.120', '.1200', '.12000', '.120000'] as $fraction) {
                $text = '2026-10-20T12:34:56'.$fraction.'Z';
                $occurrence = $class::fromString($text);
                self::assertSame($text, $occurrence->toString());
                self::assertSame($text, $occurrence->hashValue());
                self::assertSame(json_encode($text), json_encode($occurrence));
                self::assertTrue($occurrence->equals($class::fromString($text)));
            }

            self::assertFalse($class::fromString('2026-10-20T12:34:56Z')->equals($class::fromString('2026-10-20T12:34:56.0Z')));
        }

        $unknown = NoticeRelationships::fromString('{"subject":null,"recipients":null,"author":null,"command":null,"event":null,"action":null}');
        self::assertFalse($unknown->equals(NoticeRelationships::fromArray(array_replace($unknown->toArray(), ['recipients' => []]))));
        self::assertFalse(ProtectedContent::fromString('{"version":1,"body":null}')->equals(ProtectedContent::fromString('{"version":1,"body":""}')));
    }

    public function test_that_protected_value_reconstruction_does_not_bypass_capture_constraints(): void
    {
        $content = ProtectedContent::fromString(json_encode(['version' => 1, 'body' => str_repeat('x', 16385)]));
        self::assertSame(16385, strlen($content->body()));
        $capture = DetailCapture::create(ObservationId::generate(), DetailKind::PROCESS_OUTPUT, 'text/plain', CaptureCompleteness::COMPLETE, OutputSegment::create('stdout', 0, 0));
        $this->expectException(DomainException::class);
        ProtectedContent::fromArray($capture, $content->toArray());
    }

    #[DataProvider('invalidProtectedContent')]
    public function test_that_protected_reconstruction_rejects_invalid_or_oversized_bytes(array $data): void
    {
        $this->expectException(DomainException::class);
        ProtectedContent::fromString(json_encode($data));
    }

    public static function invalidProtectedContent(): iterable
    {
        yield [[]];
        yield [['version' => 2, 'body' => null]];
        yield [['version' => 1, 'body' => false]];
        yield [['version' => 1, 'body' => str_repeat('x', 65537)]];
    }

    #[DataProvider('invalidTotal')]
    public function test_that_total_reconstruction_validates_evidence_not_just_json(array $changes): void
    {
        $data = ['metric' => 'v/tokens', 'unit' => 'token', 'provenance' => 'provider', 'measured' => '12.5', 'counts' => ['complete' => 1, 'partial' => 0, 'unknown' => 0, 'unavailable' => 0]];
        $this->expectException(DomainException::class);
        UsageTotal::fromArray(array_replace($data, $changes));
    }

    public static function invalidTotal(): iterable
    {
        yield [['extra' => 1]];
        yield [['counts' => null]];
        yield [['counts' => []]];
        yield [['counts' => ['complete' => -1, 'partial' => 0, 'unknown' => 0, 'unavailable' => 0]]];
        yield [['counts' => ['complete' => '1', 'partial' => 0, 'unknown' => 0, 'unavailable' => 0]]];
        yield [['counts' => ['complete' => 0, 'partial' => 0, 'unknown' => 0, 'unavailable' => 0]]];
        yield [['measured' => null]];
        yield [['measured' => 12.5]];
        yield [['measured' => '-1']];
        yield [['metric' => 'not-namespaced']];
    }

    public function test_that_instrumentation_names_and_references_remain_independent_exact_values(): void
    {
        $reference = ObservationReference::fromString('A:1._-');
        self::assertSame('A:1._-', $reference->toString());
        self::assertTrue($reference->equals(ObservationReference::fromString('A:1._-')));
        self::assertFalse($reference->equals(ObservationReference::fromString('a:1._-')));
        self::assertSame(str_repeat('x', 128), ObservationReference::fromString(str_repeat('x', 128))->toString());
        $name = ObservationName::fromString('vendor/a');
        self::assertSame('vendor/a', $name->toString());
        self::assertTrue($name->equals(ObservationName::fromString('vendor/a')));
        self::assertSame('a/'.str_repeat('x', 94), ObservationName::fromString('a/'.str_repeat('x', 94))->toString());
    }

    #[DataProvider('invalidStructures')]
    public function test_that_owning_decoders_reject_missing_structural_fields(string $class, array $data): void
    {
        $this->expectException(DomainException::class);
        $class::fromArray($data);
    }

    public static function invalidStructures(): iterable
    {
        yield [Observation::class, ['version' => 1]];
        yield [RecordProtectedDetail::class, []];
        yield [ObservationRecorded::class, []];
        yield [PhaseReference::class, []];
        yield [ListExecutions::class, []];
    }

    #[DataProvider('invalidLexicalValues')]
    public function test_that_instrumentation_values_reject_the_wrong_grammar(string $class, mixed $value): void
    {
        $this->expectException(DomainException::class);
        $class::fromString($value);
    }

    public static function invalidLexicalValues(): iterable
    {
        foreach ([null, 1, '', 'a/b', ' padded', str_repeat('x', 129)] as $value) {
            yield [ObservationReference::class, $value];
        }

        foreach ([null, 1, '', 'Vendor/a', 'vendor', 'a/'.str_repeat('x', 95)] as $value) {
            yield [ObservationName::class, $value];
        }
    }
}
