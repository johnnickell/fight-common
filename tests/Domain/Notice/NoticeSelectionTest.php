<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Notice;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(NoticeSelection::class)]
final class NoticeSelectionTest extends UnitTestCase
{
    public function test_that_selection_is_canonical_and_filters_distinct_relationships_and_half_open_instants(): void
    {
        $notice = Notice::fromArray(NoticeFixture::data());
        $filters = [
            'id' => 'notice-1', 'producer' => 'producer', 'topic' => 'catalog/tools', 'type' => 'catalog-changed',
            'subject' => 'subject-agent', 'recipient' => 'recipient-agent', 'author' => 'author-agent',
            'command' => 'command-id', 'event' => 'event-id', 'action' => 'action-id',
            'occurred_from' => '2026-10-25T01:02:03.120000Z', 'occurred_until' => '2026-10-25T01:02:03.120001Z',
            'recorded_from' => '2026-10-25T01:02:03Z', 'recorded_until' => '2026-10-25T01:02:04Z'
        ];
        $selection = NoticeSelection::create(['scope-2', 'scope', 'scope'], $filters);
        self::assertSame(['scope', 'scope-2'], $selection->scopes());
        self::assertSame($selection->toArray(), NoticeSelection::fromArray($selection->toArray())->toArray());
        self::assertTrue($selection->matches($notice, '2026-10-25T01:02:03.999999Z'));
        self::assertSame('2026-10-25T01:02:03.000000Z', $selection->filters()['recorded_from']);
        self::assertFalse($selection->matches($notice, '2026-10-25T01:02:04.000000Z'));
        self::assertFalse($selection->matches($notice, '2026-10-25T01:02:02.999999Z'));
        self::assertFalse(NoticeSelection::create(['other'])->matches($notice, '2026-10-25T01:02:03Z'));
        foreach (['topic', 'subject', 'recipient', 'producer', 'author', 'command', 'event', 'action', 'id', 'type'] as $field) {
            self::assertFalse(NoticeSelection::create(['scope'], [$field => 'other'])->matches($notice, '2026-10-25T01:02:03Z'));
        }
        $unknown = Notice::fromArray(NoticeFixture::data(['occurred_at' => null, 'relationships' => ['subject' => null, 'recipients' => null, 'author' => null, 'command' => null, 'event' => null, 'action' => null]]));
        self::assertTrue(NoticeSelection::create(['scope'])->matches($unknown, '2026-10-25T01:02:03Z'));
        self::assertFalse(NoticeSelection::create(['scope'], ['occurred_from' => '0001-01-01T00:00:00Z'])->matches($unknown, '2026-10-25T01:02:03Z'));
        self::assertFalse(NoticeSelection::create(['scope'], ['recipient' => 'recipient-agent'])->matches($unknown, '2026-10-25T01:02:03Z'));
        self::assertFalse(NoticeSelection::create(['scope'], ['occurred_from' => '2026-10-25T01:02:03.12Z', 'occurred_until' => '2026-10-25T01:02:03.120Z'])->matches($notice, '2026-10-25T01:02:03Z'));
    }

    #[DataProvider('invalidSelections')]
    public function test_that_invalid_selection_rejects_without_accepting_authority(array $data): void
    {
        $this->expectException(DomainException::class);
        NoticeSelection::fromArray($data);
    }

    public static function invalidSelections(): iterable
    {
        yield [['scopes' => [], 'filters' => [], 'SUPER_ADMIN' => true]];
        yield [['scopes' => 'scope', 'filters' => []]];
        yield [['scopes' => [], 'filters' => null]];
        yield [['scopes' => ['key' => 'scope'], 'filters' => []]];
        yield [['scopes' => array_fill(0, 17, 'scope'), 'filters' => []]];
        foreach ([null, '', 'a b', str_repeat('a', 97), ['scope']] as $scope) { yield [['scopes' => [$scope], 'filters' => []]]; }
        yield [['scopes' => [], 'filters' => array_fill_keys(range(0, 14), 'value')]];
        foreach ([['SUPER_ADMIN' => 'yes'], ['topic' => []], ['recorded_from' => 42], ['recorded_from' => '2026-02-30T00:00:00Z'], ['occurred_until' => str_repeat('x', 1000)], ['occurred_from' => '2026-10-25T00:00:01Z', 'occurred_until' => '2026-10-25T00:00:00Z'], ['recorded_from' => '2026-10-25T00:00:01Z', 'recorded_until' => '2026-10-25T00:00:00Z']] as $filters) { yield [['scopes' => ['scope'], 'filters' => $filters]]; }
    }
}
