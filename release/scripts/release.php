<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
require __DIR__.'/../../vendor/autoload.php';

use Fight\Release\Adapter\ArtifactReleasePlanAuthority;
use Fight\Release\Adapter\CryptographicRunIdGenerator;
use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Adapter\LocalGitPort;
use Fight\Release\Adapter\ReleaseFixtureLoader;
use Fight\Release\Application\Boundary\ReleaseBoundaryCrash;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffect;
use Fight\Release\Application\Boundary\ReleaseRuntimeTermination;
use Fight\Release\Application\CanonicalJson;
use Fight\Release\Application\MachineResult;
use Fight\Release\Application\ReleaseInspectionService;
use Fight\Release\Application\ReleasePlanCapabilityFirewall;
use Fight\Release\Application\ReleasePlanFactory;
use Fight\Release\Application\ReleasePlanService;
use Fight\Release\Application\ReleasePreparationService;
use Fight\Release\Application\ReleaseResultFactory;
use Fight\Release\Application\Utf8Validator;

/**
 * Dispatches an application machine result to the stable subprocess seam.
 *
 * @return never
 */
function dispatch_release_result(MachineResult $result): never
{
    try {
        if (!MachineResult::isValidPayload($result->payload, $result->exitCode)) {
            throw new RuntimeException('The release result contract is invalid.');
        }

        $output = json_encode($result->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
        authenticate_release_result($output, $result->exitCode);
        echo $output;
    } catch (Throwable $throwable) {
        fwrite(STDERR, 'release result dispatch failed: '.$throwable->getMessage().PHP_EOL);
        exit(70);
    }

    exit($result->exitCode);
}

/**
 * Writes all bytes to one exclusively created private runtime file.
 */
function write_release_runtime_file(string $path, string $contents): bool
{
    $stream = @fopen($path, 'x');

    if (!is_resource($stream)) {
        return false;
    }

    $offset = 0;
    $length = strlen($contents);

    while ($offset < $length) {
        $written = fwrite($stream, substr($contents, $offset));

        if (!is_int($written) || $written < 1) {
            fclose($stream);

            return false;
        }

        $offset += $written;
    }

    $flushed = fflush($stream);
    $closed = fclose($stream);

    return $flushed && $closed;
}

/**
 * Authenticates one normal machine result before any result bytes reach stdout.
 */
function authenticate_release_result(string $output, int $exitCode): void
{
    $evidencePath = getenv('FIGHT_COMMON_RELEASE_RESULT_EVIDENCE');
    $outputPath = getenv('FIGHT_COMMON_RELEASE_RESULT_OUTPUT');
    $nonce = getenv('FIGHT_COMMON_RELEASE_RESULT_NONCE');
    $testRuntime = getenv('FIGHT_COMMON_RELEASE_TEST_RUNTIME');

    if (
        $testRuntime === 'fight-common-release-direct-test-v1'
        && $evidencePath === false
        && $outputPath === false
        && $nonce === false
    ) {
        return;
    }

    if (
        !is_string($evidencePath)
        || $evidencePath === ''
        || !is_string($outputPath)
        || $outputPath === ''
        || !is_string($nonce)
        || preg_match('/\A[0-9a-f]{64}\z/D', $nonce) !== 1
    ) {
        throw new RuntimeException('The private release result channel is unavailable.');
    }

    if (!write_release_runtime_file($outputPath, $output)) {
        throw new RuntimeException('The private release result copy could not be persisted.');
    }

    $evidence = $nonce.PHP_EOL.$exitCode.PHP_EOL.hash('sha256', $output).PHP_EOL;

    if (!write_release_runtime_file($evidencePath, $evidence)) {
        throw new RuntimeException('The private release result evidence could not be persisted.');
    }
}

/**
 * Parses an exact command option grammar.
 *
 * @phpstan-param list<string> $arguments
 * @phpstan-param list<string> $allowed
 *
 * @return array<string, string>|null
 */
function release_options(array $arguments, array $allowed): ?array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (preg_match('/\A--([a-z]+)=(.+)\z/D', $argument, $matches) !== 1) {
            return null;
        }

        $name = $matches[1];

        if (!in_array($name, $allowed, true) || array_key_exists($name, $options)) {
            return null;
        }

        $options[$name] = $matches[2];
    }

    return $options;
}

/**
 * Reports whether the deprecated external-ledger option was supplied.
 *
 * @phpstan-param list<string> $arguments
 */
