<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release\Boundary;

use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOperationResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers the mutually exclusive release boundary operation result states. */
#[CoversClass(ReleaseBoundaryOperationResult::class)]
class ReleaseBoundaryOperationResultTest extends UnitTestCase
{
    /**
     * Covers a successful result requiring and retaining its operation value.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_success_requires_and_retains_its_operation_value(): void
    {
        $result = ReleaseBoundaryOperationResult::success('operation-value');

        self::assertSame(ReleaseBoundaryOutcome::SUCCESS, $result->outcome);
        self::assertSame('operation-value', $result->value);
        self::assertTrue($result->hasValue());
    }

    /**
     * Covers a stopped result never retaining a successful operation value.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_stopped_results_cannot_be_constructed_with_the_success_outcome(): void
    {
        foreach ([ReleaseBoundaryOutcome::SUCCESS, ReleaseBoundaryOutcome::ALREADY_SATISFIED] as $outcome) {
            try {
                ReleaseBoundaryOperationResult::stopped($outcome);
                self::fail('A successful or verification-required outcome cannot be an ordinary stop.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(
                    'A stopped boundary result requires a non-success outcome.',
                    $exception->getMessage()
                );
            }
        }
    }

    /**
     * Covers explicit provider evidence without exposing it as a verified operation value.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_already_satisfied_requires_evidence_and_never_claims_a_success_value(): void
    {
        $result = ReleaseBoundaryOperationResult::alreadySatisfied('remote_release_exists');

        self::assertSame(ReleaseBoundaryOutcome::ALREADY_SATISFIED, $result->outcome);
        self::assertSame('remote_release_exists', $result->postconditionEvidence);
        self::assertNull($result->value);
        self::assertFalse($result->hasValue());
        self::assertTrue($result->requiresPostconditionVerification());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'An already-satisfied boundary result requires postcondition evidence.'
        );

        ReleaseBoundaryOperationResult::alreadySatisfied('');
    }
}
