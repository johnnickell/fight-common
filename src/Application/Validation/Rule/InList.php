<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class InList
 */
final class InList extends CompositeSpecification
{
    /**
     * Constructs InList
     */
    public function __construct(private readonly array $list)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::isOneOf($candidate, $this->list);
    }
}