function release_requests_ledger(array $arguments): bool
{
    return array_any(
        $arguments,
        static fn (string $argument): bool => $argument === '--ledger' || str_starts_with($argument, '--ledger=')
    );
}

/**
 * Applies an optional credential-free Git-resolution control and removes it from release authority.
 *
 * @param DeterministicReleaseBoundaryFake $ports     Deterministic outer boundary.
 * @param array<string, mixed> $candidate Candidate fixture authority and optional fake control.
 */
function configure_release_git(
    DeterministicReleaseBoundaryFake $ports,
    array &$candidate
): bool {
    $configuration = $candidate['git_resolution'] ?? null;
    unset($candidate['git_resolution']);

    if ($configuration === null) {
        return true;
    }

    if (!is_array($configuration) || !is_string($configuration['status'] ?? null)) {
        return false;
    }

    foreach (['tag_name', 'tag_object_oid', 'peeled_commit_oid'] as $field) {
        if (isset($configuration[$field]) && !is_string($configuration[$field])) {
            return false;
        }
    }

    return $ports->configureBaselineTagResolution(
        $configuration['status'],
        $configuration['tag_name'] ?? '',
        $configuration['tag_object_oid'] ?? 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
        $configuration['peeled_commit_oid'] ?? 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45'
    );
}

/**
 * Applies credential-free preparation controls that never become release authority.
 *
 * @param DeterministicReleaseBoundaryFake $ports         Credential-free release boundaries.
 * @param array<string, mixed> $configuration Controlled preparation fixture.
 */
function configure_release_preparation(
    DeterministicReleaseBoundaryFake $ports,
    array $configuration
): bool {
    if (
        array_diff(
            array_keys($configuration),
            [
                'plan_authority_status',
                'git_resolution_outcome',
                'interrupt_before_run_binding_once',
                'interrupt_run_projection_once',
                'interrupt_finalized_run_projection_once',
                'run_state_helper_protocol_termination_once',
                'artifact_write_outcome'
            ]
        ) !== []
    ) {
        return false;
    }

    if (isset($configuration['artifact_write_outcome'])) {
        if (
            !is_string($configuration['artifact_write_outcome'])
            || !$ports->configureOutcome('filesystem.write', $configuration['artifact_write_outcome'])
        ) {
            return false;
        }
    }

    if (isset($configuration['plan_authority_status'])) {
        if (
            !is_string($configuration['plan_authority_status'])
            || !$ports->configurePlanAuthorityStatus($configuration['plan_authority_status'])
        ) {
            return false;
        }
    }

    if (isset($configuration['git_resolution_outcome'])) {
        if (
            !is_string($configuration['git_resolution_outcome'])
            || !$ports->configureOutcome('git.resolve_ref', $configuration['git_resolution_outcome'])
        ) {
            return false;
        }
    }

    if (isset($configuration['interrupt_run_projection_once'])) {
        if ($configuration['interrupt_run_projection_once'] !== true) {
            return false;
        }

        $ports->interruptRunProjectionOnce();
    }

    if (isset($configuration['interrupt_before_run_binding_once'])) {
        if ($configuration['interrupt_before_run_binding_once'] !== true) {
            return false;
        }

        $ports->interruptBeforeRunBindingOnce();
    }

    if (isset($configuration['interrupt_finalized_run_projection_once'])) {
        if ($configuration['interrupt_finalized_run_projection_once'] !== true) {
            return false;
        }

        $ports->interruptFinalizedRunProjectionOnce();
    }

    if (isset($configuration['run_state_helper_protocol_termination_once'])) {
        if ($configuration['run_state_helper_protocol_termination_once'] !== true) {
            return false;
        }

        $ports->terminateRunStateHelperOnce();
    }

    return true;
}

/**
 * Authenticates a deliberately configured crash to the repository wrapper.
 */
function mark_configured_release_crash(ReleaseBoundaryCrash $crash): void
{
    $markerPath = getenv('FIGHT_COMMON_RELEASE_CRASH_MARKER');
    $nonce = getenv('FIGHT_COMMON_RELEASE_CRASH_NONCE');

    if (is_string($markerPath) && $markerPath !== '' && is_string($nonce) && $nonce !== '') {
        $marker = fopen($markerPath, 'x');

        if (is_resource($marker)) {
            $payload = $nonce.PHP_EOL;
            $written = fwrite($marker, $payload);

            if ($written === strlen($payload)) {
                fflush($marker);
            }

            fclose($marker);
        }
    }

    fwrite(STDERR, $crash::class.': '.$crash->getMessage().PHP_EOL);
}

