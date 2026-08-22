<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application\Boundary;

use Fight\Release\Application\Boundary\PlanArtifactReadResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class PlanArtifactReadResultTest
 *
 * Covers the closed immutable-artifact read result.
 */
#[CoversClass(PlanArtifactReadResult::class)]
final class PlanArtifactReadResultTest extends UnitTestCase
{
    /** Covers the three disjoint read states. */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_content_missing_and_governed_stops_remain_disjoint(): void
    {
        $content = PlanArtifactReadResult::content('');
        $missing = PlanArtifactReadResult::missing();
        $stopped = PlanArtifactReadResult::stopped(ReleaseBoundaryOutcome::UNCERTAINTY);

        self::assertTrue($content->hasContent());
        self::assertSame('', $content->contents);
        self::assertFalse($content->missing);
        self::assertFalse($missing->hasContent());
        self::assertTrue($missing->missing);
        self::assertNull($missing->contents);
        self::assertFalse($stopped->hasContent());
        self::assertFalse($stopped->missing);
        self::assertSame(ReleaseBoundaryOutcome::UNCERTAINTY, $stopped->outcome);

        foreach ([ReleaseBoundaryOutcome::SUCCESS, ReleaseBoundaryOutcome::ALREADY_SATISFIED] as $outcome) {
            try {
                PlanArtifactReadResult::stopped($outcome);
                self::fail('A stopped artifact read cannot carry a non-stop outcome.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(
                    'A stopped artifact read requires a non-success outcome.',
                    $exception->getMessage()
                );
            }
        }
    }
}
