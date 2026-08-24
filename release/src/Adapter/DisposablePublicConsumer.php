<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use Fight\Release\Application\Boundary\PublicConsumerPort;
use Fight\Release\Application\Boundary\PublicConsumerProbeRejected;
use Fight\Release\Application\SchedulerEvidenceAuthority;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Class DisposablePublicConsumer
 *
 * Installs one candidate as a copied path package and runs isolated representative and Scheduler probes.
 */
final readonly class DisposablePublicConsumer implements PublicConsumerPort
{
    /**
     * Runs one external installed-package probe
     *
     * @return array<string, mixed>
     */
    public function run(string $repository, string $fixture, string $consumer): array
    {
        $candidateComposerBytes = file_get_contents($repository.'/composer.json');
        $fixtureComposerBytes = file_get_contents($fixture.'/composer.json');
        assert(is_string($candidateComposerBytes));
        assert(is_string($fixtureComposerBytes));
        $candidateComposer = json_decode($candidateComposerBytes, true, flags: JSON_THROW_ON_ERROR);
        $productionAutoload = $this->productionAutoload($candidateComposer);
        $candidateProductionDigest = $this->productionTreeDigest($repository, $productionAutoload);
        $consumerComposer = json_decode($fixtureComposerBytes, true, flags: JSON_THROW_ON_ERROR);
        $consumerComposer['repositories'][0]['url'] = $repository;
        file_put_contents(
            $consumer.'/composer.json',
            json_encode(
                $consumerComposer,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )."\n"
        );
        copy($fixture.'/probe.php', $consumer.'/probe.php');
        copy($fixture.'/public-api-probe.php', $consumer.'/public-api-probe.php');

        $this->runProcess(
            ['/usr/local/bin/composer', 'install', '--no-interaction', '--no-progress', '--no-plugins', '--no-scripts'],
            $consumer,
            [
                'COMPOSER_DISABLE_NETWORK' => '1',
                'COMPOSER_HOME'            => $consumer.'/.composer',
                'PATH'                     => '/usr/local/bin:/usr/bin:/bin'
            ]
        );

        $installedPackage = $consumer.'/vendor/johnnickell/fight-common';
        $this->authenticateCopiedPackage($repository, $consumer, $installedPackage);
        $publicApiProbeBytes = $this->runPublicApiProbe(
            [PHP_BINARY, $consumer.'/public-api-probe.php', $consumer.'/vendor/autoload.php'],
            $consumer,
            ['PATH' => '/usr/local/bin:/usr/bin:/bin']
        );
        $publicApiProbeReceipt = json_decode($publicApiProbeBytes, true, flags: JSON_THROW_ON_ERROR);
        SchedulerEvidenceAuthority::isPublicApiProbeReceipt($publicApiProbeReceipt)
            || throw new RuntimeException('The representative public API probe evidence is invalid.');

        $schedulerProbeBytes = $this->runProbe(
            [PHP_BINARY, $consumer.'/probe.php', $consumer.'/vendor/autoload.php'],
            $consumer,
            ['PATH' => '/usr/local/bin:/usr/bin:/bin']
        );
        $schedulerProbeReceipt = json_decode($schedulerProbeBytes, true, flags: JSON_THROW_ON_ERROR);
        SchedulerEvidenceAuthority::isSchedulerProbeReceipt($schedulerProbeReceipt)
            || throw new RuntimeException('The Scheduler probe evidence is invalid.');
        $runtimeDeprecations = [];
        foreach (
            [
                ...$publicApiProbeReceipt['observations']['runtime_deprecations'],
                ...$schedulerProbeReceipt['observations']['runtime_deprecations']
            ] as $runtimeDeprecation
        ) {
            $runtimeDeprecations[json_encode($runtimeDeprecation, JSON_THROW_ON_ERROR)] = $runtimeDeprecation;
        }

        $probeReceipt = [
            'schema_version' => 'fight-common.public-api-probe/v1',
            'findings'       => [
                ...$publicApiProbeReceipt['findings'],
                ...$schedulerProbeReceipt['findings']
            ],
            'observations'   => [
                'uuid'                 => $publicApiProbeReceipt['observations']['uuid'],
                'meta'                 => $publicApiProbeReceipt['observations']['meta'],
                'collection'           => $publicApiProbeReceipt['observations']['collection'],
                'runtime_deprecations' => array_values($runtimeDeprecations),
                'scheduler'            => $schedulerProbeReceipt['observations']['scheduler']
            ]
        ];
        $probeBytes = json_encode(
            $probeReceipt,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )."\n";
        file_put_contents($consumer.'/probe-receipt.json', $probeBytes);

        $lockBytes = file_get_contents($consumer.'/composer.lock');
        $installedComposerBytes = file_get_contents($installedPackage.'/composer.json');
        assert(is_string($lockBytes));
        assert(is_string($installedComposerBytes));
        $installedComposer = json_decode($installedComposerBytes, true, flags: JSON_THROW_ON_ERROR);
        ($installedComposer['autoload'] ?? null) === ($candidateComposer['autoload'] ?? null)
            || throw new RuntimeException('Installed production autoload authority does not match candidate.');
        $installedProductionDigest = $this->productionTreeDigest(
            $installedPackage,
            $productionAutoload
        );
        hash_equals($candidateProductionDigest, $installedProductionDigest)
            || throw new RuntimeException('Installed production autoload tree does not match candidate.');
        $lock = json_decode($lockBytes, true, flags: JSON_THROW_ON_ERROR);
        $packages = array_column($lock['packages'], null, 'name');
        $resolved = $packages['johnnickell/fight-common'];

        return [
            'schema_version'   => 'fight-common.disposable-public-consumer/v1',
            'status'           => 'valid',
            'classification'   => 'patch',
            'findings'         => $probeReceipt['findings'],
            'candidate'        => [
                'package'                => $candidateComposer['name'],
                'composer_sha256'        => hash('sha256', $candidateComposerBytes),
                'production_tree_sha256' => $candidateProductionDigest
            ],
            'resolved_package' => [
                'package'                => $resolved['name'],
                'version'                => $resolved['version'],
                'reference'              => $resolved['dist']['reference'],
                'composer_sha256'        => hash('sha256', $installedComposerBytes),
                'production_tree_sha256' => $installedProductionDigest,
                'installed_as'           => 'copy'
            ],
            'lock'             => [
                'sha256'       => hash('sha256', $lockBytes),
                'content_hash' => $lock['content-hash']
            ],
            'probe'            => [
                'sha256'       => hash('sha256', $probeBytes),
                'observations' => $probeReceipt['observations']
            ]
        ];
    }

    /**
     * Authenticates that Composer installed the candidate within the consumer as a distinct directory copy
     */
    private function authenticateCopiedPackage(string $repository, string $consumer, string $installedPackage): void
    {
        $candidateRoot = realpath($repository);
        $consumerRoot = realpath($consumer);
        $installedRoot = realpath($installedPackage);
        if (
            !is_dir($installedPackage)
            || is_link($installedPackage)
            || !is_string($candidateRoot)
            || !is_string($consumerRoot)
            || !is_string($installedRoot)
            || $installedRoot === $candidateRoot
            || !str_starts_with($installedRoot, $consumerRoot.'/')
        ) {
            throw new RuntimeException('Installed candidate package is not an authenticated copy.');
        }
    }

    /**
     * Returns the exact production PSR-4 directories and files from Composer authority
     *
     * @param array<string, mixed> $composer
     *
     * @return array{directories: list<string>, files: list<string>}
     */
    private function productionAutoload(array $composer): array
    {
        $directories = [];
        foreach (($composer['autoload']['psr-4'] ?? []) as $paths) {
            foreach (is_array($paths) ? $paths : [$paths] as $path) {
                is_string($path) || throw new RuntimeException('Production PSR-4 path is invalid.');
                $directories[] = $this->canonicalAutoloadPath($path);
            }
        }

        $autoloadFiles = [];
        foreach (($composer['autoload']['files'] ?? []) as $path) {
            is_string($path) || throw new RuntimeException('Production autoload file is invalid.');
            $autoloadFiles[] = $this->canonicalAutoloadPath($path);
        }

        $directories = array_values(array_unique($directories));
        $autoloadFiles = array_values(array_unique($autoloadFiles));
        sort($directories, SORT_STRING);
        sort($autoloadFiles, SORT_STRING);

        return ['directories' => $directories, 'files' => $autoloadFiles];
    }

    /**
     * Returns an exact digest for the candidate-authorized production Composer and autoload tree
     *
     * @param string                                               $package            Package root.
     * @param array{directories: list<string>, files: list<string>} $productionAutoload Production paths.
     */
    private function productionTreeDigest(string $package, array $productionAutoload): string
    {
        $files = ['composer.json'];

        foreach ($productionAutoload['directories'] as $directory) {
            is_dir($package.'/'.$directory)
                || throw new RuntimeException('Production autoload path is unavailable: '.$directory);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($package.'/'.$directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $files[] = substr($file->getPathname(), strlen($package) + 1);
                }
            }
        }

        foreach ($productionAutoload['files'] as $autoloadFile) {
            is_file($package.'/'.$autoloadFile)
                || throw new RuntimeException('Production autoload file is unavailable: '.$autoloadFile);
            $files[] = $autoloadFile;
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);
        $context = hash_init('sha256');

        foreach ($files as $relativePath) {
            $bytes = file_get_contents($package.'/'.$relativePath);
            assert(is_string($bytes));
            hash_update($context, $relativePath."\0".strlen($bytes)."\0".$bytes);
        }

        return hash_final($context);
    }

    /**
     * Returns one canonical package-relative Composer autoload path
     */
    private function canonicalAutoloadPath(string $path): string
    {
        $canonical = rtrim($path, '/');
        if (
            $canonical === ''
            || str_starts_with($canonical, '/')
            || str_contains($canonical, '\\')
            || in_array('..', explode('/', $canonical), true)
        ) {
            throw new RuntimeException('Production autoload path is not canonical: '.$path);
        }

        return $canonical;
    }

    /**
     * Runs one closed local process and returns its complete standard output
     *
     * @param array  $command     Closed process argument vector.
     * @param string $directory   Disposable consumer working directory.
     * @param array  $environment Isolated process environment.
     *
     * @phpstan-param list<string> $command
     * @phpstan-param array<string, string> $environment
     */
    private function runProcess(array $command, string $directory, array $environment): string
    {
        $outcome = $this->runProcessOutcome($command, $directory, $environment);
        $outcome['status'] === 0 || throw new RuntimeException(
            'The public consumer process failed: '.(
                is_string($outcome['error']) ? trim($outcome['error']) : 'unknown failure'
            )
        );

        return $outcome['output'];
    }

    /**
     * Runs one closed local process and returns its observed outcome
     *
     * Launch and standard-output read failures throw before a subprocess outcome is available.
     *
     * @param array  $command     Closed process argument vector.
     * @param string $directory   Disposable consumer working directory.
     * @param array  $environment Isolated process environment.
     *
     * @phpstan-param list<string> $command
     * @phpstan-param array<string, string> $environment
     *
     * @return array{status: int, output: string, error: string|false}
     */
    private function runProcessOutcome(array $command, string $directory, array $environment): array
    {
        $pipes = [];
        $process = @proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            $directory,
            $environment,
            ['bypass_shell' => true]
        );
        is_resource($process) || throw new RuntimeException('The public consumer process is unavailable.');
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        is_string($output) || throw new RuntimeException('The public consumer output is unavailable.');

        return ['status' => $status, 'output' => $output, 'error' => $error];
    }

    /**
     * Runs the installed-package Scheduler probe through its typed failure boundary
     *
     * @param array  $command     Closed probe argument vector.
     * @param string $directory   Disposable consumer working directory.
     * @param array  $environment Isolated probe environment.
     *
     * @phpstan-param list<string> $command
     * @phpstan-param array<string, string> $environment
     */
    private function runProbe(array $command, string $directory, array $environment): string
    {
        $outcome = $this->runProcessOutcome($command, $directory, $environment);
        $outcome['status'] === 0 || throw new PublicConsumerProbeRejected(
            'The installed Scheduler probe failed to compile or execute.'
        );

        return $outcome['output'];
    }

    /**
     * Runs the representative installed public-API probe without assigning Scheduler semantics to its failure
     *
     * @param array  $command     Closed probe argument vector.
     * @param string $directory   Disposable consumer working directory.
     * @param array  $environment Isolated probe environment.
     *
     * @phpstan-param list<string> $command
     * @phpstan-param array<string, string> $environment
     */
    private function runPublicApiProbe(array $command, string $directory, array $environment): string
    {
        $outcome = $this->runProcessOutcome($command, $directory, $environment);
        $outcome['status'] === 0 || throw new RuntimeException(
            'The representative public API probe failed to compile or execute.'
        );

        return $outcome['output'];
    }
}
