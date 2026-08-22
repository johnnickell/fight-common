<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\BaselineTagVerifier;
use Fight\Release\Application\Boundary\BaselineTagResolutionResult;
use Fight\Release\Application\Boundary\BaselineTagResolutionStatus;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BaselineTagVerifier::class)]
#[CoversClass(BaselineTagResolutionResult::class)]
#[CoversClass(BaselineTagResolutionStatus::class)]
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
/**
 * Class BaselineTagVerifierTest
 */
class BaselineTagVerifierTest extends UnitTestCase
{
    /**
     * Covers canonical future tags and the sole historical exception.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_only_canonical_future_tags_and_the_bare_historical_exception_are_accepted(): void
    {
        $verifier = new BaselineTagVerifier();

        self::assertTrue($verifier->isCanonical('v1.2.3', '1.2.3'));
        self::assertTrue($verifier->isCanonical('1.1.0', '1.1.0'));
        self::assertFalse($verifier->isCanonical('1.2.3', '1.2.3'));
        self::assertFalse($verifier->isCanonical('v1.1.0', '1.1.0'));
        self::assertFalse($verifier->isCanonical('v01.2.3', '01.2.3'));
    }

    /**
     * Covers moving and closed unusable-ref resolution states.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_resolution_rejects_moving_identity_and_preserves_closed_git_states(): void
    {
        $verifier = new BaselineTagVerifier();
        $resolved = $verifier->verify(
            new DeterministicReleaseBoundaryFake(),
            'v1.2.3',
            str_repeat('d', 40),
            'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45'
        );
        self::assertTrue($resolved->isResolved());

        $fake = new DeterministicReleaseBoundaryFake();
        $fake->configureBaselineTagResolution('resolved', 'v1.2.3', str_repeat('c', 40), str_repeat('b', 40));
        $moving = $verifier->verify(
            $fake,
            'v1.2.3',
            str_repeat('d', 40),
            str_repeat('a', 40),
            str_repeat('b', 40)
        );

        self::assertSame(BaselineTagResolutionStatus::MOVING, $moving->status);
        self::assertFalse($moving->isResolved());

        foreach (['missing', 'ambiguous', 'duplicate_normalized', 'non_ancestor'] as $status) {
            $fake = new DeterministicReleaseBoundaryFake();
            self::assertTrue($fake->configureBaselineTagResolution($status));
            $resolution = $verifier->verify(
                $fake,
                'v1.2.3',
                str_repeat('d', 40),
                str_repeat('a', 40),
                str_repeat('b', 40)
            );

            self::assertSame($status, $resolution->status?->value);
            self::assertNull($resolution->tagName);
        }

        self::assertFalse($fake->configureBaselineTagResolution('unknown'));
    }

    /**
     * Covers construction invariants for resolved, rejected and stopped results.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_resolution_result_factories_reject_contradictory_states(): void
    {
        $resolved = BaselineTagResolutionResult::resolved('v1.2.3', str_repeat('a', 40), str_repeat('b', 40));
        self::assertTrue($resolved->isResolved());
        self::assertSame('v1.2.3', $resolved->tagName);

        $stopped = BaselineTagResolutionResult::stopped(ReleaseBoundaryOutcome::FAILURE);
        self::assertFalse($stopped->isResolved());

        try {
            BaselineTagResolutionResult::stopped(ReleaseBoundaryOutcome::SUCCESS);
            self::fail('A successful stopped result was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'A stopped baseline resolution requires a non-success outcome.',
                $exception->getMessage()
            );
        }

        $this->expectException(InvalidArgumentException::class);
        BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::RESOLVED);
    }
}
