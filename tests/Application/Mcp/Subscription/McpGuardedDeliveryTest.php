<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Fiber;
use Fight\Common\Application\Mcp\Subscription\McpDisclosureAction;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionDelivery;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(McpDisclosureAction::class)]
#[CoversClass(McpSubscriptionDelivery::class)]
final class McpGuardedDeliveryTest extends UnitTestCase
{
    public function test_that_action_cannot_be_repeated_saved_or_moved_to_another_fiber(): void
    {
        $calls = 0;
        $action = new McpDisclosureAction(static function () use (&$calls): void { ++$calls; });
        $foreign = new Fiber(static fn () => $action->invoke());
        try { $foreign->start(); self::fail('Foreign Fiber admitted'); } catch (RuntimeException) { self::assertSame(0, $calls); }
        $action->invoke();
        try { $action->invoke(); self::fail('Repeated action'); } catch (RuntimeException) { self::assertSame(1, $calls); }
        $action->finish();
        try { $action->invoke(); self::fail('Saved action'); } catch (RuntimeException) { self::assertSame(1, $calls); }
        $omitted = new McpDisclosureAction(static fn () => self::fail('Late action'));
        try { $omitted->finish(); self::fail('Omission accepted'); } catch (RuntimeException) { self::assertTrue(true); }
        $this->expectException(RuntimeException::class);
        $omitted->invoke();
    }

    public function test_that_action_fault_is_not_retried_or_fabricated_as_rollback(): void
    {
        $effects = [];
        $action = new McpDisclosureAction(static function () use (&$effects): void { $effects[] = 'admitted'; throw new RuntimeException('effect fault'); });
        try { $action->invoke(); self::fail('Missing fault'); } catch (RuntimeException $failure) { self::assertSame('effect fault', $failure->getMessage()); }
        $action->finish();
        self::assertSame(['admitted'], $effects);
    }

    public function test_that_fragments_are_freshly_produced_and_cannot_mix_changed_frames(): void
    {
        $calls = 0; $wire = '';
        $delivery = new McpSubscriptionDelivery(static function ($write) use (&$calls): void { ++$calls; $write('abcdef'); });
        $sink = static function (string $chunk) use (&$wire): void { $wire .= $chunk; };
        self::assertFalse($delivery->send($sink, 2));
        self::assertFalse($delivery->send($sink, 2));
        self::assertTrue($delivery->send($sink, 2));
        self::assertSame('abcdef', $wire); self::assertSame(3, $calls);
        try { $delivery->send($sink); self::fail('Completed delivery reused'); } catch (RuntimeException) { self::assertSame('abcdef', $wire); }
        $frame = 'original';
        $changing = new McpSubscriptionDelivery(static function ($write) use (&$frame): void { $write($frame); });
        self::assertFalse($changing->send($sink, 1)); $frame = 'different';
        $this->expectException(RuntimeException::class);
        $changing->send($sink, 1);
    }

    public function test_that_interruption_after_a_partial_handoff_cannot_report_a_complete_frame(): void
    {
        $active = true; $wire = '';
        $delivery = new McpSubscriptionDelivery(static function ($write) use (&$active): void {
            if ($active) { $write('bounded frame'); }
        });
        self::assertFalse($delivery->send(static function ($bytes) use (&$wire): void { $wire .= $bytes; }, 1));
        self::assertSame('b', $wire);
        $active = false;
        $this->expectException(RuntimeException::class);
        $delivery->send(static fn () => self::fail('Interrupted frame emitted'), 1);
    }

    public function test_that_duplicate_empty_invalid_length_and_transport_faults_do_not_retry_output(): void
    {
        $empty = new McpSubscriptionDelivery(static function (): void {});
        self::assertTrue($empty->send(static fn () => self::fail('Empty work emitted')));
        foreach (['duplicate', 'length', 'transport'] as $fault) {
            $calls = 0;
            $delivery = new McpSubscriptionDelivery(static function ($write) use ($fault): void { $write('bytes'); if ($fault === 'duplicate') { $write('bytes'); } });
            try {
                $delivery->send(static function () use (&$calls, $fault): void { ++$calls; if ($fault === 'transport') { throw new RuntimeException('transport'); } }, $fault === 'length' ? 0 : 10);
                self::fail('Invalid output accepted');
            } catch (RuntimeException) { self::assertSame($fault === 'length' ? 0 : 1, $calls); }
        }
    }
}
