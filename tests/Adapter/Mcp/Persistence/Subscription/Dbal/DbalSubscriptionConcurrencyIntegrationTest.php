<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Subscription\Dbal;

use Fight\Test\Common\TestCase\Mcp\McpSubscriptionJourneyTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class DbalSubscriptionConcurrencyIntegrationTest extends McpSubscriptionJourneyTestCase
{
    #[DataProvider('databases')]
    public function test_that_heartbeat_after_clock_capture_preserves_open_counts_without_claiming_recent_observation(string $backend, bool $configured): void
    {
        $this->assert_that_heartbeat_after_clock_capture_preserves_open_counts_without_claiming_recent_observation($backend, $configured);
    }

    #[DataProvider('capacityCases')]
    public function test_that_real_capacity_lock_waits_preserve_freshness_and_retained_recovery(string $backend, string $mode, string $transport, int $bootstrapDelayMicroseconds, bool $configured): void
    {
        $this->assert_that_real_capacity_lock_waits_preserve_freshness_and_retained_recovery($backend, $mode, $transport, $bootstrapDelayMicroseconds, $configured);
    }

    #[DataProvider('databases')]
    public function test_that_same_second_noops_and_lost_ownership_preserve_dedicated_transaction_contracts(string $backend, bool $configured): void
    {
        $this->assert_that_same_second_noops_and_lost_ownership_preserve_dedicated_transaction_contracts($backend, $configured);
    }

    public static function capacityCases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach (['fast', 'expired', 'overlong', 'timeout', 'pre-renewal-expired'] as $mode) {
                foreach (['body', 'emitter'] as $transport) {
                    yield $backend.'-'.$mode.'-'.$transport.'-configured' => [$backend, $mode, $transport, 0, true];
                    if (in_array($mode, ['fast', 'expired'], true)) {
                        yield $backend.'-'.$mode.'-'.$transport.'-delayed-bootstrap-configured' => [$backend, $mode, $transport, 1100000, true];
                    }
                }
            }
        }
    }
}
