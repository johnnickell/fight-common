<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Notice;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Command\RecordNotice;
use Fight\Common\Domain\Mcp\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeMetadata;
use Fight\Common\Domain\Mcp\Notice\NoticeOccurrence;
use Fight\Common\Domain\Mcp\Notice\NoticeRelationships;
use Fight\Common\Domain\Mcp\Notice\RecordedNotice;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Notice::class)]
#[CoversClass(NoticeMetadata::class)]
#[CoversClass(NoticeRelationships::class)]
#[CoversClass(NoticeOccurrence::class)]
#[CoversClass(RecordedNotice::class)]
#[CoversClass(RecordNotice::class)]
#[CoversClass(NoticeRecorded::class)]
final class NoticeTest extends UnitTestCase
{
    public function test_that_notice_snapshots_preserve_distinctions_and_canonicalize_unordered_fields(): void
    {
        $label = 'safe';
        $recipient = 'recipient-agent';
        $data = NoticeFixture::data();
        $data['metadata']['app/label'] =& $label;
        $data['relationships']['recipients'][0] =& $recipient;
        $notice = Notice::fromArray($data);
        $command = new RecordNotice($notice);
        $label = 'changed'; $recipient = 'changed';
        self::assertSame('safe', $notice->metadata()->toArray()['app/label']);
        self::assertSame(['other-recipient', 'recipient-agent'], $notice->relationships()->toArray()['recipients']);
        self::assertSame('2026-10-25T01:02:03.12Z', $notice->occurrence()->toString());
        self::assertSame($notice, $command->notice());
        self::assertSame($notice->comparison(), RecordNotice::fromArray($command->toArray())->notice()->comparison());
        $permuted = $notice->toArray();
        $permuted['relationships']['recipients'] = array_reverse($permuted['relationships']['recipients']);
        $permuted['metadata'] = array_reverse($permuted['metadata'], true);
        self::assertSame($notice->comparison(), Notice::fromArray($permuted)->comparison());
        $recorded = new RecordedNotice($notice, RecordedNotice::MAX_POSITION, '2026-10-25T01:02:04.000001Z');
        self::assertSame($notice, $recorded->notice());
        self::assertSame(2147483647, $recorded->position());
        self::assertSame('2026-10-25T01:02:04.000001Z', $recorded->recordedAt());
        $event = NoticeRecorded::fromRecorded($recorded);
        self::assertSame(['version' => 1, 'id' => 'notice-1', 'producer' => 'producer', 'scope' => 'scope', 'position' => 2147483647, 'recorded_at' => '2026-10-25T01:02:04.000001Z'], $event->toArray());
        self::assertSame($event->toArray(), NoticeRecorded::fromArray($event->toArray())->toArray());
    }

    public function test_that_unknown_and_broadcast_relationships_are_not_conflated(): void
    {
        $unknown = ['subject' => null, 'recipients' => null, 'author' => null, 'command' => null, 'event' => null, 'action' => null];
        $notice = Notice::fromArray(NoticeFixture::data(['occurred_at' => null, 'relationships' => $unknown, 'metadata' => []]));
        self::assertNull($notice->occurrence());
        self::assertSame($unknown, $notice->relationships()->toArray());
        $broadcast = $notice->toArray(); $broadcast['relationships']['recipients'] = [];
        self::assertNotSame($notice->comparison(), Notice::fromArray($broadcast)->comparison());
        self::assertSame([], Notice::fromArray($broadcast)->relationships()->toArray()['recipients']);
    }

    #[DataProvider('times')]
    public function test_that_occurrence_preserves_only_supplied_precision(string $time): void
    {
        self::assertSame($time, NoticeOccurrence::fromString($time)->toString());
    }

    public static function times(): iterable
    {
        foreach (['0001-01-01T00:00:00Z', '9999-12-31T23:59:59.999999Z', '2024-02-29T00:00:00.0Z', '2026-10-25T01:02:03.001Z'] as $time) { yield [$time]; }
    }

    #[DataProvider('invalidTimes')]
    public function test_that_invalid_occurrence_rejects_without_rollover(string $time): void
    {
        $this->expectException(DomainException::class);
        NoticeOccurrence::fromString($time);
    }

