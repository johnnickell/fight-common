<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\Tool\NullMcpProgressReporter;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(NullMcpProgressReporter::class)]
final class NullMcpProgressReporterTest extends UnitTestCase
{
    public function test_that_reporting_valid_status_emits_nothing_and_does_not_cancel(): void
    {
        $this->expectOutputString('');
        $reporter = new NullMcpProgressReporter();
        $reporter->report(0, 0);
        $reporter->report(1, 2, 'Reading');
        $reporter->report(1);
        $reporter->report(2, 2, 'Complete');
        self::assertFalse($reporter->isCancelled());
    }

    #[DataProvider('invalidProgress')]
    public function test_that_invalid_progress_is_rejected_without_an_emitter(float $progress, ?float $total): void
    {
        $reporter = new NullMcpProgressReporter();
        $reporter->report(1);
        $this->expectException(DomainException::class);
        $reporter->report($progress, $total);
    }

    public static function invalidProgress(): iterable
    {
        yield [-1, null];
        yield [0, null];
        yield [INF, null];
        yield [NAN, null];
        yield [2, 1];
        yield [2, INF];
        yield [2, NAN];
    }
}
