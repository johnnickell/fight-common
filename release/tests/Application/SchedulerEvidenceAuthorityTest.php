<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Common\Application\Scheduler\Exception\SchedulerException;
use Fight\Release\Application\JSendEvidenceAuthority;
use Fight\Release\Application\SchedulerEvidenceAuthority;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
#[CoversClass(SchedulerEvidenceAuthority::class)]
/**
 * Class SchedulerEvidenceAuthorityTest
 */
final class SchedulerEvidenceAuthorityTest extends UnitTestCase
{
    /**
     * Proves the closed finding sets and observations used by copied-package receipts
     */
    public function test_that_exact_scheduler_evidence_is_owned_by_one_authority(): void
    {
        self::assertFalse(SchedulerEvidenceAuthority::isPublicApiProbeReceipt('not-a-receipt'));
        self::assertSame([$this->legacyFindings()[0]], SchedulerEvidenceAuthority::publicApiFindings());
        self::assertSame($this->legacyFindings(), SchedulerEvidenceAuthority::findings(false));
        self::assertSame(
            [...$this->legacyFindings(), $this->portableFinding()],
            SchedulerEvidenceAuthority::findings(true)
        );
        self::assertSame($this->nonZeroFailure(), SchedulerEvidenceAuthority::nonZeroFailureObservation());
        self::assertSame([
            'commands' => ['portable-command'],
            'output'   => "scheduler portable command\n"
        ], SchedulerEvidenceAuthority::portableObservation());

        $publicApiReceipt = [
            'schema_version' => 'fight-common.public-api-representative-probe/v1',
            'findings'       => SchedulerEvidenceAuthority::publicApiFindings(),
            'observations'   => [
                'uuid'                       => '00000000-0000-0000-0000-000000000000',
                'meta'                       => ['consumer' => 'disposable'],
                'collection'                 => ['alpha', 'beta'],
                'transactional_unit_of_work' => $this->transactionalUnitOfWorkObservation(),
                'runtime_deprecations'       => []
            ]
        ];
        self::assertTrue(SchedulerEvidenceAuthority::isPublicApiProbeReceipt($publicApiReceipt));

        $legacyOnlyPublicApiReceipt = $publicApiReceipt;
        $legacyOnlyPublicApiReceipt['observations']['transactional_unit_of_work'] = $this
            ->transactionalUnitOfWorkObservation(false);
        self::assertFalse(SchedulerEvidenceAuthority::isPublicApiProbeReceipt($legacyOnlyPublicApiReceipt));
        self::assertTrue(
            SchedulerEvidenceAuthority::isCanonicalBaselinePublicApiProbeReceipt($legacyOnlyPublicApiReceipt)
        );
        self::assertFalse(
            SchedulerEvidenceAuthority::isCanonicalBaselinePublicApiProbeReceipt($publicApiReceipt)
        );

        foreach (['schema_version', 'findings'] as $field) {
            self::assertFalse(SchedulerEvidenceAuthority::isPublicApiProbeReceipt([
                ...$publicApiReceipt,
                $field => null
            ]));
        }

        foreach (['uuid', 'meta', 'collection', 'transactional_unit_of_work', 'runtime_deprecations'] as $field) {
            $mutated = $publicApiReceipt;
            $mutated['observations'][$field] = null;
            self::assertFalse(SchedulerEvidenceAuthority::isPublicApiProbeReceipt($mutated));
        }

        $publicApiReceipt['observations']['runtime_deprecations'] = [[
            'severity' => 'E_USER_DEPRECATED',
            'message'  => 'controlled public probe deprecation'
        ]];
        self::assertTrue(SchedulerEvidenceAuthority::isPublicApiProbeReceipt($publicApiReceipt));
    }

