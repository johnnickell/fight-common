<?php

declare(strict_types=1);

/**
 * Reads and decodes one JSON object.
 *
 * @return array<string, mixed>
 */
function certification_json(string $path): array
{
    $bytes = file_get_contents($path);
    is_string($bytes) || throw new RuntimeException('Certification input is unreadable: '.$path);
    $value = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);

    return is_array($value) ? $value : throw new RuntimeException('Certification input must be an object.');
}

/**
 * Returns exact resolved package versions from one Composer lock.
 *
 * @return array{lock_sha256: string, packages: array<string, string>}
 */
function certification_versions(string $path): array
{
    $lockSha256 = hash_file('sha256', $path);
    is_string($lockSha256) || throw new RuntimeException('Composer lock digest is unavailable.');
    $lock = certification_json($path);
    $packages = [...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])];
    $versions = [];
    foreach ($packages as $package) {
        if (is_array($package) && is_string($package['name'] ?? null) && is_string($package['version'] ?? null)) {
            $versions[$package['name']] = $package['version'];
        }
    }

    ksort($versions, SORT_STRING);

    return ['lock_sha256' => $lockSha256, 'packages' => $versions];
}

/**
 * @return list<array<string, int|string>>
 */
function certification_commands(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    is_array($lines) || throw new RuntimeException('Command outcomes are unreadable.');
    $commands = [];
    foreach ($lines as $line) {
        $fields = explode("\t", $line);
        count($fields) === 6 || throw new RuntimeException('A command outcome is malformed.');
        $commands[] = [
            'name'          => $fields[0],
            'status'        => $fields[1],
            'exit_code'     => (int) $fields[2],
            'duration_ms'   => (int) $fields[3],
            'output_sha256' => $fields[4],
            'command'       => $fields[5]
        ];
    }

    return $commands;
}
