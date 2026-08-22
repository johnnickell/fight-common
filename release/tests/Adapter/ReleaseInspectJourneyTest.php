<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Application\CompatibilityAssessment;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Covers the read-only release inspection journey. */
#[CoversNothing]
class ReleaseInspectJourneyTest extends UnitTestCase
{
    /**
     * Covers non-authoritative minimum-increment recommendations.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_inspect_recommends_a_non_authoritative_minimum_increment_from_declared_immutable_inputs(): void
    {
        $root = dirname(__DIR__, 3);
        $executable = $root.'/bin/release';

        self::assertTrue(is_executable($executable));
        self::assertStringStartsWith("#!/usr/bin/env bash\n", (string) file_get_contents($executable));
        self::assertFileExists($root.'/release/scripts/release.php');

        $process = ReleaseProcess::create([
            $executable,
            'inspect',
            '--fixture='.$root.'/release/fixtures/inspect-candidate.json'
        ]);

        $process->mustRun();

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('fight-common.release-result/v1', $result['schema_version']);
        self::assertSame('inspect', $result['command']);
        self::assertSame('release_inspection', $result['capability']);
        self::assertSame('succeeded', $result['status']);
        self::assertSame('success', $result['exit_class']);
        self::assertSame('minor', $result['recommendation']['minimum_increment']);
        self::assertSame('1.3.0', $result['recommendation']['recommended_version']);
        self::assertFalse($result['recommendation']['authoritative']);
        self::assertSame(
            CompatibilityAssessment::CATEGORIES,
            array_column($result['recommendation']['compatibility_assessment']['categories'], 'category')
        );
        self::assertSame(
            'maximum_required_increment_across_all_compatibility_categories',
            $result['recommendation']['compatibility_assessment']['rationale']
        );
        self::assertSame([
            'source_commit'       => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline_tag'        => 'v1.2.3',
            'baseline_tag_object' => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            'baseline_commit'     => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
            'support_policy'      => 'support-policy-2026-08'
        ], $result['resolved_inputs']);
        self::assertSame([], $result['proposed_effects']);
        self::assertSame([
            'minimum_increment_recommendation_derived'
        ], $result['verified_postconditions']);
        self::assertSame([
            ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success']
        ], $result['performed_effects']);
        self::assertSame([
            'action'  => 'approve_exact_version_for_plan',
            'version' => '1.3.0'
        ], $result['next_action']);
        self::assertSame('release.inspect.minimum_increment', $result['findings'][0]['id']);
    }

    /**
     * Covers strict, overflow-safe stable SemVer handling at the public command seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_inspect_rejects_noncanonical_semver_and_increments_large_identifiers_exactly(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $root.'/.runs/fight-common-release-inspect-semver-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);

        try {
            $validFixture = $directory.'/valid.json';
            file_put_contents($validFixture, json_encode([
                'source_commit'          => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
                'baseline'               => [
                    'version'    => '18446744073709551616.340282366920938463463374607431768211456.999999999999999999999999999999',
                    'tag_name'   => 'v18446744073709551616.340282366920938463463374607431768211456.999999999999999999999999999999',
                    'tag_object' => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                    'commit'     => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45'
                ],
                'support_policy'         => 'support-policy-2026-08',
                'compatibility_evidence' => self::compatibilityEvidence('patch')
            ], JSON_THROW_ON_ERROR));
            $valid = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$validFixture]);
            $valid->mustRun();
            $validResult = json_decode($valid->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(
                '18446744073709551616.340282366920938463463374607431768211456.1000000000000000000000000000000',
                $validResult['recommendation']['recommended_version']
            );

            foreach (['01.2.3', '1.02.3', '1.2.03', '+1.2.3', '1.2', '1.2.3.4'] as $index => $version) {
                $fixture = $directory.'/invalid-'.$index.'.json';
                file_put_contents($fixture, json_encode([
                    'baseline'               => ['version' => $version],
                    'compatibility_evidence' => self::compatibilityEvidence('patch')
                ], JSON_THROW_ON_ERROR));
                $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$fixture]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $version);
                self::assertSame('release.inspect.baseline_invalid', $result['findings'][0]['id'], $version);
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $fixture) {
                unlink($fixture);
            }

            rmdir($directory);
        }
    }

    /**
     * Covers composed category aggregation and fail-closed evidence at the public command seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_inspect_aggregates_every_category_and_rejects_incomplete_or_declared_aggregates(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $root.'/.runs/fight-common-release-inspect-compatibility-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/release/fixtures/inspect-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            foreach (['patch' => '1.2.4', 'major' => '2.0.0'] as $classification => $version) {
                $fixtureCandidate = $candidate;
                $fixtureCandidate['compatibility_evidence'] = self::compatibilityEvidence($classification);
                $path = $directory.'/'.$classification.'.json';
                file_put_contents($path, json_encode($fixtureCandidate, JSON_THROW_ON_ERROR));
                $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$path]);
                $process->mustRun();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame($classification, $result['recommendation']['minimum_increment']);
                self::assertSame($version, $result['recommendation']['recommended_version']);
            }

            $canonicalPath = $directory.'/canonical-order.json';
            file_put_contents($canonicalPath, json_encode($candidate, JSON_THROW_ON_ERROR));
            $canonicalProcess = ReleaseProcess::create([
                $root.'/bin/release', 'inspect', '--fixture='.$canonicalPath
            ]);
            $canonicalProcess->mustRun();
            $canonicalResult = json_decode($canonicalProcess->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            $permuted = $candidate;
            $permuted['compatibility_evidence'] = array_map(
                static fn (array $entry): array => array_reverse($entry, true),
                array_reverse($permuted['compatibility_evidence'])
            );
            $permutedPath = $directory.'/permuted-order.json';
            file_put_contents($permutedPath, json_encode($permuted, JSON_THROW_ON_ERROR));
            $permutedProcess = ReleaseProcess::create([
                $root.'/bin/release', 'inspect', '--fixture='.$permutedPath
            ]);
            $permutedProcess->mustRun();
            $permutedResult = json_decode($permutedProcess->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame($canonicalResult['recommendation'], $permutedResult['recommendation']);
            self::assertSame([
                ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success']
            ], $permutedResult['performed_effects']);

            $invalidCandidates = [];
            $invalidCandidates['missing'] = $candidate;
            array_pop($invalidCandidates['missing']['compatibility_evidence']);
            $invalidCandidates['duplicate'] = $candidate;
            $invalidCandidates['duplicate']['compatibility_evidence'][1]
                = $invalidCandidates['duplicate']['compatibility_evidence'][0];
            $invalidCandidates['unknown'] = $candidate;
            $invalidCandidates['unknown']['compatibility_evidence'][0]['category'] = 'unknown-category';
            $invalidCandidates['missing-field'] = $candidate;
            unset($invalidCandidates['missing-field']['compatibility_evidence'][0]['evidence_id']);
            $invalidCandidates['extra-field'] = $candidate;
            $invalidCandidates['extra-field']['compatibility_evidence'][0]['aggregate'] = 'minor';

            foreach ($invalidCandidates as $name => $fixtureCandidate) {
                $path = $directory.'/'.$name.'.json';
                file_put_contents($path, json_encode($fixtureCandidate, JSON_THROW_ON_ERROR));
                $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$path]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $name);
                self::assertSame('release.inspect.compatibility_evidence_invalid', $result['findings'][0]['id']);
                self::assertSame([], $result['performed_effects']);
            }

            $indeterminate = $candidate;
            $indeterminate['compatibility_evidence'][4]['classification'] = 'indeterminate';
            $path = $directory.'/indeterminate.json';
            file_put_contents($path, json_encode($indeterminate, JSON_THROW_ON_ERROR));
            $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$path]);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(5, $process->getExitCode());
            self::assertSame('release.inspect.compatibility_indeterminate', $result['findings'][0]['id']);
            self::assertSame([], $result['performed_effects']);

            $declaredLower = $candidate;
            $declaredLower['minimum_increment'] = 'patch';
            $path = $directory.'/declared-lower.json';
            file_put_contents($path, json_encode($declaredLower, JSON_THROW_ON_ERROR));
            $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$path]);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(2, $process->getExitCode());
            self::assertSame('release.inspect.compatibility_aggregate_forbidden', $result['findings'][0]['id']);
            self::assertSame([], $result['performed_effects']);
        } finally {
            foreach (glob($directory.'/*') ?: [] as $fixture) {
                unlink($fixture);
            }

            rmdir($directory);
        }
    }

    /**
     * Covers every established caller-declared compatibility result alias at the public command seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_inspect_rejects_every_caller_declared_derived_compatibility_field_before_git(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $root.'/.runs/fight-common-release-inspect-derived-fields-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/release/fixtures/inspect-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $aliases = [
            'change_class',
            'minimum_increment',
            'release_class',
            'minimum_release_class',
            'authorized_release_class',
            'recommended_version',
            'compatibility_aggregate',
            'compatibility_assessment',
            'recommendation',
            'classification',
            'authoritative',
            'categories',
            'rationale',
            'aggregate'
        ];

        try {
            foreach ($aliases as $alias) {
                $fixture = $directory.'/'.$alias.'.json';
                file_put_contents($fixture, json_encode([...$candidate, $alias => 'caller-declared'], JSON_THROW_ON_ERROR));
                $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$fixture]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $alias);
                self::assertSame('policy_blocked', $result['status'], $alias);
                self::assertSame('invalid_input', $result['exit_class'], $alias);
                self::assertSame(
                    'release.inspect.compatibility_aggregate_forbidden',
                    $result['findings'][0]['id'],
                    $alias
                );
                self::assertSame([], $result['performed_effects'], $alias);
                self::assertSame(
                    ['action' => 'provide_category_compatibility_evidence'],
                    $result['next_action'],
                    $alias
                );
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $fixture) {
                unlink($fixture);
            }

            rmdir($directory);
        }
    }

    /**
     * Covers an unreadable bootstrap fixture stop before the release ledger begins.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_inspect_reports_the_actual_ledger_when_fixture_resolution_stops_early(): void
    {
        $root = dirname(__DIR__, 3);
        $process = ReleaseProcess::create([
            $root.'/bin/release',
            'inspect',
            '--fixture='.$root.'/release/fixtures/missing-inspection-fixture.json'
        ]);

        $process->run();

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(2, $process->getExitCode());
        self::assertSame('release.inspect.fixture_unreadable', $result['findings'][0]['id']);
        self::assertSame([], $result['performed_effects']);

        $fixture = $root.'/.runs/release-inspect-malformed-'.bin2hex(random_bytes(8)).'.json';
        file_put_contents($fixture, '{');

        try {
            $malformed = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$fixture]);
            $malformed->run();
            $malformedResult = json_decode($malformed->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $malformed->getExitCode());
            self::assertSame('release.inspect.fixture_invalid', $malformedResult['findings'][0]['id']);
            self::assertSame([], $malformedResult['performed_effects']);
        } finally {
            unlink($fixture);
        }
    }

    /**
     * Covers successful boundary continuation and resolved-input validation at the command seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_inspect_validates_resolved_identities_after_a_successful_boundary_evaluation(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $root.'/.runs/fight-common-release-inspect-authority-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);

        try {
            $valid = ReleaseProcess::create([
                $root.'/bin/release',
                'inspect',
                '--fixture='.$root.'/release/fixtures/boundary-success.json'
            ]);
            $valid->mustRun();
            $validResult = json_decode($valid->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame('1.3.0', $validResult['recommendation']['recommended_version']);
            self::assertArrayHasKey('resolved_inputs', $validResult);
            self::assertSame([
                'inspection_boundary_effect_completed',
                'minimum_increment_recommendation_derived'
            ], $validResult['verified_postconditions']);

            $candidate = json_decode(
                (string) file_get_contents($root.'/release/fixtures/boundary-success.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $invalidInputs = [
                ['baseline.version', '01.2.3', 'release.inspect.baseline_invalid'],
                ['source_commit', 'not-an-object-id', 'release.inspect.source_commit_invalid'],
                ['baseline.tag_object', 'not-an-object-id', 'release.inspect.baseline_tag_object_invalid'],
                ['baseline.commit', 'not-an-object-id', 'release.inspect.baseline_commit_invalid'],
                ['support_policy', ' ', 'release.inspect.support_policy_invalid']
            ];

            foreach ($invalidInputs as $index => [$field, $value, $findingId]) {
                $invalid = $candidate;

                if (str_contains($field, '.')) {
                    [$parent, $child] = explode('.', $field);
                    $invalid[$parent][$child] = $value;
                } else {
                    $invalid[$field] = $value;
                }

                $fixture = $directory.'/invalid-'.$index.'.json';
                file_put_contents($fixture, json_encode($invalid, JSON_THROW_ON_ERROR));

                $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$fixture]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $field);
                self::assertSame($findingId, $result['findings'][0]['id'], $field);
                self::assertArrayNotHasKey('recommendation', $result, $field);
                self::assertArrayNotHasKey('resolved_inputs', $result, $field);
                self::assertSame([], $result['verified_postconditions'], $field);
                self::assertSame([], $result['performed_effects'], $field);
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $fixture) {
                unlink($fixture);
            }

            rmdir($directory);
        }
    }

    /**
     * Covers unusable and moving baseline tags through the direct command.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_inspect_fails_closed_for_unusable_or_moving_baseline_tags_at_the_public_seam(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $root.'/.runs/fight-common-release-inspect-tags-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/release/fixtures/inspect-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            $historical = $candidate;
            $historical['baseline']['version'] = '1.1.0';
            $historical['baseline']['tag_name'] = '1.1.0';
            $fixture = $directory.'/historical.json';
            file_put_contents($fixture, json_encode($historical, JSON_THROW_ON_ERROR));
            $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$fixture]);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('1.1.0', $result['resolved_inputs']['baseline_tag']);

            $historical['baseline']['tag_name'] = 'v1.1.0';
            $fixture = $directory.'/legacy-alias.json';
            file_put_contents($fixture, json_encode($historical, JSON_THROW_ON_ERROR));
            $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$fixture]);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(2, $process->getExitCode());
            self::assertSame('release.inspect.baseline_tag_invalid', $result['findings'][0]['id']);

            foreach (['missing', 'ambiguous', 'duplicate_normalized', 'non_ancestor'] as $status) {
                $fixture = $directory.'/'.$status.'.json';
                file_put_contents($fixture, json_encode([
                    ...$candidate,
                    'git_resolution' => ['status' => $status]
                ], JSON_THROW_ON_ERROR));
                $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$fixture]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(4, $process->getExitCode(), $status);
                self::assertSame('release.inspect.baseline_tag_'.$status, $result['findings'][0]['id']);
            }

            $fixture = $directory.'/moving.json';
            file_put_contents($fixture, json_encode([
                ...$candidate,
                'git_resolution' => [
                    'status'         => 'resolved',
                    'tag_object_oid' => str_repeat('c', 40)
                ]
            ], JSON_THROW_ON_ERROR));
            $process = ReleaseProcess::create([$root.'/bin/release', 'inspect', '--fixture='.$fixture]);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(6, $process->getExitCode());
            self::assertSame('release.inspect.baseline_tag_moving', $result['findings'][0]['id']);
        } finally {
            foreach (glob($directory.'/*') ?: [] as $fixture) {
                unlink($fixture);
            }

            rmdir($directory);
        }
    }

    /** @return list<array<string, string>> */
    private static function compatibilityEvidence(string $classification): array
    {
        return array_map(
            static fn (string $category): array => [
                'category'       => $category,
                'finding_id'     => 'release.compatibility.'.$category.'.fixture',
                'evidence_id'    => 'evidence.compatibility.'.$category.'.fixture',
                'classification' => $classification
            ],
            CompatibilityAssessment::CATEGORIES
        );
    }
}
