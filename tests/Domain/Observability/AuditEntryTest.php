<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Observability;

use DateTimeImmutable;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Common\Domain\Observability\AuditEntryId;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuditEntry::class)]
class AuditEntryTest extends UnitTestCase
{
    public function test_that_record_creates_entry_with_generated_id_and_current_time(): void
    {
        $before = new DateTimeImmutable();
        $entry = AuditEntry::record('user:42', 'order.placed', ['order_id' => 'ORD-1']);
        $after = new DateTimeImmutable();

        self::assertSame('user:42', $entry->actor());
        self::assertSame('order.placed', $entry->action());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $entry->timestamp()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $entry->timestamp()->getTimestamp());
        self::assertSame('ORD-1', $entry->context()->get('order_id'));
    }

    public function test_that_record_with_empty_context_is_valid(): void
    {
        $entry = AuditEntry::record('system', 'deploy');

        self::assertSame('system', $entry->actor());
        self::assertSame('deploy', $entry->action());
        self::assertTrue($entry->context()->isEmpty());
    }

    public function test_that_constructor_sets_all_fields(): void
    {
        $id = AuditEntryId::generate();
        $timestamp = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $context = Meta::create(['key' => 'value']);

        $entry = new AuditEntry($id, 'actor', 'action', $timestamp, $context);

        self::assertTrue($id->equals($entry->id()));
        self::assertSame('actor', $entry->actor());
        self::assertSame('action', $entry->action());
        self::assertSame($timestamp, $entry->timestamp());
        self::assertSame('value', $entry->context()->get('key'));
    }

    public function test_that_to_array_contains_all_fields(): void
    {
        $entry = AuditEntry::record('user:1', 'login', ['ip' => '127.0.0.1']);

        $array = $entry->toArray();

        self::assertArrayHasKey('id', $array);
        self::assertSame('user:1', $array['actor']);
        self::assertSame('login', $array['action']);
        self::assertArrayHasKey('timestamp', $array);
        self::assertSame('127.0.0.1', $array['context']['ip']);
    }

    public function test_that_json_serialize_matches_to_array(): void
    {
        $entry = AuditEntry::record('user:1', 'login');

        self::assertSame($entry->toArray(), $entry->jsonSerialize());
    }
}
