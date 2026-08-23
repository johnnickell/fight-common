<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Application\ReleaseCertificationArtifactFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class ReleaseCertificationArtifactFactoryTest
 *
 * Covers canonical certification artifact payload composition.
 */
#[CoversClass(ReleaseCertificationArtifactFactory::class)]
final class ReleaseCertificationArtifactFactoryTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    /**
     * Covers package handoff, success-manifest, and durable-stop payload composition.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_certification_artifacts_preserve_the_governed_package_bindings(): void
    {
        $factory = new ReleaseCertificationArtifactFactory();
        $prepared = $this->preparedHandoff();
        $handoff = $factory->handoff(
            $prepared,
            str_repeat('p', 64),
            str_repeat('a', 64),
            str_repeat('e', 64)
        );
        $handoffId = str_repeat('h', 64);
        $evidenceId = str_repeat('i', 64);
        $evidence = [
            'classification_records' => ['structural-api' => ['classification' => 'patch']],
            'lanes'                  => [
                'archive_install'       => ['outcome' => 'verified'],
                'compatibility_git_ref' => ['outcome' => 'verified'],
                'dependency_latest'     => ['outcome' => 'verified'],
                'dependency_locked'     => ['outcome' => 'verified'],
                'dependency_lowest'     => ['outcome' => 'verified'],
                'planning_api'          => ['outcome' => 'verified'],
                'quality'               => ['outcome' => 'verified']
            ]
        ];

        self::assertSame('certification', $handoff['phase']);
        self::assertSame('packaged', $handoff['status']);
        self::assertSame(str_repeat('p', 64), $handoff['prepared_handoff_id']);
        self::assertSame(str_repeat('a', 64), $handoff['bindings']['archive_digest']);
        self::assertSame(str_repeat('e', 64), $handoff['bindings']['package_effect_set_id']);

        $manifest = $factory->manifest($handoff, $evidence, $handoffId, $evidenceId);

        self::assertSame('certified', $manifest['status']);
        self::assertSame($handoffId, $manifest['certification_handoff_id']);
        self::assertSame($evidenceId, $manifest['certification_evidence_id']);
        self::assertSame('latest-permitted', $manifest['lanes']['dependency_latest']['resolution']);
        self::assertSame('repository-locked', $manifest['lanes']['dependency_locked']['resolution']);
        self::assertSame('lowest-permitted', $manifest['lanes']['dependency_lowest']['resolution']);
        self::assertSame($handoff['bindings']['baseline'], $manifest['lanes']['planning_api']['baseline']);

        self::assertSame([
            'certification_handoff_id' => $handoffId,
            'lane'                     => 'quality',
            'outcome'                  => 'failed',
            'phase'                    => 'certification',
            'plan_id'                  => $handoff['plan_id'],
            'run_id'                   => $handoff['run_id'],
            'schema_version'           => 'fight-common.release-certification-stop/v1',
            'state'                    => 'certification_failed'
        ], $factory->stop(
            $handoff,
            $handoffId,
            'certification_failed',
            'quality',
            'failed'
        ));
    }
    // phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /** @return array<string, mixed> */
    private function preparedHandoff(): array
    {
        return [
            'approvals' => [
                'release'  => ['approval_id' => 'release-approval-001'],
                'required' => ['release-approval-001']
            ],
            'bindings'  => [
                'approved_version'         => '1.3.0',
                'baseline'                 => ['version' => '1.2.3'],
                'source_commit_oid'        => str_repeat('c', 40),
                'evidence_manifest_digest' => str_repeat('m', 64)
            ],
            'plan_id'   => str_repeat('l', 64),
            'run_id'    => str_repeat('r', 64)
        ];
    }
}
