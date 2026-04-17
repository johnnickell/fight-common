<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class IsType
 */
final class IsType extends CompositeSpecification
{
    private bool $nullable;

    /**
     * Constructs IsType
     */
    public function __construct(private string $type)
    {
        $this->nullable = false;
        if (str_starts_with($this->type, '?')) {
            $this->nullable = true;
            $this->type = substr($this->type, 1);
        }
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        if ($this->nullable && $candidate === null) {
            return true;
        }

        return Validate::isType($candidate, $this->type);
    }
}
