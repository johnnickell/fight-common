<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\BaselineTagResolutionResult;
use Fight\Release\Application\Boundary\BaselineTagResolutionStatus;
use Fight\Release\Application\Boundary\CanonicalRunsDirectory;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\HashingPort;
use Fight\Release\Application\Boundary\PlanArtifactReadResult;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\ReleaseBoundaryOperationResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;
use Fight\Release\Application\Boundary\Sha256Digest;
use Throwable;

/**
 * Class ReleasePlanService
 *
 * Coordinates validation and immutable persistence of a release plan.
 */
final readonly class ReleasePlanService
{
    private ReleasePlanCapabilityFirewall $capabilities;

    /**
     * Constructs ReleasePlanService
     */
    public function __construct(
        private PlanArtifactStore $artifacts,
        private HashingPort $hashing,
        private ReleaseEffectLedger $effects,
        private GitPort $git,
        private CanonicalJson $json,
        private ReleasePlanFactory $plans,
        private ReleaseResultFactory $results,
        private BaselineTagVerifier $baselineTags = new BaselineTagVerifier(),
        private Utf8Validator $utf8 = new Utf8Validator(),
        ?ReleasePlanCapabilityFirewall $capabilities = null
    ) {
        $this->capabilities = $capabilities ?? new ReleasePlanCapabilityFirewall($results);
    }

    /**
     * Persists one approved immutable plan below the configured runs root
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     */
    public function plan(array $candidate, string $output, string $runsDirectory): MachineResult
    {
        if (!$this->utf8->isValid([$candidate, $output, $runsDirectory])) {
            return $this->results->failure(
                'plan',
                'release.plan.inputs_encoding_invalid',
                'Planning inputs must contain only valid UTF-8 strings.',
                'provide_valid_utf8_plan_inputs',
                []
            );
        }

        $capabilityStop = $this->capabilities->inspect($candidate);

        if ($capabilityStop instanceof MachineResult) {
            return $capabilityStop;
        }

        $validationFailure = $this->plans->validationFailure($candidate);

        if ($validationFailure instanceof ReleasePlanValidationFailure) {
            return $this->results->planValidationFailure($validationFailure, []);
        }

        $versionAuthorizationFailure = $this->plans->versionAuthorizationFailure($candidate);

        if ($versionAuthorizationFailure instanceof ReleasePlanValidationFailure) {
            return $this->results->planValidationFailure($versionAuthorizationFailure, []);
        }

        $boundary = $candidate['boundary'] ?? null;

        if (
            is_array($boundary)
            && !$this->effects->configureOutcome('filesystem.write', $boundary['outcome'])
        ) {
            return $this->results->failure(
                'plan',
                'release.boundary.fixture_invalid',
                'The planning fixture could not configure its controlled filesystem write outcome.',
                'correct_boundary_fixture',
                []
            );
        }

        $runsOutput = $this->artifacts->resolveRunsDirectory($output, $runsDirectory);

        if ($runsOutput->outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            return $this->results->planBoundaryValueOutcome(
                $runsOutput->outcome,
                $this->effects->effects()
            );
        }

        if (!$runsOutput->hasDirectory()) {
            return $this->results->failure(
                'plan',
                'release.plan.output_forbidden',
                'Planning artifacts must be written under the repository .runs directory.',
                'select_runs_output',
                $this->effects->effects()
            );
        }

        /** @var CanonicalRunsDirectory $canonicalOutput */
        $canonicalOutput = $runsOutput->directory;

        if (!$canonicalOutput->matches($output, $runsDirectory)) {
            return $this->results->failure(
                'plan',
                'release.plan.output_forbidden',
                'Planning artifacts must be written under the repository .runs directory.',
                'select_runs_output',
                $this->effects->effects()
            );
        }

        /** @var array<string, string> $baseline */
        $baseline = $candidate['baseline'];
        /** @var string $sourceCommitOid */
        $sourceCommitOid = $candidate['source_commit_oid'];
        $resolution = $this->baselineTags->verify(
            $this->git,
            $baseline['tag_name'],
            $sourceCommitOid,
            $baseline['tag_object_oid'],
            $baseline['peeled_commit_oid']
        );

        if (!$resolution->isResolved()) {
            return $this->planBaselineResolutionStop($resolution);
        }

        $plan = $this->plans->create($candidate);
        /** @var array<string, mixed> $plan */

        $hash = $this->hashing->sha256($this->json->encode($plan));

        if ($hash->outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            return $this->results->planBoundaryValueOutcome(
                $hash->outcome,
                $this->effects->effects()
            );
        }

        $digest = Sha256Digest::tryFrom($hash->value ?? '');

        if (!$digest instanceof Sha256Digest) {
            return $this->results->planBoundaryValueOutcome(
                ReleaseBoundaryOutcome::FAILURE,
                $this->effects->effects()
            );
        }

        $planId = $digest->value;
        $artifact = [...$plan, 'plan_id' => $planId];
        $artifactFilename = $planId.'.json';
        $artifactPath = $canonicalOutput->artifactPath($artifactFilename);

        $artifactRead = $this->artifacts->readArtifact($canonicalOutput, $artifactFilename);

        if ($artifactRead->outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            return $this->results->planBoundaryOutcome(
                $artifactRead->outcome,
                $planId,
                $artifactPath,
                $this->effects->effects()
            );
        }

        $writeRequired = $artifactRead->missing;
        $created = false;

        if (!$writeRequired) {
            $verification = $this->verifyExpectedArtifact($artifactRead, $artifact, $planId);

            if (!$verification->hasValue()) {
                return $this->results->planBoundaryOutcome(
                    $verification->outcome,
                    $planId,
                    $artifactPath,
                    $this->effects->effects()
                );
            }

            if ($verification->value !== 'verified') {
                return $this->results->planPersistenceFailure(
                    'release.plan.artifact_conflict',
                    'The existing plan artifact does not canonically verify for its plan identity.',
                    'resolve_plan_artifact_conflict',
                    $planId,
                    $artifactPath,
                    $this->effects->effects()
                );
            }
        } else {
            $write = $this->artifacts->writeArtifact(
                $canonicalOutput,
                $artifactFilename,
                $this->json->encode($artifact).PHP_EOL
            );

            $alreadySatisfied = $write->requiresPostconditionVerification()
                && $write->postconditionEvidence === 'immutable_artifact_exists';
            $publicationUncertain = $write->publicationMayHaveCompleted();

            if (!$write->persisted() && !$alreadySatisfied && !$publicationUncertain) {
                $outcome = $write->outcome;

                if ($outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED) {
                    $outcome = ReleaseBoundaryOutcome::FAILURE;
                }

                return $this->results->planBoundaryOutcome(
                    $outcome,
                    $planId,
                    $artifactPath,
                    $this->effects->effects()
                );
            }

            $created = $write->persisted();

            $persistedRead = $this->artifacts->readArtifact($canonicalOutput, $artifactFilename);
            $verification = $this->verifyExpectedArtifact($persistedRead, $artifact, $planId);

            if (!$verification->hasValue()) {
                if ($publicationUncertain) {
                    return $this->results->planBoundaryOutcome(
                        ReleaseBoundaryOutcome::UNCERTAINTY,
                        $planId,
                        $artifactPath,
                        $this->effects->effects()
                    );
                }

                return $this->results->planBoundaryOutcome(
                    $verification->outcome,
                    $planId,
                    $artifactPath,
                    $this->effects->effects()
                );
            }

            if ($verification->value !== 'verified') {
                if ($alreadySatisfied) {
                    return $this->results->planPersistenceFailure(
                        'release.plan.artifact_conflict',
                        'The existing plan artifact does not canonically verify for its plan identity.',
                        'resolve_plan_artifact_conflict',
                        $planId,
                        $artifactPath,
                        $this->effects->effects()
                    );
                }

                if ($publicationUncertain && $persistedRead->missing) {
                    return $this->results->planBoundaryOutcome(
                        ReleaseBoundaryOutcome::UNCERTAINTY,
                        $planId,
                        $artifactPath,
                        $this->effects->effects()
                    );
                }

                if ($publicationUncertain) {
                    return $this->results->planPersistenceFailure(
                        'release.plan.artifact_conflict',
                        'The published plan artifact does not canonically verify for its plan identity.',
                        'resolve_plan_artifact_conflict',
                        $planId,
                        $artifactPath,
                        $this->effects->effects()
                    );
                }

                return $this->results->planPersistenceFailure(
                    'release.plan.artifact_not_persisted',
                    'The immutable plan artifact could not be verified after persistence.',
                    'repair_plan_artifact_persistence',
                    $planId,
                    $artifactPath,
                    $this->effects->effects()
                );
            }

            if ($publicationUncertain) {
                $created = true;
            }
        }

        $findingId = 'release.plan.already_satisfied';
        $message = 'The immutable release plan already existed and was canonically verified.';
        $verifiedPostcondition = 'immutable_release_plan_already_persisted';

        if ($created) {
            $findingId = 'release.plan.created';
            $message = 'The approved exact version and immutable release inputs were bound into a plan.';
            $verifiedPostcondition = 'immutable_release_plan_persisted';
        }

        /** @var list<string> $expectedEffectClasses */
        $expectedEffectClasses = $plan['expected_effect_classes'];
        $proposedEffects = array_map(
            static fn (string $effectClass): array => ['effect_class' => $effectClass],
            $expectedEffectClasses
        );

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'plan',
            'capability'              => 'release_planning',
            'status'                  => 'succeeded',
            'exit_class'              => 'success',
            'plan_id'                 => $planId,
            'artifact'                => ['plan_id' => $planId, 'path' => $artifactPath],
            'findings'                => [[
                'id'      => $findingId,
                'message' => $message
            ]],
            'verified_postconditions' => [$verifiedPostcondition],
            'performed_effects'       => $this->effects->effects(),
            'proposed_effects'        => $proposedEffects,
            'next_action'             => ['action' => 'create_release_run']
        ], 0);
    }

    /**
     * Builds a planning stop before hashing when the descriptive baseline cannot be revalidated
     */
    private function planBaselineResolutionStop(BaselineTagResolutionResult $resolution): MachineResult
    {
        if ($resolution->outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            return $this->results->planBoundaryValueOutcome(
                $resolution->outcome,
                $this->effects->effects()
            );
        }

        $status = $resolution->status ?? BaselineTagResolutionStatus::AMBIGUOUS;
        $moving = $status === BaselineTagResolutionStatus::MOVING;

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'plan',
            'capability'              => 'release_planning',
            'status'                  => $moving ? 'stale_plan' : 'policy_blocked',
            'exit_class'              => $moving ? 'drifted' : 'failed',
            'findings'                => [[
                'id'      => 'release.plan.baseline_tag_'.$status->value,
                'message' => 'The canonical baseline tag did not resolve to one stable ancestral identity.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => $this->effects->effects(),
            'proposed_effects'        => [],
            'next_action'             => ['action' => $moving ? 'refresh_bound_inputs' : 'repair_baseline_authority']
        ], $moving ? 6 : 4);
    }

    /**
     * Checks an artifact against its canonical immutable identity
     *
     * @param PlanArtifactReadResult $read      Governed artifact read.
     * @param array<string, mixed> $artifact     Expected artifact.
     */
    private function verifyExpectedArtifact(
        PlanArtifactReadResult $read,
        array $artifact,
        string $planId
    ): ReleaseBoundaryOperationResult {
        if ($read->outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            return ReleaseBoundaryOperationResult::stopped($read->outcome);
        }

        if (!$read->hasContent()) {
            return ReleaseBoundaryOperationResult::success('invalid');
        }

        $contents = $read->contents ?? '';

        try {
            $persisted = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ReleaseBoundaryOperationResult::success('invalid');
        }

        if (
            !is_array($persisted)
            || ($persisted['plan_id'] ?? null) !== $planId
            || $contents !== $this->json->encode($artifact).PHP_EOL
        ) {
            return ReleaseBoundaryOperationResult::success('invalid');
        }

        unset($persisted['plan_id']);
        $expected = $artifact;
        unset($expected['plan_id']);

        $hash = $this->hashing->sha256($this->json->encode($persisted));

        if (!$hash->hasValue()) {
            return ReleaseBoundaryOperationResult::stopped($hash->outcome);
        }

        $digest = Sha256Digest::tryFrom($hash->value ?? '');

        if (!$digest instanceof Sha256Digest) {
            return ReleaseBoundaryOperationResult::stopped(ReleaseBoundaryOutcome::FAILURE);
        }

        $verified = $this->json->encode($persisted) === $this->json->encode($expected)
            && $digest->value === $planId;

        return ReleaseBoundaryOperationResult::success($verified ? 'verified' : 'invalid');
    }
}
