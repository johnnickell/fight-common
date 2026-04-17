<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use DateTimeZone;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class IsTimezone
 */
final class IsTimezone extends CompositeSpecification
{
    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        if ($candidate instanceof DateTimeZone) {
            $candidate = $candidate->getName();
        }

        return Validate::isTimezone($candidate);
    }
}
