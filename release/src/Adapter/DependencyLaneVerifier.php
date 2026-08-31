<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use Fight\Release\Application\Boundary\DependencyLanePort;
use Fight\Release\Application\DependencyLaneEvidenceAuthority;
use RuntimeException;

/**
 * Class DependencyLaneVerifier
 *
 * Produces disposable Composer evidence for every supported dependency and framework-isolation lane.
 *
 * Each lane has its own Composer home and lock file.  The verifier deliberately reports one failed
 * receipt instead of allowing an unavailable solver to look like a compatible package.
 */
final readonly class DependencyLaneVerifier implements DependencyLanePort
{
    /** @var array<string, array<string, string>> */
    private const array FRAMEWORKS = [
        'symfony'     => [
            'symfony/framework-bundle' => '^8.1',
            'symfony/process'          => '^8.1'
        ],
        'laravel'     => ['laravel/framework' => '^13.0'],
        'yii'         => [
            'yiisoft/di'               => '^1.4',
            'yiisoft/db'               => '^2.0',
            'yiisoft/db-sqlite'        => '^2.0',
            'yiisoft/router'           => '^4.0',
            'yiisoft/router-fastroute' => '^4.0',
            'yiisoft/view'             => '^12.2'
        ],
        'codeigniter' => [
            'codeigniter4/framework' => '^4.7',
            'codeigniter4/queue'     => '^1.0'
        ],
        'slim'        => ['slim/slim' => '^4.14']
    ];

    /**
     * Verifies every dependency lane in one disposable workspace
     *
     * @return array<string, mixed>
     */
    public function verify(string $repository, string $workspace): array
    {
        $lanes = [];
        foreach (DependencyLaneEvidenceAuthority::LANES as $lane) {
            try {
                $lanes[$lane] = $this->lane($repository, $workspace, $lane);
            } catch (RuntimeException $exception) {
                return [
                    'schema_version' => 'fight-common.dependency-lanes/v1',
                    'status'         => 'invalid',
                    'lanes'          => $lanes,
                    'failure'        => [
                        'lane'        => $lane,
                        'attribution' => 'composer',
                        'message'     => $exception->getMessage(),
                        'next_action' => ['action' => 'restore_'.$lane.'_dependency_lane_and_retry']
                    ]
                ];
            }
        }

        return ['schema_version' => 'fight-common.dependency-lanes/v1', 'status' => 'valid', 'lanes' => $lanes];
    }

    /**
     * Resolves and verifies one isolated lane
     *
     * @return array<string, mixed>
     */
    private function lane(string $repository, string $workspace, string $lane): array
    {
        $directory = $workspace.'/dependency-'.$lane;
        mkdir($directory, 0700, true) || throw new RuntimeException('The '.$lane.' lane workspace is unavailable.');
        $composer = $this->consumerManifest($repository, $lane);
        $manifest = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        file_put_contents($directory.'/composer.json', $manifest);
        // A disposable consumer has no tracked lock. Resolve once, then install from the resulting lock for
        // the locked lane; the other modes each resolve from their own fresh manifest.
        $command = ['/usr/local/bin/composer', 'update', '--no-interaction', '--no-progress', '--no-scripts'];
        if ($lane === 'production') {
            $command[] = '--no-dev';
        }

        if ($lane === 'lowest') {
            $command[] = '--prefer-lowest';
        }

        $this->run($command, $directory);
        if ($lane === 'locked') {
            $this->run(
                ['/usr/local/bin/composer', 'install', '--no-interaction', '--no-progress', '--no-scripts'],
                $directory
            );
        }

        $lockBytes = file_get_contents($directory.'/composer.lock');
        is_string($lockBytes) || throw new RuntimeException('The '.$lane.' lane did not produce a lock file.');
        $lock = json_decode($lockBytes, true, flags: JSON_THROW_ON_ERROR);
        $resolved = array_column($lock['packages'] ?? [], 'version', 'name');
        $this->assertIsolation($lane, $resolved);
        $probes = $this->runProbe($lane, $directory);

        return [
            'name'        => $lane,
            'status'      => 'passed',
            'lock_sha256' => hash('sha256', $lockBytes),
            'resolved'    => $resolved,
            'probes'      => $probes,
            'next_action' => ['action' => 'review_dependency_lane_evidence']
        ];
    }

    /**
     * Returns a Composer manifest for one dependency lane
     *
     * @return array<string, mixed>
     */
    private function consumerManifest(string $repository, string $lane): array
    {
        $require = ['php' => '>=8.5', 'johnnickell/fight-common' => '*'];
        if (isset(self::FRAMEWORKS[$lane])) {
            $require = [...$require, ...self::FRAMEWORKS[$lane]];
        }

        return [
            'name'              => 'fight/dependency-lane-'.$lane,
            'require'           => $require,
            'repositories'      => [['type' => 'path', 'url' => $repository, 'options' => ['symlink' => false]]],
            // The candidate is a copied path package whose Composer version is dev-main; this is
            // consumer-local resolution policy, while prefer-stable preserves stable transitive packages.
            'minimum-stability' => 'dev',
            'prefer-stable'     => true,
            'config'            => ['allow-plugins' => ['yiisoft/config' => true]]
        ];
    }

    /**
     * Runs one closed Composer command
     *
     * @phpstan-param list<string> $command Composer argument vector.
     */
    private function run(array $command, string $directory): void
    {
        $process = proc_open(
            $command,
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $directory,
            ['COMPOSER_HOME' => $directory.'/.composer', 'PATH' => '/usr/local/bin:/usr/bin:/bin'],
            ['bypass_shell' => true]
        );
        is_resource($process) || throw new RuntimeException(
            'Composer is unavailable for dependency-lane verification.'
        );
        stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process) === 0 || throw new RuntimeException(
            'Composer could not resolve the dependency lane: '.trim((string) $error)
        );
    }

    /**
     * Loads public and selected adapter namespaces from the resolved consumer
     *
     * @return array<string, string>
     */
    private function runProbe(string $lane, string $directory): array
    {
        $classes = [
            'public_api'  => $this->className('Fight', 'Common', 'Domain', 'Value', 'Identifier', 'Uuid'),
            'transaction' => $this->className(
                'Fight',
                'Common',
                'Application',
                'Repository',
                'TransactionalUnitOfWork'
            ),
            'messaging'   => $this->className('Fight', 'Common', 'Application', 'Messaging', 'Command', 'CommandBus'),
            'http'        => $this->className('Fight', 'Common', 'Adapter', 'Http', 'Psr17', 'JSendResponseFactory'),
            'cache'       => $this->className('Fight', 'Common', 'Application', 'Cache', 'Cache')
        ];
        $frameworkClasses = [
            'symfony'     => [
                'package' => $this->className('Symfony', 'Component', 'Process', 'Process'),
                'adapter' => $this->className(
                    'Fight',
                    'Common',
                    'Adapter',
                    'Process',
                    'Symfony',
                    'SymfonyProcessRunner'
                )
            ],
            'laravel'     => [
                'package' => $this->className('Illuminate', 'Cache', 'ArrayStore'),
                'adapter' => $this->className('Fight', 'Common', 'Adapter', 'Cache', 'Laravel', 'LaravelCache')
            ],
            'yii'         => [
                'package' => $this->className('Yiisoft', 'Di', 'Container'),
                'adapter' => $this->className(
                    'Fight',
                    'Common',
                    'Adapter',
                    'Persistence',
                    'Yii',
                    'YiiTransactionalUnitOfWork'
                )
            ],
            'codeigniter' => [
                'package' => $this->className('CodeIgniter', 'Cache', 'CacheFactory'),
                'adapter' => $this->className('Fight', 'Common', 'Adapter', 'Cache', 'CodeIgniter', 'CodeIgniterCache')
            ],
            'slim'        => [
                'package' => $this->className('Slim', 'Factory', 'AppFactory'),
                'adapter' => $this->className('Fight', 'Common', 'Adapter', 'Routing', 'Slim', 'SlimUrlGenerator')
            ]
        ];
        if (isset($frameworkClasses[$lane])) {
            $classes = [...$classes, ...$frameworkClasses[$lane]];
        }

        $script = implode('', [
            '<?php declare(strict_types=1); $classes = ',
            var_export(array_values($classes), true),
            '; require __DIR__."/vendor/autoload.php"; foreach ($classes as $class) {',
            ' if (!class_exists($class) && !interface_exists($class)) {',
            ' fwrite(STDERR, $class." unavailable\\n"); exit(1); } }',
            ' echo json_encode(array_fill_keys(array_keys(',
            var_export($classes, true),
            '), "passed"), JSON_THROW_ON_ERROR);'
        ]);
        file_put_contents($directory.'/lane-probe.php', $script);
        $process = proc_open(
            [PHP_BINARY, $directory.'/lane-probe.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $directory,
            ['PATH' => '/usr/local/bin:/usr/bin:/bin'],
            ['bypass_shell' => true]
        );
        is_resource($process) || throw new RuntimeException('Dependency lane probe runtime is unavailable.');
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process) === 0 || throw new RuntimeException(
            'Dependency lane probe failed: '.trim((string) $error)
        );
        $probes = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        return is_array($probes) ? $probes : throw new RuntimeException('Dependency lane probe receipt is invalid.');
    }

    /**
     * Builds an opaque consumer class name without creating a release-layer dependency
     */
    private function className(string ...$segments): string
    {
        return implode('\\', $segments);
    }

    /**
     * Rejects installed optional frameworks outside a lane's selected stack
     *
     * @phpstan-param array<string, string> $resolved Resolved Composer package versions.
     */
    private function assertIsolation(string $lane, array $resolved): void
    {
        foreach (self::FRAMEWORKS as $name => $packages) {
            $package = array_key_first($packages);
            if ($name === $lane) {
                isset($resolved[$package]) || throw new RuntimeException('The selected '.$lane.' framework is absent.');
            } elseif ($lane === 'production' || isset(self::FRAMEWORKS[$lane])) {
                !isset($resolved[$package]) || throw new RuntimeException(
                    'The unselected '.$name.' framework leaked into '.$lane.'.'
                );
            }
        }
    }
}
