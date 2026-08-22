<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter\Fake;

use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\Boundary\AuthorizationPort;
use Fight\Release\Application\Boundary\ClockPort;
use Fight\Release\Application\Boundary\FilesystemPort;
use Fight\Release\Application\Boundary\GitHubPort;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\HashingPort;
use Fight\Release\Application\Boundary\PackagistPort;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;
use Fight\Release\Application\Boundary\SigningPort;
use Fight\Test\Release\TestCase\ReleaseBoundaryPortConformanceTestCase;
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
