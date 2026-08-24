<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\BaselineStructuralInventoryPort;
use Fight\Release\Application\Boundary\CompatibilityInputPort;
use Fight\Release\Application\Boundary\CompatibilityWorkspacePort;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\PublicConsumerPort;
use Fight\Release\Application\Boundary\PublicConsumerProbeRejected;
use Fight\Release\Application\Boundary\StructuralInventoryPort;
use RuntimeException;
use Throwable;

/**
 * Class CompatibilityAssessmentService
 */
final readonly class CompatibilityAssessmentService
{
    /**
     * Constructs CompatibilityAssessmentService
     */
    public function __construct(
        private CompatibilityInputPort $input,
        private CompatibilityWorkspacePort $workspace,
        private StructuralInventoryPort $inventory,
        private BaselineStructuralInventoryPort $baselineInventory,
        private GitPort $git,
        private PublicConsumerPort $consumer,
        private CompatibilityManifestAuthority $manifestAuthority = new PublicApiManifestAuthority(),
        private StructuralCompatibilityAuthority $structuralComparison = new StructuralApiComparison()
    ) {
    }

    /**
     * Runs repository compatibility assessment without performing or proposing release effects
     */
    public function assess(string $root): MachineResult
    {
        $stage = 'manifest';
        $workspace = null;

        try {
            $workspace = $this->workspace->createWorkspace();
            $manifestPath = $root.'/compatibility/manifest.json';
            $manifest = $this->manifestAuthority->validate(
                $manifestPath,
                $root,
                $this->input,
                $this->inventory,
                $this->git
            );
            $manifestPolicy = json_decode($this->input->read($manifestPath), true, flags: JSON_THROW_ON_ERROR);

            $stage = 'baseline';
            $baseline = $this->baselineInventory->baselineStructuralInventory(
                $manifest['baseline']['peeled_commit_oid'],
                $workspace
            );

            $stage = 'candidate';
            $candidate = $this->inventory->structuralInventory($root, str_repeat('0', 64));
            $candidate['source_oid'] = hash(
                'sha256',
                json_encode(
                    [$candidate['declarations'], $candidate['functions']],
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                )
            );

            $stage = 'structural';
            $structural = $this->structuralComparison->compare(
                $manifestPolicy,
                $baseline,
                $candidate,
                $this->structuralComparison->checker($baseline, $candidate)
            );
            if (($structural['status'] ?? null) !== 'valid') {
                $finding = CompatibilityFinding::fromStructuralResult($structural);
                $finding instanceof CompatibilityFinding
                    || throw new RuntimeException('Structural evidence was rejected without an authenticated finding.');

                return $this->rejected([$finding], 'structural');
            }

            $stage = 'consumer';
            $baselineConsumerRoot = $workspace.'/consumer/baseline';
            $candidateConsumerRoot = $workspace.'/consumer/candidate';
            $this->workspace->createDirectory($baselineConsumerRoot);
            $this->workspace->createDirectory($candidateConsumerRoot);
            $baselineConsumer = $this->consumer->run(
                $workspace.'/baseline',
                $root.'/release/fixtures/PublicApiConsumer',
                $baselineConsumerRoot
            );
            SchedulerEvidenceAuthority::isCanonicalBaselineReceipt($baselineConsumer)
                || throw new RuntimeException('Canonical baseline Scheduler evidence is invalid.');
            try {
                $candidateConsumer = $this->consumer->run(
                    $root,
                    $root.'/release/fixtures/PublicApiConsumer',
                    $candidateConsumerRoot
                );
            } catch (PublicConsumerProbeRejected) {
                return $this->schedulerIncompatibility();
            }

            if (SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baselineConsumer, $candidateConsumer)) {
                return $this->schedulerIncompatibility();
            }

            $consumer = [
                ...$candidateConsumer,
                'package_probes' => [
                    'baseline'               => [
                        'identity'    => [
                            'version'                => $manifest['baseline']['version'],
                            'peeled_commit_oid'      => $manifest['baseline']['peeled_commit_oid'],
                            'production_tree_sha256' => $baselineConsumer['candidate']['production_tree_sha256']
                        ],
                        'attribution' => 'baseline',
                        'receipt'     => $baselineConsumer
                    ],
                    'candidate'              => [
                        'identity'    => [
                            'production_tree_sha256' => $candidateConsumer['candidate']['production_tree_sha256']
                        ],
                        'attribution' => 'candidate',
                        'receipt'     => $candidateConsumer
                    ],
                    'distinct_installations' => $baselineConsumerRoot !== $candidateConsumerRoot
                ]
            ];

            return new MachineResult([
                'schema_version'          => 'fight-common.release-result/v1',
                'command'                 => 'compatibility',
                'capability'              => 'compatibility_assessment',
                'status'                  => 'succeeded',
                'exit_class'              => 'success',
                'findings'                => [[
                    'id'      => 'release.compatibility.harness-completed',
                    'message' => 'Repository-owned compatibility evidence completed without certifying release.'
                ]],
                'verified_postconditions' => [
                    'compatibility_manifest_authenticated',
                    'structural_evidence_composed',
                    'disposable_public_consumer_verified',
                    'baseline_and_candidate_public_probes_verified'
                ],
                'performed_effects'       => [],
                'proposed_effects'        => [],
                'next_action'             => ['action' => 'review_compatibility_evidence'],
                'evidence'                => [
                    'manifest'   => $manifest,
                    'structural' => $structural,
                    'consumer'   => $consumer
                ]
            ], 0);
        } catch (CompatibilityManifestRejected $rejected) {
            return $this->rejected($rejected->findings, 'manifest');
        } catch (Throwable) {
            return new MachineResult([
                'schema_version'          => 'fight-common.release-result/v1',
                'command'                 => 'compatibility',
                'capability'              => 'compatibility_assessment',
                'status'                  => 'evidence_indeterminate',
                'exit_class'              => 'uncertain',
                'findings'                => [[
                    'id'      => 'release.compatibility.'.$stage.'-evidence-unavailable',
                    'message' => 'Required '.$stage.' compatibility evidence is unavailable or invalid.'
                ]],
                'verified_postconditions' => [],
                'performed_effects'       => [],
                'proposed_effects'        => [],
                'next_action'             => ['action' => 'restore_'.$stage.'_evidence_and_retry']
            ], 5);
        } finally {
            if (is_string($workspace)) {
                $this->workspace->remove($workspace);
            }
        }
    }

    /**
     * Returns authenticated fail-closed authority results
     *
     * @param array $findings Authenticated findings.
     *
     * @phpstan-param non-empty-list<CompatibilityFinding> $findings
     */
    private function rejected(array $findings, string $stage): MachineResult
    {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'compatibility',
            'capability'              => 'compatibility_assessment',
            'status'                  => 'evidence_indeterminate',
            'exit_class'              => 'uncertain',
            'findings'                => array_map(
                static fn (CompatibilityFinding $finding): array => $finding->machineFinding(),
                $findings
            ),
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'restore_'.$stage.'_evidence_and_retry']
        ], 5);
    }

    /**
     * Returns the stable stop for a candidate that cannot reproduce the Scheduler 1.x contract
     */
    private function schedulerIncompatibility(): MachineResult
    {
        return new MachineResult(SchedulerEvidenceAuthority::incompatibilityResult(), 4);
    }
}
