<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

use Fight\Common\Application\Release\Boundary\BaselineTagResolutionResult;
use Fight\Common\Application\Release\Boundary\BaselineTagResolutionStatus;
use Fight\Common\Application\Release\Boundary\GitPort;

/**
 * Class BaselineTagVerifier
 */
final readonly class BaselineTagVerifier
{
    /**
     * Reports whether a tag is canonical for its stable SemVer baseline
     */
    public function isCanonical(string $tagName, string $version): bool
    {
        if (!StableSemVer::isValid($version)) {
            return false;
        }

        return $version === '1.1.0' ? $tagName === '1.1.0' : $tagName === 'v'.$version;
    }

    /**
     * Verifies one descriptive tag and proves all immutable identities still match
     */
    public function verify(
        GitPort $git,
        string $tagName,
        string $candidateOid,
        string $expectedTagObjectOid,
        string $expectedPeeledCommitOid
    ): BaselineTagResolutionResult {
        $resolution = $git->resolveBaselineTag($tagName, $candidateOid);

        if (!$resolution->isResolved()) {
            return $resolution;
        }

        if (
            $resolution->tagName !== $tagName
            || $resolution->tagObjectOid !== $expectedTagObjectOid
            || $resolution->peeledCommitOid !== $expectedPeeledCommitOid
        ) {
            return BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::MOVING);
        }

        return $resolution;
    }
}
