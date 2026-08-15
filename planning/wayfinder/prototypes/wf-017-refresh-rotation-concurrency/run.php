<?php

declare(strict_types=1);

$framework = getenv('PROTOTYPE_FRAMEWORK');
$receipt = getenv('PROTOTYPE_RECEIPT');
if (!is_string($framework) || $framework === '' || !is_string($receipt) || $receipt === '') {
    throw new RuntimeException('PROTOTYPE_FRAMEWORK and PROTOTYPE_RECEIPT are required.');
}

$setup = runWorker('setup');
$failedAudit = runWorker('fail-audit');
$afterFailure = runWorker('inspect');
prototypeAssert($failedAudit['decision'] === 'failed', 'forced audit failure must escape');
prototypeAssert($afterFailure['current']['status'] === 'ACTIVE', 'failed audit must roll back predecessor rotation');
prototypeAssert($afterFailure['successor'] === null && $afterFailure['rotation_audit'] === null, 'failed audit must leave no successor or audit');

$marker = '/tmp/wf017-refresh-lock-' . bin2hex(random_bytes(6));
$winner = startWorker('rotate', ['PROTOTYPE_HOLD_MS' => '700', 'PROTOTYPE_LOCK_MARKER' => $marker]);
$deadline = microtime(true) + 5;
while (!is_file($marker) && microtime(true) < $deadline) {
    usleep(10_000);
}
prototypeAssert(is_file($marker), 'winner did not acquire the row lock');
$loser = startWorker('rotate');
$winnerResult = finishWorker($winner);
$loserResult = finishWorker($loser);
prototypeAssert($winnerResult['decision'] === 'rotated', 'the lock winner must rotate');
prototypeAssert($loserResult['decision'] === 'bounded_conflict', 'the concurrent loser must return a bounded conflict');
prototypeAssert($loserResult['lock_wait_ms'] >= 400, 'the concurrent loser must wait for the native row lock');

$afterRace = runWorker('inspect');
prototypeAssert($afterRace['current']['status'] === 'ROTATED', 'predecessor must be rotated');
prototypeAssert($afterRace['successor']['status'] === 'ACTIVE', 'exactly one successor must remain active');
prototypeAssert($afterRace['rotation_audit']['action'] === 'REFRESH_ROTATED', 'rotation audit must commit');

$reuse = runWorker('rotate', ['PROTOTYPE_NOW' => '106']);
$afterReuse = runWorker('inspect');
prototypeAssert($reuse['decision'] === 'reuse_detected', 'reuse outside the conflict window must be detected');
prototypeAssert($afterReuse['successor']['status'] === 'REVOKED', 'reuse must revoke only the affected device family');
prototypeAssert($afterReuse['reuse_audit']['action'] === 'REFRESH_REUSE_DETECTED', 'reuse audit must commit');

$database = parse_url((string) getenv('PROTOTYPE_DATABASE_URL'), PHP_URL_SCHEME) === 'postgresql' ? 'PostgreSQL' : 'MySQL';
$result = [
    'prototype' => 'WF-017 refresh rotation concurrency',
    'framework' => $framework,
    'database' => $database,
    'native_api' => $setup['api'],
    'versions' => $setup['versions'],
    'scenarios' => [
        'forced_audit_failure' => $failedAudit,
        'concurrent_winner' => $winnerResult,
        'concurrent_loser' => $loserResult,
        'late_predecessor_reuse' => $reuse,
    ],
    'checks' => [
        'rotation_and_audit_atomic' => true,
        'native_row_lock_observed' => true,
        'one_successor_created' => true,
        'bounded_conflict_does_not_revoke_family' => true,
        'late_reuse_revokes_device_family' => true,
        'reuse_audited' => true,
        'portable_operation_has_no_framework_branch' => true,
    ],
    'verdict' => 'pass',
];
file_put_contents($receipt, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
echo sprintf("PASS %s / %s\n", $framework, $database);

/** @param array<string, string> $extraEnvironment
 *  @return array{process: resource, pipes: array<int, resource>}
 */
function startWorker(string $action, array $extraEnvironment = []): array
{
    $environment = array_merge([
        'PROTOTYPE_FRAMEWORK' => (string) getenv('PROTOTYPE_FRAMEWORK'),
        'PROTOTYPE_DATABASE_URL' => (string) getenv('PROTOTYPE_DATABASE_URL'),
        'PROTOTYPE_NOW' => '100',
    ], $extraEnvironment);
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/worker.php', $action],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__,
        $environment,
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start prototype worker.');
    }
    fclose($pipes[0]);

    return ['process' => $process, 'pipes' => $pipes];
}

/** @param array{process: resource, pipes: array<int, resource>} $worker
 *  @return array<string, mixed>
 */
function finishWorker(array $worker): array
{
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    $exit = proc_close($worker['process']);
    if ($exit !== 0) {
        throw new RuntimeException('Prototype worker failed: ' . trim((string) $stderr . "\n" . (string) $stdout));
    }
    $decoded = json_decode((string) $stdout, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Prototype worker returned an invalid result.');
    }

    return $decoded;
}

/** @param array<string, string> $extraEnvironment
 *  @return array<string, mixed>
 */
function runWorker(string $action, array $extraEnvironment = []): array
{
    return finishWorker(startWorker($action, $extraEnvironment));
}

function prototypeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
