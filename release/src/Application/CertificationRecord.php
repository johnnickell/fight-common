<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use RuntimeException;

/**
 * Class CertificationRecord
 */
final readonly class CertificationRecord
{
    /**
     * Creates a compact certification record
     *
     * @param string                                    $version            Candidate version
     * @param string                                    $commit             Candidate commit
     * @param string                                    $archive            Archive filename
     * @param string                                    $archiveSha256      Archive digest
     * @param list<array<string, int|string>>           $commands           Command outcomes
     * @param array<string, array<string, mixed>>       $dependencyVersions Dependency lane evidence
     * @param array<string, mixed>                      $surface            Package-surface result
     * @param array<string, mixed>                      $consumer           Installed-consumer result
     * @param list<array<string, string>>               $starterReceipts    Historical starter receipts
     *
     * @return array<string, mixed>
     */
    public function create(
        string $version,
        string $commit,
        string $archive,
        string $archiveSha256,
        array $commands,
        array $dependencyVersions,
        array $surface,
        array $consumer,
        array $starterReceipts
    ): array {
        preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', $version) === 1
            || throw new RuntimeException('The certification version is invalid.');
        $this->requireSha1($commit, 'candidate commit');
        $this->requireSha256($archiveSha256, 'archive digest');

        $expectedCommands = [
            'default-dependency-resolution',
            'default-product-gate',
            'latest-dependency-resolution',
            'latest-product-gate',
            'lowest-dependency-resolution',
            'lowest-product-gate',
            'composer-archive',
            'consumer-resolution',
            'production-install',
            'installed-consumer-probe',
            'package-surface'
        ];
        $commandNames = array_column($commands, 'name');
        $commandNames === $expectedCommands
            || throw new RuntimeException('The certification command outcomes are incomplete or out of order.');

        foreach ($commands as $command) {
            if (($command['status'] ?? null) !== 'passed' || ($command['exit_code'] ?? null) !== 0) {
                throw new RuntimeException('A command outcome is not passing.');
            }

            $this->requireSha256($command['output_sha256'] ?? null, 'command output digest');
        }

        ($surface['status'] ?? null) === 'passed'
            || throw new RuntimeException('The package surface did not pass.');
        ($consumer['status'] ?? null) === 'passed'
            || throw new RuntimeException('The installed-consumer probe did not pass.');

        array_keys($dependencyVersions) === ['default', 'latest', 'lowest']
            || throw new RuntimeException('The dependency lanes are incomplete or out of order.');
        foreach ($dependencyVersions as $lane => $evidence) {
            $this->requireSha256($evidence['lock_sha256'] ?? null, $lane.' dependency lock digest');
            is_array($evidence['packages'] ?? null) && $evidence['packages'] !== []
                || throw new RuntimeException('The '.$lane.' dependency package versions are empty.');
        }

        count($starterReceipts) === 5 || throw new RuntimeException('Exactly five starter receipts are required.');
        $frameworks = [];

        foreach ($starterReceipts as $receipt) {
            foreach (['ticket', 'framework', 'repository', 'path'] as $field) {
                is_string($receipt[$field] ?? null) && $receipt[$field] !== ''
                    || throw new RuntimeException('A starter receipt identity is incomplete.');
            }

            $receipt['ticket'] === 'T-00075'
                || throw new RuntimeException('A starter receipt cites the wrong acceptance ticket.');
            $receipt['repository'] === 'johnnickell/project-'.$receipt['framework']
                || throw new RuntimeException('A starter receipt repository identity is invalid.');
            $receipt['path'] === 'evidence/framework-support/receipt-v1.json'
                || throw new RuntimeException('A starter receipt path identity is invalid.');
            $frameworks[] = $receipt['framework'];
            $this->requireSha1($receipt['commit'] ?? null, 'starter receipt commit');
            $this->requireSha256($receipt['sha256'] ?? null, 'starter receipt digest');
        }

        sort($frameworks, SORT_STRING);
        $frameworks === ['codeigniter', 'laravel', 'slim', 'symfony', 'yii']
            || throw new RuntimeException('Starter receipt framework identities are incomplete.');

        $record = [
            'schema_version'      => 'fight-common.release-certification/v2',
            'status'              => 'certified',
            'version'             => $version,
            'candidate'           => ['commit' => $commit, 'checkout' => 'clean'],
            'archive'             => ['file' => $archive, 'sha256' => $archiveSha256],
            'commands'            => $commands,
            'dependency_versions' => $dependencyVersions,
            'package_surface'     => $surface,
            'installed_consumer'  => $consumer,
            'starter_receipts'    => $starterReceipts,
            'excluded_effects'    => ['merge', 'tag', 'push', 'github', 'packagist', 'deployment']
        ];
        $record['certification_sha256'] = hash('sha256', $this->canonicalJson($record));

        return $record;
    }

    /**
     * Encodes recursively key-sorted JSON for stable identity
     *
     * @param array<mixed> $value Value to encode
     */
    public function canonicalJson(array $value): string
    {
        $value = $this->sortKeys($value);

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Sorts every object-like array by key
     *
     * @param array<mixed> $value Value to sort
     *
     * @return array<mixed>
     */
    private function sortKeys(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }

        return $value;
    }

    /**
     * Requires one lowercase SHA-1 digest
     */
    private function requireSha1(mixed $value, string $name): void
    {
        is_string($value) && preg_match('/\A[a-f0-9]{40}\z/D', $value) === 1
            || throw new RuntimeException('The '.$name.' is invalid.');
    }

    /**
     * Requires one lowercase SHA-256 digest
     */
    private function requireSha256(mixed $value, string $name): void
    {
        is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1
            || throw new RuntimeException('The '.$name.' is invalid.');
    }
}
