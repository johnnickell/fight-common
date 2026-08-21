<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

/**
 * Class ReleaseAuthorityValidator
 *
 * Validates immutable identities shared by inspection and planning policy.
 */
final readonly class ReleaseAuthorityValidator
{
    /**
     * Checks one object ID against the repository's current SHA-1 object format
     */
    public function isGitObjectId(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{40}$/D', $value) === 1;
    }

    /**
     * Checks one opaque support-policy identity without inventing an external registry
     */
    public function isSupportPolicyIdentity(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * Checks one stable, extensible certification evidence identifier
     */
    public function isEvidenceRequirementId(mixed $value): bool
    {
        return $this->isAuthorityId($value);
    }

    /**
     * Checks one stable, extensible lowercase authority identifier
     */
    public function isAuthorityId(mixed $value): bool
    {
        return is_string($value)
            && strlen($value) <= 128
            && preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $value) === 1;
    }
}
