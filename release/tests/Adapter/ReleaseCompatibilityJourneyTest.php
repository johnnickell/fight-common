<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ReleaseCompatibilityJourneyTest
 *
 * Covers the read-only public compatibility-evidence journey.
 */
#[CoversNothing]
final class ReleaseCompatibilityJourneyTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves repository-owned compatibility authority without caller-supplied policy or success fixtures.
     */
    public function test_that_compatibility_composes_authenticated_repository_evidence_without_release_effects(): void
    {
        $root = dirname(__DIR__, 3);
        $process = ReleaseProcess::create([$root.'/bin/release', 'compatibility']);

        $process->mustRun();

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('fight-common.release-result/v1', $result['schema_version']);
        self::assertSame('compatibility', $result['command']);
        self::assertSame('compatibility_assessment', $result['capability']);
        self::assertSame('succeeded', $result['status']);
        self::assertSame('success', $result['exit_class']);
        self::assertSame(0, $result['exit_code']);
        self::assertSame('valid', $result['evidence']['manifest']['status']);
        self::assertSame('1.1.0', $result['evidence']['manifest']['baseline']['version']);
        self::assertSame('valid', $result['evidence']['structural']['status']);
        self::assertSame(
            'fight-common.disposable-public-consumer/v1',
            $result['evidence']['consumer']['schema_version']
        );
        self::assertSame('copy', $result['evidence']['consumer']['resolved_package']['installed_as']);
        self::assertSame(
            $result['evidence']['consumer']['candidate']['production_tree_sha256'],
            $result['evidence']['consumer']['resolved_package']['production_tree_sha256']
        );
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $result['evidence']['consumer']['lock']['sha256']);
        self::assertSame([], $result['performed_effects']);
        self::assertSame([], $result['proposed_effects']);
        self::assertSame(
            [
                'compatibility_manifest_authenticated',
                'structural_evidence_composed',
                'disposable_public_consumer_verified'
            ],
            $result['verified_postconditions']
        );
        self::assertSame(
            ['action' => 'review_compatibility_evidence'],
            $result['next_action']
        );
        self::assertSame(
            'release.compatibility.harness-completed',
            $result['findings'][0]['id']
        );

        $fabricated = ReleaseProcess::create([
            $root.'/bin/release',
            'compatibility',
            '--fixture='.$root.'/release/fixtures/inspect-candidate.json'
        ]);
        $fabricated->run();

        $rejection = json_decode($fabricated->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(2, $fabricated->getExitCode());
        self::assertSame('release.compatibility.arguments_invalid', $rejection['findings'][0]['id']);
        self::assertSame([], $rejection['performed_effects']);
    }

    /**
     * Proves a real manifest classification omission remains attributed through the release process seam.
     */
    public function test_that_compatibility_preserves_a_manifest_missing_classification_finding(): void
    {
        $root = dirname(__DIR__, 3);
        $filesystem = new Filesystem();
        $repository = sys_get_temp_dir().'/fight-common-missing-classification-'.bin2hex(random_bytes(8));
        $filesystem->mkdir([
            $repository.'/bin',
            $repository.'/compatibility',
            $repository.'/release/scripts'
        ]);

        foreach (['vendor', 'src', 'planning', 'docs', 'tests'] as $directory) {
            $filesystem->symlink($root.'/'.$directory, $repository.'/'.$directory);
        }

        foreach (['CONTEXT.md', 'composer.json'] as $file) {
            $filesystem->symlink($root.'/'.$file, $repository.'/'.$file);
        }

        $filesystem->symlink($root.'/bin/release', $repository.'/bin/release');
        $filesystem->symlink($root.'/release/README.md', $repository.'/release/README.md');
        $filesystem->symlink($root.'/release/tests', $repository.'/release/tests');
        $filesystem->copy($root.'/release/scripts/release.php', $repository.'/release/scripts/release.php');

        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $completeManifest = $manifest;
        $missing = array_pop($manifest['declarations']);
        self::assertIsArray($missing);
        self::assertIsString($missing['name']);
        $filesystem->dumpFile(
            $repository.'/compatibility/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
        );

        try {
            $process = ReleaseProcess::create([$repository.'/bin/release', 'compatibility']);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(5, $process->getExitCode());
            self::assertSame('evidence_indeterminate', $result['status']);
            self::assertSame('uncertain', $result['exit_class']);
            self::assertSame([[
                'id'          => 'release.compatibility.structural-api.missing-classification',
                'message'     => 'Structural compatibility authority rejected the evidence.',
                'attribution' => 'compatibility-manifest',
                'subject'     => $missing['name'],
                'operation'   => null
            ]], $result['findings']);
            self::assertSame([], $result['performed_effects']);
            self::assertSame([], $result['proposed_effects']);
            self::assertSame([], $result['verified_postconditions']);
            self::assertSame(
                ['action' => 'restore_manifest_evidence_and_retry'],
                $result['next_action']
            );

            $twoMissingManifest = $completeManifest;
            $twoMissing = [
                array_pop($twoMissingManifest['declarations']),
                array_pop($twoMissingManifest['declarations'])
            ];
            foreach ($twoMissing as $missingEntry) {
                self::assertIsArray($missingEntry);
            }

            $missingSubjects = array_column($twoMissing, 'name');
            sort($missingSubjects, SORT_STRING);
            $filesystem->dumpFile(
                $repository.'/compatibility/manifest.json',
                json_encode(
                    $twoMissingManifest,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ).PHP_EOL
            );
            $twoMissingProcess = ReleaseProcess::create([$repository.'/bin/release', 'compatibility']);
            $twoMissingProcess->run();
            $twoMissingResult = json_decode(
                $twoMissingProcess->getOutput(),
                true,
                flags: JSON_THROW_ON_ERROR
            );

            self::assertSame(5, $twoMissingProcess->getExitCode());
            self::assertSame(array_map(static fn (string $subject): array => [
                'id'          => 'release.compatibility.structural-api.missing-classification',
                'message'     => 'Structural compatibility authority rejected the evidence.',
                'attribution' => 'compatibility-manifest',
                'subject'     => $subject,
                'operation'   => null
            ], $missingSubjects), $twoMissingResult['findings']);
            self::assertSame([], $twoMissingResult['verified_postconditions']);
            self::assertSame([], $twoMissingResult['performed_effects']);
            self::assertSame([], $twoMissingResult['proposed_effects']);
            self::assertSame(
                ['action' => 'restore_manifest_evidence_and_retry'],
                $twoMissingResult['next_action']
            );

            $invalidClassification = $completeManifest;
            $invalidClassification['declarations'][0]['classification'] = 'unknown';

            $missingClassification = $completeManifest;
            unset($missingClassification['declarations'][0]['classification']);

            $multipleInvalidClassifications = $completeManifest;
            $multipleInvalidClassifications['declarations'][0]['classification'] = 'unknown';
            unset($multipleInvalidClassifications['functions'][0]['classification']);

            $classificationFailures = [
                [$invalidClassification, [$invalidClassification['declarations'][0]['name']]],
                [$missingClassification, [$missingClassification['declarations'][0]['name']]],
                [
                    $multipleInvalidClassifications,
                    [
                        $multipleInvalidClassifications['declarations'][0]['name'],
                        $multipleInvalidClassifications['functions'][0]['name']
                    ]
                ]
            ];
            foreach ($classificationFailures as [$invalidManifest, $invalidSubjects]) {
                sort($invalidSubjects, SORT_STRING);
                $filesystem->dumpFile(
                    $repository.'/compatibility/manifest.json',
                    json_encode(
                        $invalidManifest,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ).PHP_EOL
                );
                $invalidProcess = ReleaseProcess::create([$repository.'/bin/release', 'compatibility']);
                $invalidProcess->run();
                $invalidResult = json_decode(
                    $invalidProcess->getOutput(),
                    true,
                    flags: JSON_THROW_ON_ERROR
                );

                self::assertSame(5, $invalidProcess->getExitCode());
                self::assertSame('evidence_indeterminate', $invalidResult['status']);
                self::assertSame('uncertain', $invalidResult['exit_class']);
                self::assertSame(array_map(static fn (string $subject): array => [
                    'id'          => 'release.compatibility.structural-api.missing-classification',
                    'message'     => 'Structural compatibility authority rejected the evidence.',
                    'attribution' => 'compatibility-manifest',
                    'subject'     => $subject,
                    'operation'   => null
                ], $invalidSubjects), $invalidResult['findings']);
                self::assertSame([], $invalidResult['verified_postconditions']);
                self::assertSame([], $invalidResult['performed_effects']);
                self::assertSame([], $invalidResult['proposed_effects']);
                self::assertSame(
                    ['action' => 'restore_manifest_evidence_and_retry'],
                    $invalidResult['next_action']
                );
            }
        } finally {
            $filesystem->remove($repository);
        }
    }
}
