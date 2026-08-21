<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release\Boundary;

use Fight\Common\Application\Release\Boundary\PlanArtifactWriteResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers the typed immutable plan-artifact write result. */
#[CoversClass(PlanArtifactWriteResult::class)]
class PlanArtifactWriteResultTest extends UnitTestCase
{
    /**
     * Covers persistence classification from the declared outcome.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_only_success_reports_a_persisted_artifact(): void
    {
        self::assertTrue(PlanArtifactWriteResult::success()->persisted());
        self::assertFalse(PlanArtifactWriteResult::stopped(ReleaseBoundaryOutcome::REFUSAL)->persisted());
    }

    /**
     * Covers explicit existing-artifact evidence remaining unverified until the Application checks it.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_already_satisfied_requires_evidence_without_claiming_persistence(): void
    {
        $result = PlanArtifactWriteResult::alreadySatisfied('immutable_artifact_exists');

        self::assertSame(ReleaseBoundaryOutcome::ALREADY_SATISFIED, $result->outcome);
        self::assertSame('immutable_artifact_exists', $result->postconditionEvidence);
        self::assertFalse($result->persisted());
        self::assertTrue($result->requiresPostconditionVerification());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'An already-satisfied artifact write requires postcondition evidence.'
        );

        PlanArtifactWriteResult::alreadySatisfied('');
    }

    /**
     * Covers a provider losing certainty only after atomic publication may have completed.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_post_publication_uncertainty_requires_independent_verification(): void
    {
        $result = PlanArtifactWriteResult::publicationVerificationRequired();

        self::assertSame(ReleaseBoundaryOutcome::UNCERTAINTY, $result->outcome);
        self::assertSame('publication_verification_required', $result->postconditionEvidence);
        self::assertFalse($result->persisted());
        self::assertTrue($result->requiresPostconditionVerification());
        self::assertTrue($result->publicationMayHaveCompleted());
        self::assertFalse(PlanArtifactWriteResult::success()->publicationMayHaveCompleted());
    }

    /**
     * Covers verification-required outcomes being rejected as ordinary stops.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_an_already_satisfied_artifact_write_cannot_be_constructed_as_a_stop(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A stopped artifact write requires a non-success outcome.');

        PlanArtifactWriteResult::stopped(ReleaseBoundaryOutcome::ALREADY_SATISFIED);
    }
}
