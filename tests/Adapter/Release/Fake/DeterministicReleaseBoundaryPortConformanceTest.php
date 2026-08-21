<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Release\Fake;

use Fight\Common\Adapter\Release\Fake\DeterministicReleaseBoundaryFake;
use Fight\Common\Application\Release\Boundary\AuthorizationPort;
use Fight\Common\Application\Release\Boundary\ClockPort;
use Fight\Common\Application\Release\Boundary\FilesystemPort;
use Fight\Common\Application\Release\Boundary\GitHubPort;
use Fight\Common\Application\Release\Boundary\GitPort;
use Fight\Common\Application\Release\Boundary\HashingPort;
use Fight\Common\Application\Release\Boundary\PackagistPort;
use Fight\Common\Application\Release\Boundary\PlanArtifactStore;
use Fight\Common\Application\Release\Boundary\ReleaseEffectLedger;
use Fight\Common\Application\Release\Boundary\SigningPort;
use Fight\Test\Common\TestCase\Release\ReleaseBoundaryPortConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Applies the reusable release-port contract to the credential-free deterministic fake.
 */
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
final class DeterministicReleaseBoundaryPortConformanceTest extends ReleaseBoundaryPortConformanceTestCase
{
    /**
     * Creates one isolated deterministic release boundary.
     */
    protected function createReleaseBoundary(array $outcomes = []): FilesystemPort
        &GitPort
        &HashingPort
        &ClockPort
        &SigningPort
        &AuthorizationPort
        &GitHubPort
        &PackagistPort
        &PlanArtifactStore
        &ReleaseEffectLedger
    {
        return new DeterministicReleaseBoundaryFake($outcomes);
    }
}