    public static function invalidTimes(): iterable
    {
        foreach (['0000-01-01T00:00:00Z', '2026-02-29T00:00:00Z', '2026-01-01T24:00:00Z', '2026-01-01T00:00:60Z', '2026-01-01T00:00:00.1234567Z', '2026-01-01T00:00:00+00:00', '2026-01-01T00:00:00.Z', '2026-01-01T00:00:00Z\n'] as $time) { yield [$time]; }
    }

    #[DataProvider('invalidSubmissions')]
    public function test_that_invalid_or_protected_payload_shapes_reject(array $data): void
    {
        $this->expectException(DomainException::class);
        Notice::fromArray($data);
    }

    public static function invalidSubmissions(): iterable
    {
        foreach ([['version' => 2], ['body' => 'protected'], ['task' => ['state' => 'full']], ['wire' => 'transcript'], ['metadata' => ['app/body' => ['protected']]], ['metadata' => ['app/x' => new \stdClass()]], ['metadata' => ['app/float' => 1.2]], ['metadata' => ['app/float' => INF]], ['metadata' => ['bad-key' => true]], ['metadata' => ['app/text' => "secret\nbody"]], ['metadata' => ['app/text' => "\xff"]], ['metadata' => ['app/text' => str_repeat('x', 129)]], ['metadata' => array_fill_keys(array_map(static fn ($i) => 'app/k'.$i, range(0, 16)), 1)], ['metadata' => array_fill_keys(array_map(static fn ($i) => 'app/k'.$i, range(0, 15)), str_repeat('x', 128))], ['metadata' => [str_repeat('a', 63).'/x' => 1]], ['metadata' => null], ['relationships' => null], ['occurred_at' => 123], ['id' => ''], ['id' => 'with space'], ['id' => "foo\n"], ['id' => 'é'], ['id' => str_repeat('x', 97)], ['producer' => false]] as $change) {
            yield [NoticeFixture::data($change)];
        }
        foreach ([['subject' => []], ['author' => 3], ['command' => 'bad space'], ['extra' => null], ['recipients' => 'not-list'], ['recipients' => ['key' => 'id']], ['recipients' => array_fill(0, 33, 'id')], ['recipients' => ['id', 'id']], ['recipients' => [null]]] as $change) {
            $data = NoticeFixture::data(); $data['relationships'] = array_replace($data['relationships'], $change); yield [$data];
        }
        $data = NoticeFixture::data(); unset($data['id']); yield [$data];
        $data = NoticeFixture::data();
        foreach (['id', 'producer', 'scope', 'topic', 'type'] as $key) { $data[$key] = str_repeat('x', 96); }
        $data['relationships']['recipients'] = array_map(static fn ($i) => str_pad((string) $i, 96, 'x'), range(0, 31));
        foreach (['subject', 'author', 'command', 'event', 'action'] as $key) { $data['relationships'][$key] = str_repeat('x', 96); }
        $data['metadata'] = array_fill_keys(array_map(static fn ($i) => 'app/k'.$i, range(0, 13)), str_repeat('x', 128));
        yield [$data];
    }

