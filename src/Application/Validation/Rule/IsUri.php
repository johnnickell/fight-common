<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class IsUri
 */
final class IsUri extends CompositeSpecification
{
    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::isUri($candidate);
    }
}
