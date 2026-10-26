<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Subscription;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Subscription\Query\SummarizeSubscribers;
use Fight\Common\Domain\Subscription\SubscriberSelection;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SubscriberSelection::class)]
#[CoversClass(ListSubscribers::class)]
#[CoversClass(SummarizeSubscribers::class)]
final class SubscriberQueriesTest extends UnitTestCase
{
    public function test_that_queries_round_trip_finite_selections_and_strict_live_pages_without_authority(): void
    {
        $filters = ['subscriber' => 'agent', 'topic' => 'topic', 'registration_id' => str_repeat('1', 32), 'state' => 'stale_uncertain', 'requested_filter' => 'promptsListChanged', 'accepted_filter' => 'toolsListChanged', 'requested_resource' => 'file:///a', 'accepted_resource' => 'file:///b', 'opened_from' => 10, 'opened_until' => 20];
        $selection = SubscriberSelection::create(['z', 'a', 'z'], $filters);
        self::assertSame(['a', 'z'], $selection->scopes());
        ksort($filters); self::assertSame($filters, $selection->filters());
        self::assertEquals($selection, SubscriberSelection::fromArray($selection->toArray()));
        $summary = new SummarizeSubscribers($selection);
        self::assertSame($selection, $summary->selection());
        self::assertEquals($summary, SummarizeSubscribers::fromArray($summary->toArray()));
        $page = new ListSubscribers($selection);
        self::assertSame($selection, $page->selection());
        self::assertSame(1, $page->pagination()->page()); self::assertSame(16, $page->pagination()->perPage());
        self::assertEquals($page, ListSubscribers::fromArray($page->toArray()));
        $page = new ListSubscribers($selection, 2, 3, ['opened_at' => 'asc']);
        self::assertSame(3, $page->pagination()->offset()); self::assertSame(['opened_at' => 'ASC'], $page->pagination()->orderings());
        self::assertEquals($page, ListSubscribers::fromArray($page->toArray()));
    }

    public function test_that_untrusted_filters_shapes_times_and_page_overflow_fail_before_storage(): void
    {
        foreach ([['role' => 'SUPER_ADMIN'], ['state' => 'active'], ['registration_id' => 'bad'], ['subscriber' => 'bad secret'], ['requested_resource' => ''], ['accepted_resource' => str_repeat('a', 2049)], ['requested_resource' => "bad\xff"], ['opened_from' => 0], ['opened_until' => 2147483648], ['opened_from' => '1'], ['opened_from' => 20, 'opened_until' => 10]] as $filters) {
            $this->invalid(fn () => SubscriberSelection::create(['scope'], $filters));
        }
        $this->invalid(fn () => SubscriberSelection::create(array_fill(0, 17, 'scope')));
        $this->invalid(fn () => SubscriberSelection::fromArray(['scopes' => 'bad', 'filters' => []]));
        $this->invalid(fn () => SubscriberSelection::fromArray(['scopes' => [], 'filters' => 'bad']));
        $this->invalid(fn () => SubscriberSelection::fromArray(['scopes' => []]));
        $this->invalid(fn () => SummarizeSubscribers::fromArray(['selection' => false]));
        $selection = SubscriberSelection::create(['scope']);
        foreach ([[0, 1, []], [1, 0, []], [1, 17, []], [PHP_INT_MAX, 16, []], [1000002, 1, []], [1, 1, ['owner' => 'ASC']], [1, 1, ['opened_at' => 'DROP TABLE']]] as $args) {
            $this->invalid(fn () => new ListSubscribers($selection, ...$args));
        }
        foreach (['selection' => false, 'page' => '1', 'per_page' => '16', 'orderings' => false] as $field => $value) {
            $this->invalid(fn () => ListSubscribers::fromArray(array_replace((new ListSubscribers($selection))->toArray(), [$field => $value])));
        }
    }

    private function invalid(callable $operation): void
    {
        try { $operation(); self::fail('Invalid selection accepted'); } catch (DomainException) { self::assertTrue(true); }
    }
}