    /**
     * Proves raw Scheduler-probe evidence is authenticated before aggregate receipt composition
     */
    public function test_that_scheduler_probe_receipt_requires_the_exact_authenticated_envelope(): void
    {
        $receipt = $this->schedulerProbeReceipt(true);

        self::assertTrue(SchedulerEvidenceAuthority::isSchedulerProbeReceipt($receipt));
        self::assertFalse(SchedulerEvidenceAuthority::isSchedulerProbeReceipt('not-a-receipt'));

        foreach (['schema_version', 'findings', 'observations'] as $field) {
            $missing = $receipt;
            unset($missing[$field]);
            self::assertFalse(SchedulerEvidenceAuthority::isSchedulerProbeReceipt($missing));
        }

        $mutatedSchema = $receipt;
        $mutatedSchema['schema_version'] = 'fight-common.scheduler-probe/v999';
        self::assertFalse(SchedulerEvidenceAuthority::isSchedulerProbeReceipt($mutatedSchema));

        $mutatedFinding = $receipt;
        $mutatedFinding['findings'][0]['status'] = 'candidate-only';
        self::assertFalse(SchedulerEvidenceAuthority::isSchedulerProbeReceipt($mutatedFinding));

        $reorderedObservations = $receipt;
        $reorderedObservations['observations'] = array_reverse(
            $reorderedObservations['observations'],
            true
        );
        self::assertFalse(SchedulerEvidenceAuthority::isSchedulerProbeReceipt($reorderedObservations));

        $malformedDeprecation = $receipt;
        $malformedDeprecation['observations']['runtime_deprecations'] = [['message' => 'missing severity']];
        self::assertFalse(SchedulerEvidenceAuthority::isSchedulerProbeReceipt($malformedDeprecation));

        $divergent = $receipt;
        $divergent['observations']['scheduler']['command_output'] = "candidate-only output\n";
        $divergent['observations']['runtime_deprecations'] = [[
            'severity' => 'E_USER_DEPRECATED',
            'message'  => 'candidate-only deprecation'
        ]];
        self::assertTrue(SchedulerEvidenceAuthority::isSchedulerProbeReceipt($divergent));

        $legacyOnly = $this->schedulerProbeReceipt(false);
        self::assertTrue(SchedulerEvidenceAuthority::isSchedulerProbeReceipt($legacyOnly));
    }

    /**
     * Proves copied receipts fail closed when required evidence is omitted or mutated
     */
    public function test_that_copied_receipt_authentication_rejects_omitted_and_mutated_evidence(): void
    {
        $baseline = $this->receipt(str_repeat('b', 64), false, false);
        $candidate = $this->receipt(str_repeat('c', 64), true, true);

        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($baseline));
        self::assertTrue(SchedulerEvidenceAuthority::isCopiedReceipt($candidate));
        self::assertTrue(SchedulerEvidenceAuthority::isCanonicalBaselineReceipt($baseline));
        self::assertFalse(SchedulerEvidenceAuthority::isCanonicalBaselineReceipt($candidate));
        self::assertTrue(SchedulerEvidenceAuthority::receiptsAreEquivalent($baseline, $candidate));

