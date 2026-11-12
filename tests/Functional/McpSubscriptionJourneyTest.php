<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Test\Common\TestCase\Mcp\McpSubscriptionJourneyTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class McpSubscriptionJourneyTest extends McpSubscriptionJourneyTestCase
{
    #[DataProvider('isolationDatabases')]
    public function test_that_real_shared_database_live_streams_reused_ids_revocation_failure_and_polling_are_isolated(string $backend, bool $configured): void
    {
        $this->assert_that_real_shared_database_live_streams_reused_ids_revocation_failure_and_polling_are_isolated($backend, $configured);
    }

    #[DataProvider('representativeDatabase')]
    public function test_that_independent_routed_operator_queries_summarize_then_page_actual_streams_with_current_inspection_grants(string $backend, bool $configured): void
    {
        $this->assert_that_independent_routed_operator_queries_summarize_then_page_actual_streams_with_current_inspection_grants($backend, $configured);
    }

    #[DataProvider('representativeDatabase')]
    public function test_that_actual_prompt_serving_and_live_notifications_have_independent_current_authority_and_retained_recovery(string $backend, bool $configured): void
    {
        $this->assert_that_actual_prompt_serving_and_live_notifications_have_independent_current_authority_and_retained_recovery($backend, $configured);
    }

    #[DataProvider('nativeCompositions')]
    public function test_that_supported_native_compositions_emit_before_close_and_idle_disconnect_is_observed(string $composition, bool $configured): void
    {
        $this->assert_that_supported_native_compositions_emit_before_close_and_idle_disconnect_is_observed($composition, $configured);
    }

    #[DataProvider('representativeDatabase')]
    public function test_that_abrupt_death_becomes_stale_and_cross_process_owner_updates_cannot_revive_closed_or_expired_rows(string $backend, bool $configured): void
    {
        $this->assert_that_abrupt_death_becomes_stale_and_cross_process_owner_updates_cannot_revive_closed_or_expired_rows($backend, $configured);
    }

    #[DataProvider('representativeDatabase')]
    public function test_that_concurrent_removal_and_over_batch_backlog_end_honestly_and_new_listen_is_not_replay(string $backend, bool $configured): void
    {
        $this->assert_that_concurrent_removal_and_over_batch_backlog_end_honestly_and_new_listen_is_not_replay($backend, $configured);
    }

    public static function representativeDatabase(): iterable
    {
        yield 'sqlite-configured' => ['sqlite', true];
    }

    public static function isolationDatabases(): iterable
    {
        yield from self::representativeDatabase();
        yield 'mysql-configured' => ['mysql', true];
    }

    public static function nativeCompositions(): iterable
    {
        foreach (['psr', 'symfony', 'laravel', 'yii', 'slim', 'codeigniter'] as $composition) {
            yield $composition.'-configured' => [$composition, true];
        }
    }
}