try {
/** @var list<string> $commandLine */
    $commandLine = $_SERVER['argv'] ?? [];
    $command = $commandLine[1] ?? 'unknown';
    $arguments = array_slice($commandLine, 2);
    $ports = new DeterministicReleaseBoundaryFake();
    $results = new ReleaseResultFactory($ports);
    $utf8 = new Utf8Validator();
    $fixtures = new ReleaseFixtureLoader($utf8);
    $repositoryRoot = dirname(__DIR__, 2);
    $testRepositoryRoot = getenv('FIGHT_COMMON_RELEASE_TEST_REPOSITORY');

    if (
        getenv('FIGHT_COMMON_RELEASE_TEST_RUNTIME') === 'fight-common-release-direct-test-v1'
        && is_string($testRepositoryRoot)
        && $testRepositoryRoot !== ''
    ) {
        $repositoryRoot = $testRepositoryRoot;
    }

    if (!$utf8->isValid($command)) {
        dispatch_release_result($results->failure(
            'unknown',
            'release.command.encoding_invalid',
            'The release command must be valid UTF-8.',
            'provide_valid_utf8_command',
            []
        ));
    }

    if (!$utf8->isValid($arguments)) {
        dispatch_release_result($results->failure(
            $command,
            'release.'.$command.'.arguments_encoding_invalid',
            'Release command options must be valid UTF-8.',
            'provide_valid_utf8_arguments',
            []
        ));
    }

    if (!in_array($command, ['inspect', 'plan', 'prepare'], true)) {
        dispatch_release_result($results->failure(
            $command,
            'release.command.unsupported',
            'Only the inspect, plan, and prepare commands are available.',
            'run_supported_release_command'
        ));
    }

    if (release_requests_ledger($arguments)) {
        dispatch_release_result($results->failure(
            $command,
            'release.'.$command.'.ledger_unsupported',
            'The command exposes its in-memory boundary ledger in the result and does not write ledger artifacts.',
            'read_performed_effects'
        ));
    }

    if ($command === 'plan') {
        $options = release_options($arguments, ['fixture', 'output']);

        if ($options === null) {
            dispatch_release_result($results->failure(
                'plan',
                'release.plan.arguments_invalid',
                'Planning accepts only one non-empty fixture and one non-empty output option.',
                'provide_valid_plan_arguments'
            ));
        }

        if (!isset($options['fixture'], $options['output'])) {
            dispatch_release_result($results->failure(
                'plan',
                'release.plan.inputs_required',
                'Planning requires exactly one fixture and one output directory.',
                'provide_plan_inputs'
            ));
        }

        $fixture = $fixtures->load($options['fixture']);

        if ($fixture->status === 'unreadable') {
            dispatch_release_result($results->failure(
                'plan',
                'release.plan.fixture_unreadable',
                'The planning fixture could not be resolved.',
                'provide_readable_plan_fixture',
                []
            ));
        }

        if ($fixture->status === 'encoding_invalid') {
            dispatch_release_result($results->failure(
                'plan',
                'release.plan.fixture_encoding_invalid',
                'The planning fixture must contain only valid UTF-8 strings.',
                'provide_valid_utf8_plan_fixture',
                []
            ));
        }

        if ($fixture->status === 'invalid') {
            dispatch_release_result($results->failure(
                'plan',
                'release.plan.fixture_invalid',
                'The planning fixture must contain one JSON object.',
                'provide_valid_plan_fixture',
                []
            ));
        }

        /** @var array<string, mixed> $candidate */
        $candidate = $fixture->candidate;
        $capabilityStop = new ReleasePlanCapabilityFirewall($results)->inspect($candidate);

        if ($capabilityStop instanceof MachineResult) {
            dispatch_release_result($capabilityStop);
        }

        if (!configure_release_git($ports, $candidate)) {
            dispatch_release_result($results->failure(
                'plan',
                'release.boundary.fixture_invalid',
                'The deterministic Git-resolution fixture is malformed.',
                'correct_boundary_fixture',
                []
            ));
        }

        $service = new ReleasePlanService(
            $ports,
            $ports,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            $results
        );
        dispatch_release_result($service->plan($candidate, $options['output'], $repositoryRoot.'/.runs'));
    }

    if ($command === 'prepare') {
        $options = release_options($arguments, ['authority', 'fixture', 'plan', 'resume']);

        if ($options === null || !isset($options['plan'])) {
            dispatch_release_result($results->failure(
                'prepare',
                'release.prepare.inputs_required',
                'Preparation requires exactly one immutable plan option.',
                'provide_prepare_plan'
            ));
        }

        $testFixture = isset($options['fixture']);

        if (
            $testFixture
            && getenv('FIGHT_COMMON_RELEASE_TEST_RUNTIME') !== 'fight-common-release-direct-test-v1'
        ) {
            dispatch_release_result($results->failure(
                'prepare',
                'release.prepare.fixture_forbidden',
                'Preparation fixtures are available only in the explicit direct-test runtime.',
                'remove_prepare_fixture'
            ));
        }

        if ($testFixture) {
            $fixture = $fixtures->load($options['fixture']);

            if (
                $fixture->status !== 'valid'
                || !is_array($fixture->candidate)
                || !configure_release_preparation($ports, $fixture->candidate)
            ) {
                dispatch_release_result($results->failure(
                    'prepare',
                    'release.prepare.fixture_invalid',
                    'The controlled preparation fixture is invalid.',
                    'provide_valid_prepare_fixture',
                    []
                ));
            }
        }

        if (!$testFixture && !isset($options['authority'])) {
            dispatch_release_result($results->failure(
                'prepare',
                'release.prepare.authority_required',
                'Normal preparation requires one current release-plan authority artifact.',
                'provide_current_release_plan_authority'
            ));
        }

        $record = static function (
            ReleaseEffect $effect,
            ReleaseBoundaryOutcome $outcome
        ) use ($ports): void {
            $ports->recordObservedEffect($effect, $outcome);
        };
        $git = $testFixture ? $ports : new LocalGitPort($repositoryRoot, $record);
        $authority = $testFixture ? $ports : new ArtifactReleasePlanAuthority(
            $ports,
            $options['authority'],
            $repositoryRoot.'/.runs',
            $record
        );

        $service = new ReleasePreparationService(
            $ports,
            $ports,
            $authority,
            new CryptographicRunIdGenerator(),
            $git,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            $results
        );
        dispatch_release_result($service->prepare(
            $options['plan'],
            $repositoryRoot.'/.runs',
            $options['resume'] ?? null
        ));
    }

    $options = release_options($arguments, ['fixture']);

    if ($options === null) {
        dispatch_release_result($results->failure(
            'inspect',
            'release.inspect.arguments_invalid',
            'Inspection accepts only one non-empty fixture option.',
            'provide_valid_inspection_arguments'
        ));
    }

    if (!isset($options['fixture'])) {
        dispatch_release_result($results->failure(
            'inspect',
            'release.inspect.fixture_required',
            'Inspection requires exactly one fixture.',
            'provide_inspection_fixture'
        ));
    }

    $fixture = $fixtures->load($options['fixture']);

    if ($fixture->status === 'unreadable') {
        dispatch_release_result($results->failure(
            'inspect',
            'release.inspect.fixture_unreadable',
            'The inspection fixture could not be resolved.',
            'provide_readable_inspection_fixture',
            []
        ));
    }

    if ($fixture->status === 'encoding_invalid') {
        dispatch_release_result($results->failure(
            'inspect',
            'release.inspect.fixture_encoding_invalid',
            'The inspection fixture must contain only valid UTF-8 strings.',
            'provide_valid_utf8_inspection_fixture',
            []
        ));
    }

    if ($fixture->status === 'invalid') {
        dispatch_release_result($results->failure(
            'inspect',
            'release.inspect.fixture_invalid',
            'The inspection fixture must contain one JSON object.',
            'provide_valid_inspection_fixture',
            []
        ));
    }

    /** @var array<string, mixed> $candidate */
    $candidate = $fixture->candidate;
    $inspection = new ReleaseInspectionService($results);
    $capabilityStop = $inspection->preflight($candidate);

    if ($capabilityStop instanceof MachineResult) {
        dispatch_release_result($capabilityStop);
    }

    if (!configure_release_git($ports, $candidate)) {
        dispatch_release_result($results->failure(
            'inspect',
            'release.boundary.fixture_invalid',
            'The deterministic Git-resolution fixture is malformed.',
            'correct_boundary_fixture',
            []
        ));
    }

    dispatch_release_result($inspection->inspect($candidate, $ports));
} catch (ReleaseBoundaryCrash $releaseBoundaryCrash) {
    mark_configured_release_crash($releaseBoundaryCrash);
    exit(86);
} catch (ReleaseRuntimeTermination) {
    dispatch_release_result($results->runtimeTermination($command));
}
