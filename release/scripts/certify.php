<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
require __DIR__.'/../../vendor/autoload.php';

use Fight\Release\Adapter\PackageSurfaceInspector;
use Fight\Release\Application\CertificationRecord;

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

try {
    $arguments = $_SERVER['argv'] ?? [];
    $command = $arguments[1] ?? '';
    if ($command === 'surface' && count($arguments) === 5) {
        $result = new PackageSurfaceInspector()->inspect($arguments[2], $arguments[3], $arguments[4]);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }

    if ($command === 'consumer-manifest' && count($arguments) === 5) {
        $manifest = [
            'name'          => 'fight/release-certification-consumer',
            'type'          => 'project',
            'require'       => [
                'php'                      => '>=8.5',
                'johnnickell/fight-common' => $arguments[4]
            ],
            'repositories'  => [['type' => 'artifact', 'url' => $arguments[3]]],
            'config'        => ['allow-plugins' => false],
            'prefer-stable' => true
        ];
        file_put_contents(
            $arguments[2],
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
        ) !== false || throw new RuntimeException('The consumer manifest could not be written.');
        exit(0);
    }

    if ($command === 'archive-manifest' && count($arguments) === 4) {
        $manifest = certification_json($arguments[2]);
        $manifest['version'] = $arguments[3];
        file_put_contents(
            $arguments[2],
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
        ) !== false || throw new RuntimeException('The archive manifest could not be written.');
        exit(0);
    }

    if ($command === 'record' && count($arguments) === 13) {
        $archiveSha256 = hash_file('sha256', $arguments[4]);
        is_string($archiveSha256) || throw new RuntimeException('The archive digest is unavailable.');
        $starterReferences = certification_json($arguments[11]);
        $record = new CertificationRecord()->create(
            $arguments[2],
            $arguments[3],
            basename($arguments[4]),
            $archiveSha256,
            certification_commands($arguments[5]),
            [
                'default' => certification_versions($arguments[6]),
                'latest'  => certification_versions($arguments[7]),
                'lowest'  => certification_versions($arguments[8])
            ],
            certification_json($arguments[9]),
            certification_json($arguments[10]),
            $starterReferences['receipts'] ?? []
        );
        file_put_contents(
            $arguments[12],
            json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
        ) !== false || throw new RuntimeException('The certification record could not be written.');
        exit(0);
    }

    throw new RuntimeException('Unsupported certification helper invocation.');
} catch (Throwable $throwable) {
    fwrite(STDERR, 'release certification: '.$throwable->getMessage().PHP_EOL);
    exit(1);
}
