<?php

declare(strict_types=1);

const TOPIC = 'https://starter.example.test/topics/access/users';
const EVENT = '{"event":"access.users.invalidated","schema_version":1}';

/** @param array<string, mixed> $header @param array<string, mixed> $claims */
function jwt(array $header, array $claims, string $secret): string
{
    $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode(
        $value,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
    )), '+/', '-_'), '=');
    $unsigned = $encode($header) . '.' . $encode($claims);

    return $unsigned . '.' . rtrim(strtr(base64_encode(hash_hmac('sha256', $unsigned, $secret, true)), '+/', '-_'), '=');
}

/** @return array{status: int, body: string, stderr: string} */
function request(array $arguments): array
{
    $command = array_merge(['curl', '--silent', '--show-error', '--write-out', "\n%{http_code}"], $arguments);
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start curl.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $separator = strrpos($stdout, "\n");
    if ($separator === false) {
        throw new RuntimeException('Curl did not emit an HTTP status.');
    }

    return [
        'status' => (int) substr($stdout, $separator + 1),
        'body' => substr($stdout, 0, $separator),
        'stderr' => trim($stderr),
    ];
}

/** @return array{output: string, stderr: string, exit_code: int} */
function subscribe(string $url, string $token, Closure $publish): array
{
    $pipes = [];
    $process = proc_open([
        'curl', '--silent', '--show-error', '--no-buffer', '--max-time', '2.5',
        '--header', 'Authorization: Bearer ' . $token,
        $url,
    ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start subscriber.');
    }

    usleep(400_000);
    $publish();
    $output = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['output' => $output, 'stderr' => trim($stderr), 'exit_code' => proc_close($process)];
}

[$script, $hubUrl, $secret, $imageDigest, $version, $protocol] = $argv + [null, null, null, null, null, null];
if ($hubUrl === null || $secret === null || $imageDigest === null || $version === null || $protocol === null) {
    throw new InvalidArgumentException('Usage: php probe.php HUB_URL SECRET IMAGE_DIGEST VERSION PROTOCOL');
}
if (!in_array($protocol, ['legacy', 'modern'], true)) {
    throw new InvalidArgumentException('Protocol must be legacy or modern.');
}

$now = time();
$legacy = jwt(['alg' => 'HS256', 'typ' => 'JWT'], [
    'iat' => $now,
    'exp' => $now + 300,
    'mercure' => ['publish' => [TOPIC], 'subscribe' => [TOPIC]],
], $secret);
$modern = jwt(['alg' => 'HS256', 'typ' => 'at+jwt'], [
    'iss' => 'https://localhost',
    'aud' => $hubUrl,
    'sub' => 'prototype-admin',
    'client_id' => 'wf-017-prototype',
    'iat' => $now,
    'exp' => $now + 300,
    'jti' => bin2hex(random_bytes(12)),
    'authorization_details' => [[
        'type' => 'https://mercure.rocks/authorization-detail',
        'actions' => ['publish', 'subscribe'],
        'topics' => [['match' => TOPIC]],
    ]],
], $secret);

$publish = static function (string $token) use ($hubUrl): array {
    return request([
        '--request', 'POST',
        '--header', 'Authorization: Bearer ' . $token,
        '--data-urlencode', 'topic=' . TOPIC,
        '--data-urlencode', 'data=' . EVENT,
        '--data', 'private=on',
        $hubUrl,
    ]);
};

$legacyPublish = null;
$legacySubscription = subscribe(
    $hubUrl . '?topic=' . rawurlencode(TOPIC),
    $legacy,
    static function () use (&$legacyPublish, $publish, $legacy): void {
        $legacyPublish = $publish($legacy);
    },
);
$modernPublish = null;
$modernSubscription = subscribe(
    $hubUrl . '?match=' . rawurlencode(TOPIC),
    $modern,
    static function () use (&$modernPublish, $publish, $modern): void {
        $modernPublish = $publish($modern);
    },
);
if ($legacyPublish === null || $modernPublish === null) {
    throw new RuntimeException('A publish comparison did not execute.');
}

$legacyReceived = str_contains($legacySubscription['output'], EVENT);
$modernReceived = str_contains($modernSubscription['output'], EVENT);
$normalizeBody = static function (string $body): string {
    return str_starts_with(trim($body), 'urn:uuid:') ? 'urn:uuid:<generated>' : $body;
};
$checks = $protocol === 'legacy' ? [
    'legacy_private_publish_accepted' => $legacyPublish['status'] < 300,
    'legacy_private_subscription_received' => $legacyReceived,
    'modern_private_publish_rejected' => $modernPublish['status'] >= 400,
    'modern_private_subscription_not_received' => !$modernReceived,
] : [
    'modern_private_publish_accepted' => $modernPublish['status'] < 300,
    'modern_private_subscription_received' => $modernReceived,
    'legacy_private_publish_rejected' => $legacyPublish['status'] >= 400,
    'legacy_private_subscription_not_received' => !$legacyReceived,
];
$passed = !in_array(false, $checks, true);

$receipt = [
    'prototype' => 'WF-017 Mercure protocol version',
    'observed_on' => '2026-08-14',
    'hub' => [
        'version' => $version,
        'image' => $imageDigest,
        'release' => 'https://github.com/dunglas/mercure/releases/tag/' . $version,
        'stability' => $protocol === 'legacy' ? 'stable' : 'testing-only prerelease',
        'compatibility_mode' => false,
    ],
    'upgrade_guide' => 'https://mercure.rocks/docs/UPGRADE',
    'topic' => TOPIC,
    'legacy_0x' => [
        'credential' => 'mercure.publish / mercure.subscribe',
        'subscription_parameter' => 'topic',
        'private_publish_status' => $legacyPublish['status'],
        'private_publish_body' => $normalizeBody($legacyPublish['body']),
        'private_subscription_received' => $legacyReceived,
    ],
    'modern_1_0' => [
        'credential' => 'OAuth 2 authorization_details',
        'subscription_parameter' => 'match',
        'private_publish_status' => $modernPublish['status'],
        'private_publish_body' => $normalizeBody($modernPublish['body']),
        'private_subscription_received' => $modernReceived,
    ],
    'checks' => $checks,
    'verdict' => $passed
        ? sprintf('PASS: %s accepts only the expected %s protocol shape', $version, $protocol)
        : sprintf('FAIL: %s protocol comparison was inconclusive', $version),
];

$receiptPath = __DIR__ . '/receipts/mercure-' . $version . '.json';
file_put_contents($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

foreach ($checks as $name => $result) {
    printf("%-44s %s\n", $name, $result ? 'PASS' : 'FAIL');
}
printf("%-44s %s\n", 'receipt', $receiptPath);

exit($passed ? 0 : 1);
