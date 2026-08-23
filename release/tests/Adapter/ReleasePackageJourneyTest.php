<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Test\Common\TestCase\UnitTestCase;
use Fight\Release\Application\CanonicalJson;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Covers the complete package journey through the bin/release command. */
#[CoversNothing]
class ReleasePackageJourneyTest extends UnitTestCase
{
    /**
     * Covers packaging after a complete plan-and-prepare pipeline in a real temp git repo.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_prepare_package_journey_produces_a_verified_archive_result(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-package-journey-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $handoffFixtures = $context['artifacts'];

            $packageResult = $this->livePackage($context, $handoffFixtures);

            self::assertSame(0, $packageResult['exit_code']);
            self::assertSame('packaged', $packageResult['status']);
            self::assertSame('release_packaging', $packageResult['capability']);
            self::assertSame('release.package.completed', $packageResult['findings'][0]['id']);
            self::assertSame($context['plan_id'], $packageResult['plan_id']);
            self::assertSame($context['run_id'], $packageResult['run_id']);
            self::assertSame(['action' => 'certify_release_package'], $packageResult['next_action']);
            self::assertMatchesRegularExpression(
                '/\A[0-9a-f]{64}\z/D',
                $packageResult['archive_digest']
            );
            self::assertMatchesRegularExpression(
                '/\A[0-9a-f]{40,64}\z/D',
                $packageResult['candidate_oid']
            );
            self::assertSame(
                ['phase_handoff_revalidated', 'archive_created_and_verified'],
                $packageResult['verified_postconditions']
            );
            self::assertSame(
                'fight-common.package-effect-set/v1',
                $packageResult['effect_set']['schema_version']
            );
            self::assertSame(
                'fight-common-v1.3.0.zip',
                $packageResult['effect_set']['archive_name']
            );
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers that packaging rejects an invalid handoff through the CLI.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_cli_rejects_an_invalid_handoff(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-package-invalid-'.bin2hex(random_bytes(8));
        mkdir($runtimeRoot.'/.runs/release', 0700, true);

        try {
            $handoffPath = $runtimeRoot.'/.runs/release/invalid-handoff.json';
            file_put_contents($handoffPath, '{"not":"valid"}'."\n");

            $result = ReleaseProcess::create([
                dirname(__DIR__, 3).'/bin/release',
                'package',
                '--handoff='.$handoffPath
            ], ['FIGHT_COMMON_RELEASE_TEST_REPOSITORY' => $runtimeRoot]);
            $result->run();

            $packageResult = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $packageResult['exit_code']);
            self::assertSame('release.package.handoff_invalid', $packageResult['findings'][0]['id']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /** @return array<string, mixed> */
    private function planAndPrepare(string $runtimeRoot): array
    {
        mkdir($runtimeRoot.'/.runs/release', 0700, true);
        $this->git($runtimeRoot, ['init', '--quiet']);
        file_put_contents($runtimeRoot.'/release.txt', "baseline\n");
        $this->git($runtimeRoot, ['add', 'release.txt']);
        $this->git($runtimeRoot, [
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'commit', '--quiet', '-m', 'baseline'
        ]);
        $baselineOid = $this->git($runtimeRoot, ['rev-parse', 'HEAD']);
        $this->git($runtimeRoot, [
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline'
        ]);
        $tagOid = $this->git($runtimeRoot, ['rev-parse', 'refs/tags/v1.2.3']);
        file_put_contents($runtimeRoot.'/release.txt', "candidate\n");
        $this->git($runtimeRoot, ['add', 'release.txt']);
        $this->git($runtimeRoot, [
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'commit', '--quiet', '-m', 'candidate'
        ]);
        $sourceOid = $this->git($runtimeRoot, ['rev-parse', 'HEAD']);
        $candidate = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/release/fixtures/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $candidate['source_commit_oid'] = $sourceOid;
        $candidate['baseline']['tag_object_oid'] = $tagOid;
        $candidate['baseline']['peeled_commit_oid'] = $baselineOid;
        $candidate['release_approval_authority']['candidate_commit_oid'] = $sourceOid;
        $candidate['release_approval_authority']['baseline_tag_object_oid'] = $tagOid;
        $candidate['release_approval_authority']['baseline_peeled_commit_oid'] = $baselineOid;
        $candidate['git_resolution'] = [
            'status'            => 'resolved',
            'tag_name'          => 'v1.2.3',
            'tag_object_oid'    => $tagOid,
            'peeled_commit_oid' => $baselineOid
        ];
        $fixturePath = $runtimeRoot.'/.runs/release/candidate.json';
        file_put_contents($fixturePath, json_encode($candidate, JSON_THROW_ON_ERROR).PHP_EOL);
        $environment = ['FIGHT_COMMON_RELEASE_TEST_REPOSITORY' => $runtimeRoot];
        $plan = ReleaseProcess::create([
            dirname(__DIR__, 3).'/bin/release',
            'plan',
            '--fixture='.$fixturePath,
            '--output='.$runtimeRoot.'/.runs/release'
        ], $environment);
        $plan->mustRun();
        $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $planArtifact = json_decode(
            (string) file_get_contents($planResult['artifact']['path']),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $authority = array_intersect_key($planArtifact, array_flip([
            'compatibility_exceptions',
            'evidence_manifest_digest',
            'patch_exception_authorities',
            'release_approval_authority',
            'required_approvals',
            'support_policy_identity'
        ]));
        $authority['schema_version'] = 'fight-common.release-plan-authority/v1';
        $authorityPath = $runtimeRoot.'/.runs/release/current-authority.json';
        $json = new CanonicalJson();
        file_put_contents($authorityPath, $json->encode($authority).PHP_EOL);
        $prepare = ReleaseProcess::create([
            dirname(__DIR__, 3).'/bin/release',
            'prepare',
            '--plan='.$planResult['artifact']['path'],
            '--authority='.$authorityPath
        ], $environment);
        $prepare->mustRun();
        $prepareResult = json_decode($prepare->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        return [
            'artifacts'      => $prepareResult['artifacts'],
            'environment'    => $environment,
            'plan_id'        => $prepareResult['plan_id'],
            'run_id'         => $prepareResult['run_id'],
            'source_oid'     => $sourceOid
        ];
    }

    /**
     * Executes one normal package through the CLI.
     *
     * @param array $context Live release context.
     * @param array $handoffFixtures Handoff artifact references from prepare.
     *
     * @return array<string, mixed>
     *
     * @phpstan-param array<string, mixed> $context
     * @phpstan-param array{evidence_manifest: array{manifest_id: string, path: string}, phase_handoff: array{handoff_id: string, path: string}} $handoffFixtures
     */
    private function livePackage(array $context, array $handoffFixtures): array
    {
        $packageFixture = [
            'archive_file_list' => [
                'composer.json' => '{"name":"fight/common"}',
                'release.txt' => 'candidate'
            ]
        ];
        $fixturePath = dirname($handoffFixtures['phase_handoff']['path']).'/package-fixture.json';
        file_put_contents($fixturePath, json_encode($packageFixture, JSON_THROW_ON_ERROR).PHP_EOL);

        $process = ReleaseProcess::create([
            dirname(__DIR__, 3).'/bin/release',
            'package',
            '--handoff='.$handoffFixtures['phase_handoff']['path'],
            '--fixture='.$fixturePath
        ], $context['environment']);
        $process->run();

        $output = $process->getOutput();

        if ($output === '') {
            throw new \RuntimeException('Package process produced no output. Error: '.$process->getErrorOutput());
        }

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param string[] $command */
    private function git(string $runtimeRoot, array $command): string
    {
        return trim((string) shell_exec('git -C '.escapeshellarg($runtimeRoot).' '.implode(' ', array_map('escapeshellarg', $command))));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}