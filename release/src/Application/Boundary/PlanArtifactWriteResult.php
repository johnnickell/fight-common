<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use InvalidArgumentException;

/**
 * Class PlanArtifactWriteResult
 *
 * Carries one deterministic artifact-write outcome across the Application port.
 */
final readonly class PlanArtifactWriteResult
{
    /**
     * Constructs PlanArtifactWriteResult
     */
    private function __construct(
        public ReleaseBoundaryOutcome $outcome,
        public ?string $postconditionEvidence
    ) {
    }

    /**
     * Reports a newly persisted artifact
     */
    public static function success(): self
    {
        return new self(ReleaseBoundaryOutcome::SUCCESS, null);
    }

    /**
     * Reports a provider-observed existing artifact that still requires verification
     */
    public static function alreadySatisfied(string $postconditionEvidence): self
    {
        if ($postconditionEvidence === '') {
            throw new InvalidArgumentException('An already-satisfied artifact write requires postcondition evidence.');
        }

        return new self(ReleaseBoundaryOutcome::ALREADY_SATISFIED, $postconditionEvidence);
    }

    /**
     * Reports an atomic publication whose final identity still requires independent verification
     */
    public static function publicationVerificationRequired(): self
    {
        return new self(ReleaseBoundaryOutcome::UNCERTAINTY, 'publication_verification_required');
    }

    /**
     * Reports a governed write stop without claiming a persisted postcondition
     */
    public static function stopped(ReleaseBoundaryOutcome $outcome): self
    {
        if (
            $outcome === ReleaseBoundaryOutcome::SUCCESS
            || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
        ) {
            throw new InvalidArgumentException('A stopped artifact write requires a non-success outcome.');
        }

        return new self($outcome, null);
    }

    /**
     * Reports whether the artifact write completed
     */
    public function persisted(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::SUCCESS;
    }

    /**
     * Reports whether an observed existing artifact must be verified by the Application
     */
    public function requiresPostconditionVerification(): bool
    {
        return (
            $this->outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
            || $this->outcome === ReleaseBoundaryOutcome::UNCERTAINTY
        ) && is_string($this->postconditionEvidence);
    }

    /**
     * Reports whether publication may have completed before the provider lost certainty
     */
    public function publicationMayHaveCompleted(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::UNCERTAINTY
            && $this->postconditionEvidence === 'publication_verification_required';
    }
}