    public function test_that_exact_finite_field_and_encoded_total_bounds_are_accepted(): void
    {
        self::assertSame(96, strlen(Notice::reference(str_repeat('x', 96))));
        self::assertCount(16, NoticeMetadata::fromArray(array_fill_keys(array_map(static fn ($i) => 'app/k'.$i, range(0, 15)), true))->toArray());
        self::assertSame(str_repeat('x', 128), NoticeMetadata::fromArray(['app/k' => str_repeat('x', 128)])->toArray()['app/k']);
        $relations = NoticeFixture::data()['relationships'];
        $relations['recipients'] = array_map(static fn ($i) => 'id-'.$i, range(0, 31));
        self::assertCount(32, NoticeRelationships::fromArray($relations)->toArray()['recipients']);
        $data = NoticeFixture::data();
        $data['metadata'] = ['app/k' => str_repeat('x', 128)];
        $data['relationships']['recipients'] = array_map(static fn ($i) => str_pad((string) $i, 96, 'x'), range(0, 31));
        // Independent encoded size arithmetic varies a bounded reference and scalar labels to reach the total boundary.
        foreach (['id', 'producer', 'scope', 'topic', 'type'] as $key) { $data[$key] = str_repeat('x', 96); }
        foreach (['subject', 'author', 'command', 'event', 'action'] as $key) { $data['relationships'][$key] = str_repeat('x', 96); }
        $data['metadata'] = array_fill_keys(array_map(static fn ($i) => 'app/k'.$i, range(0, 11)), str_repeat('x', 128));
        $base = strlen(json_encode(Notice::fromArray($data)->toArray(), JSON_THROW_ON_ERROR));
        $needed = Notice::MAX_BYTES - $base - (strlen(json_encode(['app/pad' => ''], JSON_THROW_ON_ERROR)) - 1);
        self::assertGreaterThanOrEqual(0, $needed); self::assertLessThanOrEqual(128, $needed);
        $data['metadata']['app/pad'] = str_repeat('p', $needed);
        self::assertSame(6144, strlen(Notice::fromArray($data)->comparison()));
        $data['metadata']['app/pad'] .= 'p';
        $this->expectException(DomainException::class); Notice::fromArray($data);
    }

    public function test_that_metadata_encoded_bounds_include_escaping_unicode_and_native_integer_kinds(): void
    {
        $values = array_fill_keys(array_map(static fn ($i) => 'app/k'.$i, range(0, 13)), str_repeat('x', 128));
        $base = strlen(json_encode($values, JSON_THROW_ON_ERROR));
        $needed = 2048 - $base - (strlen(json_encode(['app/pad' => ''], JSON_THROW_ON_ERROR)) - 1);
        self::assertGreaterThanOrEqual(0, $needed); self::assertLessThanOrEqual(128, $needed);
        $values['app/pad'] = str_repeat('p', $needed);
        self::assertSame(2048, strlen(json_encode(NoticeMetadata::fromArray($values)->toArray(), JSON_THROW_ON_ERROR)));
        $scalars = ['app/min' => PHP_INT_MIN, 'app/max' => PHP_INT_MAX, 'app/unicode' => 'état 😀 / "', 'app/empty' => '', 'app/false' => false];
        self::assertSame(NoticeMetadata::fromArray($scalars)->toArray(), NoticeMetadata::fromArray(json_decode(json_encode($scalars, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))->toArray());
        $key = str_repeat('a', 62).'/x';
        self::assertSame(64, strlen(array_key_first(NoticeMetadata::fromArray([$key => true])->toArray())));
        $values['app/pad'] .= 'p';
        $this->expectException(DomainException::class); NoticeMetadata::fromArray($values);
    }

    #[DataProvider('invalidReceipts')]
    public function test_that_receipt_bounds_reject(int $position, string $time): void
    {
        $this->expectException(DomainException::class);
        new RecordedNotice(Notice::fromArray(NoticeFixture::data()), $position, $time);
    }

    public static function invalidReceipts(): iterable
    {
        yield [0, '2026-10-25T01:02:03.000000Z']; yield [2147483648, '2026-10-25T01:02:03.000000Z']; yield [1, '2026-10-25T01:02:03Z']; yield [1, '2026-02-29T01:02:03.000000Z'];
    }

    #[DataProvider('invalidFacts')]
    public function test_that_fact_reconstruction_rejects_unsafe_or_malformed_shapes(array $change): void
    {
        $data = ['version' => 1, 'id' => 'id', 'producer' => 'producer', 'scope' => 'scope', 'position' => 1, 'recorded_at' => '2026-10-25T01:02:03.000000Z'];
        $this->expectException(DomainException::class);
        NoticeRecorded::fromArray(array_replace($data, $change));
    }

    public static function invalidFacts(): iterable
    {
        foreach ([['version' => 2], ['position' => 0], ['position' => 2147483648], ['position' => '1'], ['recorded_at' => null], ['recorded_at' => '2026-10-25T01:02:03Z'], ['recorded_at' => '2026-02-29T01:02:03.000000Z'], ['metadata' => []], ['id' => 'bad space']] as $change) { yield [$change]; }
    }
}
