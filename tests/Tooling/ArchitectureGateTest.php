<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;

#[CoversNothing]
final class ArchitectureGateTest extends UnitTestCase
{
    public function test_that_ci_runs_latest_compatible_verification_through_the_shared_quality_gate(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root.'/.github/workflows/tests.yml');
        $contributing = file_get_contents($root.'/docs/contributing.md');

        self::assertIsString($workflow);
        self::assertIsString($contributing);

        self::assertHostedWorkflowContract($workflow);
        self::assertStringContainsString(
            'The default `./bin/build` installs the dependency versions recorded in `composer.lock`',
            $contributing,
        );
        self::assertStringContainsString(
            'Hosted CI runs `composer update` ephemerally and invokes `./bin/quality` directly on the runner',
            $contributing,
        );
    }

    #[DataProvider('invalid_hosted_workflow_provider')]
    public function test_that_ci_contract_rejects_non_propagating_duplicated_or_containerized_gates(
        string $search,
        string $replacement,
    ): void {
        $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml');

        self::assertIsString($workflow);
        $invalidWorkflow = str_replace($search, $replacement, $workflow, $replacementCount);
        self::assertSame(1, $replacementCount);

        $this->expectException(ExpectationFailedException::class);
        self::assertHostedWorkflowContract($invalidWorkflow);
    }

    public function test_that_ci_contract_ignores_commands_mentioned_only_in_comments(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml');

        self::assertIsString($workflow);
        $workflowWithComments = str_replace(
            '      - name: Run shared quality gate',
            "      # run: docker run --rm fight-common ./bin/quality\n"
                ."      # run: ./bin/phpcs\n"
                .'      - name: Run shared quality gate',
            $workflow,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);

        self::assertHostedWorkflowContract($workflowWithComments);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid_hosted_workflow_provider(): iterable
    {
        yield 'failure suppression' => [
            '          FIGHT_COMMON_POSTGRESQL_DSN: postgresql://fight_common:fight_common@127.0.0.1:5432/fight_common',
            "          FIGHT_COMMON_POSTGRESQL_DSN: postgresql://fight_common:fight_common@127.0.0.1:5432/fight_common\n        continue-on-error: true",
        ];
        yield 'duplicated alternate quality command' => [
            '          FIGHT_COMMON_POSTGRESQL_DSN: postgresql://fight_common:fight_common@127.0.0.1:5432/fight_common',
            "          FIGHT_COMMON_POSTGRESQL_DSN: postgresql://fight_common:fight_common@127.0.0.1:5432/fight_common\n\n      - name: Run coding standards again\n        run: ./bin/phpcs",
        ];
        yield 'containerized quality gate' => [
            '        run: ./bin/quality',
            '        run: docker run --rm fight-common ./bin/quality',
        ];
        yield 'literal block quality gate' => [
            '        run: ./bin/quality',
            "        run: |\n          ./bin/quality",
        ];
        yield 'folded block quality gate' => [
            '        run: ./bin/quality',
            "        run: >-\n          ./bin/quality",
        ];
    }

    private static function assertHostedWorkflowContract(string $workflow): void
    {
        self::assertSame(
            1,
            preg_match(
                '/^  pull_request:\s*$\R^    branches:\s*\[(?<branches>[^]]*)]\s*$/m',
                $workflow,
                $branchMatch,
            ),
        );
        self::assertSame(
            ['main', 'develop', '1.*', 'release/*'],
            array_map(
                static fn (string $branch): string => trim(trim($branch), "'\""),
                explode(',', $branchMatch['branches']),
            ),
        );

        self::assertSame(
            [
                [
                    'name' => 'Resolve latest-compatible dependencies',
                    'run' => 'composer update --no-interaction --no-progress --prefer-dist',
                    'env' => null,
                ],
                [
                    'name' => 'Run shared quality gate',
                    'run' => './bin/quality',
                    'env' => [
                        'FIGHT_COMMON_MYSQL_DSN' =>
                            'mysql://fight_common:fight_common@127.0.0.1:3306/fight_common',
                        'FIGHT_COMMON_POSTGRESQL_DSN' =>
                            'postgresql://fight_common:fight_common@127.0.0.1:5432/fight_common',
                    ],
                ],
            ],
            self::extractExecutableTestSteps($workflow),
        );
    }

    /**
     * @return list<array{name: ?string, run: string, env: ?array<string, string>}>
     */
    private static function extractExecutableTestSteps(string $workflow): array
    {
        self::assertSame(
            1,
            preg_match('/^  test:\s*$\R(?<job>.*?)(?=^  [a-zA-Z0-9_-]+:\s*$|\z)/ms', $workflow, $jobMatch),
        );
        $job = $jobMatch['job'];

        self::assertDoesNotMatchRegularExpression(
            '/^(?:    continue-on-error|      - continue-on-error|        continue-on-error)\s*:/m',
            $job,
        );

        $lines = preg_split('/\R/', $job);
        self::assertIsArray($lines);
        $inSteps = false;
        $currentStep = null;
        $executableSteps = [];

        foreach ($lines as $line) {
            if (!$inSteps) {
                $inSteps = preg_match('/^    steps:\s*(?:#.*)?$/', $line) === 1;

                continue;
            }

            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/^      -(?:\s+(?<field>.*))?$/', $line, $stepMatch) === 1) {
                if ($currentStep !== null) {
                    self::appendExecutableStep($currentStep, $executableSteps);
                }

                $currentStep = ['name' => null, 'runs' => [], 'env' => []];
                self::readStepField($stepMatch['field'] ?? '', $currentStep);

                continue;
            }

            if ($currentStep === null) {
                continue;
            }

            if (preg_match('/^        (?<field>name|run|env):\s*(?<value>.*)$/', $line, $fieldMatch) === 1) {
                self::readStepField($fieldMatch['field'].': '.$fieldMatch['value'], $currentStep);

                continue;
            }

            if (preg_match('/^          (?<key>[A-Z][A-Z0-9_]*):\s*(?<value>.+)$/', $line, $envMatch) === 1) {
                $currentStep['env'][$envMatch['key']] = trim($envMatch['value']);
            }
        }

        self::assertTrue($inSteps);

        if ($currentStep !== null) {
            self::appendExecutableStep($currentStep, $executableSteps);
        }

        return $executableSteps;
    }

    /**
     * @param array{name: ?string, runs: list<string>, env: array<string, string>} $step
     */
    private static function readStepField(string $field, array &$step): void
    {
        if (preg_match('/^name:\s*(?<value>.*)$/', $field, $match) === 1) {
            $step['name'] = trim($match['value']);

            return;
        }

        if (preg_match('/^run:\s*(?<value>.*)$/', $field, $match) !== 1) {
            return;
        }

        $run = trim($match['value']);
        self::assertDoesNotMatchRegularExpression('/^[>|]/', $run);
        $step['runs'][] = $run;
    }

    /**
     * @param array{name: ?string, runs: list<string>, env: array<string, string>} $step
     * @param list<array{name: ?string, run: string, env: ?array<string, string>}> $executableSteps
     */
    private static function appendExecutableStep(array $step, array &$executableSteps): void
    {
        foreach ($step['runs'] as $run) {
            $executableSteps[] = [
                'name' => $step['name'],
                'run' => $run,
                'env' => $step['env'] === [] ? null : $step['env'],
            ];
        }
    }

    public function test_that_documented_architecture_gates_delegate_to_executables_that_reject_unassigned_classes(): void
    {
        $root = dirname(__DIR__, 2);
        $wrapper = file_get_contents($root.'/bin/deptrac');
        $instructions = file_get_contents($root.'/CLAUDE.md');
        $qualityGate = file_get_contents($root.'/bin/quality');

        self::assertIsString($wrapper);
        self::assertIsString($instructions);
        self::assertIsString($qualityGate);
        self::assertStringContainsString(
            'fight-common php vendor/bin/deptrac debug:unassigned --no-cache',
            $wrapper,
        );
        self::assertStringContainsString(
            '`./bin/quality` is the single host-neutral ordered gate',
            $instructions,
        );
        self::assertStringContainsString(
            'php vendor/bin/deptrac --fail-on-uncovered --report-uncovered --report-skipped',
            $qualityGate,
        );
        self::assertStringContainsString(
            'php vendor/bin/deptrac debug:unassigned --no-cache',
            $qualityGate,
        );
    }
}