        $candidateWithoutCanonicalAdapter = $this->receipt(str_repeat('c', 64), true, false);
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($candidateWithoutCanonicalAdapter));

        $unbound = $candidate;
        $unbound['resolved_package']['production_tree_sha256'] = str_repeat('e', 64);
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($unbound));

        $omitted = $candidate;
        unset($omitted['probe']['observations']['scheduler']['non_zero_failure']);
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($omitted));
        self::assertFalse(SchedulerEvidenceAuthority::receiptsAreEquivalent($baseline, $omitted));

        $deprecationsOmitted = $candidate;
        unset($deprecationsOmitted['probe']['observations']['runtime_deprecations']);
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($deprecationsOmitted));
        self::assertFalse(SchedulerEvidenceAuthority::receiptsAreEquivalent($baseline, $deprecationsOmitted));

        $transactionalUnitOfWorkOmitted = $candidate;
        unset($transactionalUnitOfWorkOmitted['probe']['observations']['transactional_unit_of_work']);
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($transactionalUnitOfWorkOmitted));
        self::assertFalse(
            SchedulerEvidenceAuthority::receiptsAreEquivalent($baseline, $transactionalUnitOfWorkOmitted)
        );

        $deprecationObserved = $candidate;
        $deprecationObserved['probe']['observations']['runtime_deprecations'] = [[
            'severity' => 'E_USER_DEPRECATED',
            'message'  => 'controlled public probe deprecation'
        ]];
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($deprecationObserved));
        self::assertFalse(SchedulerEvidenceAuthority::receiptsAreEquivalent($baseline, $deprecationObserved));

        $mutated = $candidate;
        $mutated['probe']['observations']['scheduler']['non_zero_failure']['logs'][0]['message'] = 'mutated';
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($mutated));
        self::assertFalse(SchedulerEvidenceAuthority::receiptsAreEquivalent($baseline, $mutated));

        foreach (['uuid', 'meta', 'collection', 'transactional_unit_of_work', 'jsend'] as $field) {
            $genericMutated = $candidate;
            $genericMutated['probe']['observations'][$field] = 'mutated';
            self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($genericMutated));
            self::assertFalse(SchedulerEvidenceAuthority::receiptsAreEquivalent($baseline, $genericMutated));
            self::assertFalse(
                SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $genericMutated)
            );
        }
    }

    /**
     * Proves only authenticated divergent Scheduler receipts classify a candidate incompatibility
     */
    public function test_that_only_proven_divergence_is_classified_as_incompatible(): void
    {
        $baseline = $this->receipt(str_repeat('b', 64), false, false);
        $candidate = $this->receipt(str_repeat('c', 64), true, true);
        self::assertFalse(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $candidate));

        $legacyOnlyCandidate = $this->receipt(str_repeat('c', 64), false, false);
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($legacyOnlyCandidate));
        self::assertTrue(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $legacyOnlyCandidate));

        $legacyOnlyDivergent = $legacyOnlyCandidate;
        $divergentLog = &$legacyOnlyDivergent['probe']['observations']['scheduler']['non_zero_failure']['logs'][0];
        $divergentLog['message'] = 'candidate-only failure report';
        self::assertTrue(
            SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $legacyOnlyDivergent)
        );

        $legacyOnlyMalformed = $legacyOnlyDivergent;
        $legacyOnlyMalformed['probe']['observations']['scheduler']['command_output'] = ['malformed'];
        self::assertFalse(
            SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $legacyOnlyMalformed)
        );

        $nestedArrayMalformed = $candidate;
        $nestedArrayMalformed['probe']['observations']['scheduler']['construction_styles'] = 'malformed';
        self::assertFalse(
            SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $nestedArrayMalformed)
        );

        $divergent = $candidate;
        $divergent['probe']['observations']['scheduler']['command_output'] = "candidate-only output\n";
        self::assertTrue(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $divergent));

        $nonZeroDivergent = $candidate;
        $nonZeroFailure = $nonZeroDivergent['probe']['observations']['scheduler']['non_zero_failure'];
        $nonZeroFailure['logs'][0]['message'] = 'candidate-only failure report';
        $nonZeroDivergent['probe']['observations']['scheduler']['non_zero_failure'] = $nonZeroFailure;
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($nonZeroDivergent));
        self::assertTrue(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $nonZeroDivergent));

        $nonZeroMissing = $candidate;
        unset($nonZeroMissing['probe']['observations']['scheduler']['non_zero_failure']);
        self::assertTrue(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $nonZeroMissing));

        $deprecationsMissing = $candidate;
        unset($deprecationsMissing['probe']['observations']['runtime_deprecations']);
        self::assertFalse(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $deprecationsMissing));

        $deprecationObserved = $candidate;
        $deprecationObserved['probe']['observations']['runtime_deprecations'] = [[
            'severity' => 'E_USER_DEPRECATED',
            'message'  => 'controlled public probe deprecation'
        ]];
        self::assertTrue(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $deprecationObserved));

        $portableDivergent = $candidate;
        $portableDivergent['probe']['observations']['scheduler']['portable_process_runner'] = [
            'commands' => ['candidate-only-command'],
            'output'   => "candidate-only portable output\n"
        ];
        self::assertFalse(SchedulerEvidenceAuthority::isCopiedReceipt($portableDivergent));
        self::assertTrue(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $portableDivergent));

        $portableMalformed = $candidate;
        $portableMalformed['probe']['observations']['scheduler']['portable_process_runner'] = [
            'commands' => 'candidate-only-command',
            'output'   => "candidate-only portable output\n"
        ];
        self::assertFalse(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $portableMalformed));

        $portableKeysMalformed = $candidate;
        $portableKeysMalformed['probe']['observations']['scheduler']['portable_process_runner'] = [
            'output'   => "candidate-only portable output\n",
            'commands' => ['candidate-only-command']
        ];
        self::assertFalse(
            SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $portableKeysMalformed)
        );

        $findingUnauthenticated = $nonZeroDivergent;
        $findingUnauthenticated['findings'][0]['evidence_id'] = 'candidate-only-evidence';
        self::assertFalse(
            SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $findingUnauthenticated)
        );

        $schemaUnauthenticated = $nonZeroDivergent;
        $schemaUnauthenticated['schema_version'] = 'candidate-only-schema/v1';
        self::assertFalse(
            SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $schemaUnauthenticated)
        );

        $genericUnauthenticated = $nonZeroDivergent;
        $genericUnauthenticated['probe']['observations']['meta'] = ['consumer' => 'candidate-only'];
        self::assertFalse(
            SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $genericUnauthenticated)
        );

        $unauthenticated = $divergent;
        $unauthenticated['resolved_package']['production_tree_sha256'] = str_repeat('d', 64);
        self::assertFalse(SchedulerEvidenceAuthority::candidateIsProvenIncompatible($baseline, $unauthenticated));
    }

    /**
     * Proves the exact 2.0.0 Scheduler replan machine contract
     */
    public function test_that_scheduler_replan_contract_rejects_every_mutated_shape(): void
    {
        $replan = [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'compatibility',
            'capability'              => 'compatibility_assessment',
            'status'                  => 'policy_blocked',
            'exit_class'              => 'failed',
            'exit_code'               => 4,
            'findings'                => [[
                'id'      => 'release.compatibility.consumer.scheduler-1x-incompatible',
                'message' => 'The candidate cannot reproduce the published Scheduler 1.1.0 behavior.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => [
                'action'  => 'replan_scheduler_compatibility',
                'version' => '2.0.0'
            ]
        ];

        self::assertSame($replan, SchedulerEvidenceAuthority::incompatibilityResult());
        self::assertTrue(SchedulerEvidenceAuthority::isIncompatibilityResult($replan));
        self::assertTrue(SchedulerEvidenceAuthority::claimsIncompatibility($replan));
        self::assertFalse(SchedulerEvidenceAuthority::claimsIncompatibility(['findings' => []]));
        self::assertTrue(SchedulerEvidenceAuthority::isReplanAction($replan['next_action']));

        $mutations = [
            ['findings' => []],
            ['next_action' => ['action' => 'replan_scheduler_compatibility', 'version' => '1.2.0']],
            ['status' => 'evidence_indeterminate'],
            ['evidence' => []]
        ];
        foreach ($mutations as $mutation) {
            self::assertFalse(SchedulerEvidenceAuthority::isIncompatibilityResult([...$replan, ...$mutation]));
        }
    }

    /** @return list<array{finding_id: string, evidence_id: string, attribution: string, status: string}> */
    private function legacyFindings(): array
    {
        return [[
            'finding_id'  => 'release.compatibility.consumer.public-api-probe-passed',
            'evidence_id' => 'fight-common.consumer.public-api-representative',
            'attribution' => 'release/fixtures/PublicApiConsumer/public-api-probe.php',
            'status'      => 'passed'
        ], [
            'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-construction-passed',
            'evidence_id' => 'fight-common.behavior.scheduler-legacy-construction',
            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
            'status'      => 'passed'
        ], [
            'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-command-passed',
            'evidence_id' => 'fight-common.behavior.scheduler-legacy-command',
            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
            'status'      => 'passed'
        ]];
    }

    /** @return array{finding_id: string, evidence_id: string, attribution: string, status: string} */
    private function portableFinding(): array
    {
        return [
            'finding_id'  => 'release.compatibility.consumer.scheduler-portable-runner-passed',
            'evidence_id' => 'fight-common.behavior.scheduler-portable-runner',
            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
            'status'      => 'passed'
        ];
    }

    /** @return array<string, mixed> */
    private function receipt(string $tree, bool $portable, bool $canonicalDoctrineAdapter): array
    {
        $scheduler = [
            'construction_styles'      => ['two_argument', 'positional_optional', 'named_arguments'],
            'callable_output'          => "scheduler callable\n",
            'command_output'           => "scheduler command\nscheduler command\n",
            'default_process_commands' => ['default-command'],
            'factory_process_commands' => ['factory-command', 'false', 'false'],
            'non_zero_failure'         => $this->nonZeroFailure(),
            ...($portable ? ['portable_process_runner' => [
                'commands' => ['portable-command'],
                'output'   => "scheduler portable command\n"
            ]] : [])
        ];

        return [
            'schema_version'   => 'fight-common.disposable-public-consumer/v1',
            'status'           => 'valid',
            'findings'         => [
                ...$this->legacyFindings(),
                ...($portable ? [$this->portableFinding()] : []),
                ...JSendEvidenceAuthority::findings($portable)
            ],
            'candidate'        => ['production_tree_sha256' => $tree],
            'resolved_package' => [
                'installed_as'           => 'copy',
                'production_tree_sha256' => $tree
            ],
            'lock'             => ['sha256' => str_repeat('d', 64)],
            'probe'            => [
                'sha256'       => str_repeat('a', 64),
                'observations' => [
                    'uuid'                       => '00000000-0000-0000-0000-000000000000',
                    'meta'                       => ['consumer' => 'disposable'],
                    'collection'                 => ['alpha', 'beta'],
                    'transactional_unit_of_work' => $this->transactionalUnitOfWorkObservation(
                        $canonicalDoctrineAdapter
                    ),
                    'runtime_deprecations'       => [],
                    'scheduler'                  => $scheduler,
                    'jsend'                      => JSendEvidenceAuthority::observation($portable)
                ]
            ]
        ];
    }

    /** @return array<string, mixed> */
    private function schedulerProbeReceipt(bool $portable): array
    {
        $receipt = $this->receipt(str_repeat('c', 64), $portable, true);

        return [
            'schema_version' => 'fight-common.scheduler-probe/v1',
            'findings'       => [
                ...array_slice($this->legacyFindings(), 1),
                ...($portable ? [$this->portableFinding()] : [])
            ],
            'observations'   => [
                'runtime_deprecations' => [],
                'scheduler'            => $receipt['probe']['observations']['scheduler']
            ]
        ];
    }

    /** @return array<string, mixed> */
    private function transactionalUnitOfWorkObservation(bool $canonicalDoctrineAdapter = true): array
    {
        return [
            'canonical_adapter'    => [
                'available'                       => $canonicalDoctrineAdapter,
                'transactional_unit_of_work_only' => $canonicalDoctrineAdapter,
                'standalone_commit_exposed'       => false
            ],
            'legacy_adapter'       => [
                'available'                 => true,
                'unit_of_work'              => true,
                'standalone_commit_exposed' => true,
                'commit_calls'              => 1
            ],
            'transactional_result' => 'committed',
            'transactional_closed' => !$canonicalDoctrineAdapter,
            'runtime_deprecations' => []
        ];
    }

    /** @return array<string, mixed> */
    private function nonZeroFailure(): array
    {
        $log = [
            'level'   => 'error',
            'message' => 'Command exited with non-zero status 1',
            'context' => [
                'keys'      => ['exception'],
                'exception' => [
                    'class'   => SchedulerException::class,
                    'message' => 'Command exited with non-zero status 1',
                    'code'    => 0
                ]
            ]
        ];
        $notification = [
            'subject' => '[Scheduler] Job "consumer-failing-command" failed',
            'from'    => [['address' => 'scheduler@example.com', 'name' => null]],
            'to'      => [['address' => 'operator@example.com', 'name' => null]],
            'content' => [
                'environment'  => 'Environment: consumer',
                'error'        => 'Error: Command exited with non-zero status 1',
                'code'         => 'Code: 0',
                'content_type' => 'text/plain',
                'charset'      => 'utf-8'
            ]
        ];

        return [
            'attempts'                       => 2,
            'reported_exit_codes'            => [1, 1],
            'logs'                           => [$log, $log],
            'notification_count'             => 2,
            'notifications'                  => [$notification, $notification],
            'lock_reacquired_after_attempts' => [true, true]
        ];
    }
}

// phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
