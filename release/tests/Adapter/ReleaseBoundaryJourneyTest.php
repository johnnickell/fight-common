<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Covers deterministic release boundary journeys. */
#[CoversNothing]
class ReleaseBoundaryJourneyTest extends UnitTestCase
{
    /**
     * Covers deterministic boundary classification and its permitted ledger effects.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_deterministic_boundary_fixtures_classify_each_outcome_and_record_only_allowed_effects(): void
    {
        $root = dirname(__DIR__, 3);

        foreach (
            [
            'success'     => ['succeeded', 'success', 0],
            'refusal'     => ['authority_required', 'refused', 3],
            'failure'     => ['policy_blocked', 'failed', 4],
            'uncertainty' => ['evidence_indeterminate', 'uncertain', 5],
            'drift'       => ['stale_plan', 'drifted', 6]
            ] as $outcome => [$status, $exitClass, $exitCode]
        ) {
            $process = ReleaseProcess::create([
                $root.'/bin/release',
                'inspect',
                '--fixture='.$root.'/release/fixtures/boundary-'.$outcome.'.json'
            ]);
            $process->run();

            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame($exitCode, $process->getExitCode());
            self::assertSame($status, $result['status']);
            self::assertSame($exitClass, $result['exit_class']);
            self::assertSame('release_inspection', $result['capability']);

            if ($outcome === 'success') {
                self::assertSame('release.inspect.minimum_increment', $result['findings'][0]['id']);
                self::assertSame('1.3.0', $result['recommendation']['recommended_version']);
                self::assertArrayHasKey('resolved_inputs', $result);
                self::assertSame([
                    'inspection_boundary_effect_completed',
                    'minimum_increment_recommendation_derived'
                ], $result['verified_postconditions']);
            } else {
                self::assertSame($outcome, $result['findings'][0]['outcome']);
                self::assertSame([], $result['verified_postconditions']);
            }

            $expectedEffects = [
                ['capability' => 'git', 'effect_class' => 'git.inspect_repository', 'outcome' => $outcome]
            ];

            if ($outcome === 'success') {
                $expectedEffects[] = [
                    'capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'
                ];
            }

            self::assertSame($expectedEffects, $result['performed_effects']);
        }
    }

    /**
     * Covers commands rejected before an impermissible ledger effect is recorded.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_forbidden_effects_and_unsupported_commands_are_rejected_before_the_ledger_records_an_effect(): void
    {
        $root = dirname(__DIR__, 3);

        /**
         * @var list<array{
         *     0: string,
         *     1: string,
         *     2: string|null,
         *     3: list<array{capability: string, effect_class: string, outcome: string}>
         * }> $cases
         */
        $cases = [
            [
                'inspect',
                $root.'/release/fixtures/boundary-forbidden-effect.json',
                null,
                []
            ],
            ['publish', $root.'/release/fixtures/boundary-success.json', null, []],
            [
                'inspect',
                $root.'/release/fixtures/boundary-success.json',
                '--ledger='.sys_get_temp_dir().'/outside-runs-ledger.json',
                []
            ]
        ];

        foreach ($cases as [$command, $fixture, $ledger, $performedEffects]) {
            $arguments = [
                $root.'/bin/release',
                $command,
                '--fixture='.$fixture
            ];

            if ($ledger !== null) {
                $arguments[] = $ledger;
            }

            $process = ReleaseProcess::create([
                ...$arguments
            ]);
            $process->run();

            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $process->getExitCode());
            self::assertSame('policy_blocked', $result['status']);
            self::assertSame('invalid_input', $result['exit_class']);
            self::assertSame(
                $command === 'inspect' ? 'release_inspection' : 'unsupported_command',
                $result['capability']
            );
            self::assertSame([], $result['verified_postconditions']);
            self::assertSame($performedEffects, $result['performed_effects']);

            if (str_contains($fixture, 'boundary-forbidden-effect.json')) {
                self::assertSame('release.capability.effect_forbidden', $result['findings'][0]['id']);
            }

            if ($command === 'publish') {
                self::assertSame(
                    'Only the inspect, plan, prepare, package, certify, and compatibility commands are available.',
                    $result['findings'][0]['message']
                );
                self::assertSame(['action' => 'run_supported_release_command'], $result['next_action']);
            }

