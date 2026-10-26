<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Fight\Common\Application\Mcp\Subscription\Data\SubscriberPage;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberReadScope;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberSummary;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberView;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Subscription\SubscriberSelection;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SubscriberView::class)]
#[CoversClass(SubscriberReadScope::class)]
#[CoversClass(SubscriberSummary::class)]
#[CoversClass(SubscriberPage::class)]
#[CoversClass(SubscriberAccess::class)]
final class SubscriberDataTest extends UnitTestCase
{
    public function test_that_observations_preserve_requested_accepted_optional_data_and_only_claim_supported_states(): void
    {
        $view = self::view();
        self::assertSame(str_repeat('1', 32), $view->registrationId()); self::assertSame('agent', $view->subscriber());
        self::assertSame(['scope'], $view->noticeSelection()->scopes());
        self::assertSame('recently_observed', $view->state());
        self::assertSame('stale_uncertain', self::view(['queriedAt' => 105])->state());
        self::assertSame('stale_uncertain', self::view(['queriedAt' => 99])->state());
        self::assertSame('disconnected', self::view(['observedAt' => 103, 'closedAt' => 103, 'queriedAt' => 110])->state());
        $data = json_decode(json_encode($view->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['toolsListChanged' => true, 'promptsListChanged' => false, 'resourceSubscriptions' => ['file:///a', 'file:///denied']], $data['requested']);
        self::assertSame(['toolsListChanged' => true, 'resourceSubscriptions' => ['file:///a']], $data['accepted']);
        self::assertNull($data['closed_at']); self::assertArrayNotHasKey('owner', $data);
        foreach (['registration_id' => str_repeat('1', 32), 'subscriber' => 'agent', 'state' => 'recently_observed', 'topic' => 'topic', 'requested_filter' => 'promptsListChanged', 'accepted_filter' => 'toolsListChanged', 'requested_resource' => 'file:///denied', 'accepted_resource' => 'file:///a', 'opened_from' => 100, 'opened_until' => 101] as $field => $value) {
            self::assertTrue($view->matches(SubscriberSelection::create(['scope'], [$field => $value])), $field);
        }
        foreach ([['subscriber' => 'other'], ['requested_filter' => 'absent'], ['accepted_filter' => 'promptsListChanged'], ['accepted_resource' => 'file:///denied'], ['opened_from' => 101], ['opened_until' => 100]] as $filters) { self::assertFalse($view->matches(SubscriberSelection::create(['scope'], $filters))); }
        self::assertFalse($view->matches(SubscriberSelection::create([])));
        self::assertFalse(self::view(['noticeSelection' => NoticeSelection::create([])])->matches(SubscriberSelection::create(['scope'])));
        self::assertFalse(SubscriberAccess::create(['other'])->allows($view->registrationId(), $view->noticeSelection()));
        self::assertTrue(SubscriberAccess::create(['scope'])->allows($view->registrationId(), $view->noticeSelection()));
        self::assertTrue(SubscriberAccess::create(['scope'], [$view->registrationId()])->allows($view->registrationId(), $view->noticeSelection()));
        self::assertFalse(SubscriberAccess::create(['scope'], [str_repeat('2', 32)])->allows($view->registrationId(), $view->noticeSelection()));
        self::assertTrue(SubscriberAccess::create([])->isDenied()); self::assertTrue(SubscriberAccess::create(['scope'], [])->isDenied()); self::assertFalse(SubscriberAccess::create(['scope'])->isDenied());
        self::assertFalse(SubscriberAccess::create(['scope'])->allows($view->registrationId(), NoticeSelection::create(['scope', 'concealed'])));
    }

    public function test_that_summaries_count_streams_and_identities_separately_with_fixed_full_selection_states(): void
    {
        $scope = new SubscriberReadScope('deployment-db-a', 103);
        self::assertSame(103, $scope->queriedAt());
        $views = [self::view(), self::view(['registrationId' => str_repeat('2', 32), 'queriedAt' => 106]), self::view(['registrationId' => str_repeat('3', 32), 'subscriber' => 'second', 'observedAt' => 103, 'closedAt' => 103])];
        $summary = SubscriberSummary::fromSubscribers($views, $scope)->toArray();
        self::assertSame(3, $summary['subscriptions']); self::assertSame(2, $summary['subscriber_ids']);
        self::assertSame(['recently_observed' => 1, 'disconnected' => 1, 'stale_uncertain' => 1], $summary['states']);
        self::assertSame(100, $summary['oldest_observed_at']); self::assertSame(103, $summary['latest_observed_at']);
        self::assertSame($scope->toArray(), $summary['scope']);
        $empty = SubscriberSummary::fromSubscribers([], $scope)->toArray();
        self::assertSame(0, $empty['subscriptions']); self::assertSame(0, $empty['subscriber_ids']); self::assertNull($empty['latest_observed_at']);
        $page = new SubscriberPage([$views[1]], 3, 2, 1, $scope);
        self::assertSame([$views[1]], $page->subscribers()); self::assertSame(3, $page->toArray()['total']); self::assertSame(2, $page->toArray()['page']); self::assertSame(1, $page->toArray()['per_page']);
        self::assertSame([$views[1]->toArray()], $page->toArray()['subscribers']);
    }

    public function test_that_invalid_metadata_grants_scan_and_encoded_result_bounds_fail_instead_of_truncating(): void
    {
        foreach ([['registrationId' => 'bad'], ['subscriber' => 'bad secret'], ['requested' => StrictJson::fromData([])], ['accepted' => StrictJson::fromData([])], ['openedAt' => 0], ['observedAt' => 99], ['observedAt' => 2147483648], ['expiresAt' => 100], ['expiresAt' => 2147483648], ['closedAt' => -1], ['closedAt' => 101], ['queriedAt' => 0], ['queriedAt' => 2147483648], ['requested' => StrictJson::fromObject(['large' => str_repeat('x', 65537)])]] as $change) { $this->invalid(fn () => self::view($change)); }
        $this->invalid(fn () => new SubscriberReadScope('db', 0)); $this->invalid(fn () => new SubscriberReadScope('db', 2147483648));
        $this->invalid(fn () => SubscriberAccess::create(['scope'], ['bad']));
        $this->invalid(fn () => SubscriberAccess::create(['scope'], ['key' => str_repeat('1', 32)]));
        $this->invalid(fn () => SubscriberAccess::create(['scope'], array_fill(0, 4097, str_repeat('1', 32))));
        $scope = new SubscriberReadScope('db', 103);
        foreach ([[[], -1, 1, 1], [[self::view(), self::view()], 2, 1, 1], [['bad' => self::view()], 1, 1, 1], [[], 8193, 1, 1]] as $args) { $this->invalid(fn () => new SubscriberPage(...[...$args, $scope])); }
        $huge = self::view(['requested' => StrictJson::fromObject(['optionalFamily' => str_repeat('"', 32000)])]);
        $this->invalid(fn () => new SubscriberPage(array_fill(0, 16, $huge), 16, 1, 16, $scope));
        $this->invalid(fn () => SubscriberSummary::fromSubscribers((static function () { for ($i = 0; $i < 8193; ++$i) { yield self::view(); } })(), $scope));
    }

    public static function view(array $changes = []): SubscriberView
    {
        return new SubscriberView(...array_replace(['registrationId' => str_repeat('1', 32), 'subscriber' => 'agent', 'noticeSelection' => NoticeSelection::create(['scope'], ['topic' => 'topic']), 'requested' => StrictJson::fromObject(['toolsListChanged' => true, 'promptsListChanged' => false, 'resourceSubscriptions' => ['file:///a', 'file:///denied']]), 'accepted' => StrictJson::fromObject(['toolsListChanged' => true, 'resourceSubscriptions' => ['file:///a']]), 'openedAt' => 100, 'observedAt' => 100, 'expiresAt' => 105, 'closedAt' => 0, 'queriedAt' => 103], $changes));
    }

    private function invalid(callable $operation): void
    {
        try { $operation(); self::fail('Invalid result accepted'); } catch (DomainException) { self::assertTrue(true); }
    }
}
