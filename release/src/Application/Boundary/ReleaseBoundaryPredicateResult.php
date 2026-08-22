<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use InvalidArgumentException;

/**
 * Class ReleaseBoundaryPredicateResult
 *
 * Keeps a successfully evaluated false predicate distinct from a stopped boundary operation.
 */
final readonly class ReleaseBoundaryPredicateResult
{
    /**
     * Constructs ReleaseBoundaryPredicateResult
     */
    private function __construct(public ReleaseBoundaryOutcome $outcome, public ?bool $value)
    {
    }

    /**
     * Returns a successfully evaluated predicate
     */
    public static function success(bool $value): self
    {
        return new self(ReleaseBoundaryOutcome::SUCCESS, $value);
    }

    /**
     * Returns a governed boundary stop without a predicate value
     */
    public static function stopped(ReleaseBoundaryOutcome $outcome): self
    {
        if (
            $outcome === ReleaseBoundaryOutcome::SUCCESS
            || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
        ) {
            throw new InvalidArgumentException('A stopped predicate cannot have a successful outcome.');
        }

        return new self($outcome, null);
    }

    /**
     * Reports whether the boundary evaluated the predicate
     */
    public function hasValue(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::SUCCESS && is_bool($this->value);
    }
}