            if ($ledger !== null) {
                self::assertFileDoesNotExist(substr($ledger, strlen('--ledger=')));
            }
        }
    }

    /**
     * Covers exact command grammars before fixture reads or artifact writes.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_invalid_command_arguments_fail_closed_before_any_effect_is_performed(): void
    {
        $root = dirname(__DIR__, 3);
        $fixture = '--fixture='.$root.'/release/fixtures/plan-candidate.json';
        $outputPath = $root.'/.runs/release-invalid-arguments-'.bin2hex(random_bytes(8));
        $output = '--output='.$outputPath;
        $ledgerPath = sys_get_temp_dir().'/release-invalid-ledger-'.bin2hex(random_bytes(8)).'.json';

        $cases = [
            ['inspect', [], 'release.inspect.fixture_required'],
            ['inspect', ['--fixture='], 'release.inspect.arguments_invalid'],
            ['inspect', [$fixture, $fixture], 'release.inspect.arguments_invalid'],
            ['inspect', [$fixture, '--output='.$outputPath], 'release.inspect.arguments_invalid'],
            ['inspect', [$fixture, '--unknown=value'], 'release.inspect.arguments_invalid'],
            ['inspect', [$fixture, 'positional'], 'release.inspect.arguments_invalid'],
            ['inspect', ['--fixture'], 'release.inspect.arguments_invalid'],
            ['plan', [], 'release.plan.inputs_required'],
            ['plan', [$fixture, '--output='], 'release.plan.arguments_invalid'],
            ['plan', [$fixture, $fixture, $output], 'release.plan.arguments_invalid'],
            ['plan', [$fixture, $output, $output], 'release.plan.arguments_invalid'],
            ['plan', [$fixture, $output, '--unknown=value'], 'release.plan.arguments_invalid'],
            ['plan', [$fixture, $output, 'positional'], 'release.plan.arguments_invalid'],
            ['plan', [$fixture, $output, '--ledger='.$ledgerPath], 'release.plan.ledger_unsupported'],
            ['inspect', [$fixture, '--ledger'], 'release.inspect.ledger_unsupported']
        ];

        foreach ($cases as [$command, $arguments, $findingId]) {
            $process = ReleaseProcess::create([$root.'/bin/release', $command, ...$arguments]);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $process->getExitCode(), $command.' '.implode(' ', $arguments));
            self::assertSame('fight-common.release-result/v1', $result['schema_version']);
            self::assertSame($command, $result['command']);
            self::assertSame(
                $command === 'inspect' ? 'release_inspection' : 'release_planning',
                $result['capability']
            );
            self::assertSame('policy_blocked', $result['status']);
            self::assertSame('invalid_input', $result['exit_class']);
            self::assertSame($findingId, $result['findings'][0]['id']);
            self::assertSame([], $result['performed_effects']);
            self::assertCount(1, $result['next_action']);
            self::assertSame([], $result['verified_postconditions']);
            self::assertSame([], $result['proposed_effects']);
            self::assertFileDoesNotExist($outputPath);
            self::assertFileDoesNotExist($ledgerPath);
        }
    }

    /**
     * Covers JSON object authority at the direct executable fixture seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_non_object_and_invalid_object_fixtures_stop_before_any_release_effect(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $root.'/.runs/release-fixture-shape-'.bin2hex(random_bytes(8));
        $output = $directory.'/artifacts';
        mkdir($directory, 0777, true);
        $fixtures = ['[]', '[{}]', '"string"', '42', 'null', '{}', '{'];

        try {
            foreach (['inspect', 'plan'] as $command) {
                foreach ($fixtures as $index => $contents) {
                    $fixture = $directory.'/'.$command.'-'.$index.'.json';
                    file_put_contents($fixture, $contents);
                    $arguments = [$root.'/bin/release', $command, '--fixture='.$fixture];

                    if ($command === 'plan') {
                        $arguments[] = '--output='.$output;
                    }

                    $process = ReleaseProcess::create($arguments);
                    $process->run();
                    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                    self::assertSame(2, $process->getExitCode(), $command.' '.$contents);
                    self::assertSame('fight-common.release-result/v1', $result['schema_version']);
                    self::assertSame($command, $result['command']);
                    self::assertCount(1, $result['findings']);
                    self::assertCount(1, $result['next_action']);
                    self::assertSame([], $result['performed_effects']);
                    self::assertSame([], $result['verified_postconditions']);
                    self::assertSame([], $result['proposed_effects']);
                    self::assertFileDoesNotExist($output);

                    if ($contents !== '{}') {
                        self::assertSame(
                            'release.'.$command.'.fixture_invalid',
                            $result['findings'][0]['id'],
                            $command.' '.$contents
                        );
                    }
                }
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $fixture) {
                unlink($fixture);
            }

            rmdir($directory);
        }
    }

    /**
     * Covers duplicate decoded object names at the direct inspect and plan seams.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_duplicate_fixture_members_stop_before_any_release_effect_or_artifact(): void // phpcs:ignore
    {
        $root = dirname(__DIR__, 3);
        $directory = $root.'/.runs/release-fixture-duplicates-'.bin2hex(random_bytes(8));
        $output = $directory.'/artifacts';
        mkdir($directory, 0777, true);
        $fixtures = [
            '{"approved_version":"1.2.3","approved_version":"1.2.4"}',
            '{"baseline":{"version":"1.2.3","version":"1.2.4"}}',
            '{"baseline":{"version":"1.2.3","ver\\u0073ion":"1.2.4"}}',
            '{"boundary":{"effect_class":"git.read","outcome":"success","outcome":"failure"}}'
        ];

        try {
            foreach (['inspect', 'plan'] as $command) {
                foreach ($fixtures as $index => $contents) {
                    $fixture = $directory.'/'.$command.'-'.$index.'.json';
                    file_put_contents($fixture, $contents);
                    $arguments = [$root.'/bin/release', $command, '--fixture='.$fixture];

                    if ($command === 'plan') {
                        $arguments[] = '--output='.$output;
                    }

                    $process = ReleaseProcess::create($arguments);
                    $process->run();
                    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                    self::assertSame(2, $process->getExitCode(), $command.' '.$contents);
                    self::assertSame('release.'.$command.'.fixture_invalid', $result['findings'][0]['id']);
                    self::assertSame([], $result['performed_effects']);
                    self::assertSame([], $result['verified_postconditions']);
                    self::assertSame([], $result['proposed_effects']);
                    self::assertCount(1, $result['next_action']);
                    self::assertFileDoesNotExist($output);
                }
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $fixture) {
                unlink($fixture);
            }

            rmdir($directory);
        }
    }

    /**
     * Covers invalid UTF-8 at the public command and option seam before any effect.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_invalid_utf8_arguments_fail_closed_with_encodable_results_before_any_effect(): void
    {
        $root = dirname(__DIR__, 3);
        $invalidByte = "\xFF";
        $invalidOutput = $root.'/.runs/release-invalid-utf8-'.$invalidByte;
        $cases = [
            [$invalidByte, [], 'unknown', 'release.command.encoding_invalid'],
            [
                'inspect',
                ['--fixture='.$root.'/release/fixtures/missing-'.$invalidByte.'.json'],
                'inspect',
                'release.inspect.arguments_encoding_invalid'
            ],
            [
                'plan',
                [
                    '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                    '--output='.$invalidOutput
                ],
                'plan',
                'release.plan.arguments_encoding_invalid'
            ]
        ];

        foreach ($cases as [$command, $arguments, $reportedCommand, $findingId]) {
            $process = ReleaseProcess::create([$root.'/bin/release', $command, ...$arguments]);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $process->getExitCode());
            self::assertSame('fight-common.release-result/v1', $result['schema_version']);
            self::assertSame($reportedCommand, $result['command']);
            self::assertSame($findingId, $result['findings'][0]['id']);
            self::assertSame([], $result['performed_effects']);
            self::assertCount(1, $result['next_action']);
            self::assertSame([], $result['verified_postconditions']);
            self::assertSame([], $result['proposed_effects']);
        }

        self::assertFileDoesNotExist($invalidOutput);
    }
}
