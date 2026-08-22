<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use InvalidArgumentException;

/**
 * Class ReleaseBoundaryOperationResult
 *
 * Separates a governed boundary outcome from any successful operation value.
 */
final readonly class ReleaseBoundaryOperationResult
{
    /**
     * Constructs ReleaseBoundaryOperationResult
     */
    private function __construct(
        public ReleaseBoundaryOutcome $outcome,
        public ?string $value,
        public ?string $postconditionEvidence
    ) {
    }

    /**
     * Creates a successful result with its required operation value
     */
    public static function success(string $value): self
    {
        return new self(ReleaseBoundaryOutcome::SUCCESS, $value, null);
    }

    /**
     * Reports a provider-observed postcondition that the Application must verify
     */
    public static function alreadySatisfied(string $postconditionEvidence): self
    {
        if ($postconditionEvidence === '') {
            throw new InvalidArgumentException('An already-satisfied boundary result requires postcondition evidence.');
        }

        return new self(ReleaseBoundaryOutcome::ALREADY_SATISFIED, null, $postconditionEvidence);
    }

    /**
     * Creates a stopped result without a successful operation value
     */
    public static function stopped(ReleaseBoundaryOutcome $outcome): self
    {
        if (
            $outcome === ReleaseBoundaryOutcome::SUCCESS
            || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
        ) {
            throw new InvalidArgumentException('A stopped boundary result requires a non-success outcome.');
        }

        return new self($outcome, null, null);
    }

    /**
     * Reports whether the operation returned successful data
     */
    public function hasValue(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::SUCCESS;
    }

    /**
     * Reports whether the provider supplied a postcondition claim for independent verification
     */
    public function requiresPostconditionVerification(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
            && is_string($this->postconditionEvidence);
    }
}
