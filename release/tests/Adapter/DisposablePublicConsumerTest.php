<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Adapter\DisposablePublicConsumer;
use Fight\Test\Common\TestCase\UnitTestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DisposablePublicConsumerTest
 */
#[CoversClass(DisposablePublicConsumer::class)]
final class DisposablePublicConsumerTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves an attributed public probe through a copied Composer package outside the repository
     */
    public function test_that_a_disposable_external_consumer_receives_exact_package_and_probe_receipts(): void
    {
        $root = dirname(__DIR__, 3);
        $fixture = $root.'/release/fixtures/PublicApiConsumer';
        $consumer = sys_get_temp_dir().'/fight-common-public-consumer-'.bin2hex(random_bytes(8));
        mkdir($consumer, 0777, true);

        try {
            $receipt = new DisposablePublicConsumer()->run($root, $fixture, $consumer);
            $installedPackage = $consumer.'/vendor/johnnickell/fight-common';
            $lockBytes = file_get_contents($consumer.'/composer.lock');
            $probeBytes = file_get_contents($consumer.'/probe-receipt.json');

            self::assertIsString($lockBytes);
            self::assertIsString($probeBytes);
            $lock = json_decode($lockBytes, true, flags: JSON_THROW_ON_ERROR);
            $packages = array_column($lock['packages'], null, 'name');
            $resolved = $packages['johnnickell/fight-common'];
            self::assertFalse(str_starts_with(realpath($consumer) ?: '', realpath($root) ?: ''));
            self::assertFileExists($installedPackage.'/composer.json');
            self::assertFalse(is_link($installedPackage));
            self::assertSame(
                [
                    'schema_version'   => 'fight-common.disposable-public-consumer/v1',
                    'status'           => 'valid',
                    'classification'   => 'patch',
                    'findings'         => [[
                        'finding_id'  => 'release.compatibility.consumer.public-api-probe-passed',
                        'evidence_id' => 'fight-common.consumer.public-api-representative',
                        'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
                        'status'      => 'passed'
                    ]],
                    'candidate'        => [
                        'package'                => 'johnnickell/fight-common',
                        'composer_sha256'        => hash_file('sha256', $root.'/composer.json'),
                        'production_tree_sha256' => $this->productionTreeDigest($root)
                    ],
                    'resolved_package' => [
                        'package'                => 'johnnickell/fight-common',
                        'version'                => $resolved['version'],
                        'reference'              => $resolved['dist']['reference'],
                        'composer_sha256'        => hash_file('sha256', $installedPackage.'/composer.json'),
                        'production_tree_sha256' => $this->productionTreeDigest($installedPackage),
                        'installed_as'           => 'copy'
                    ],
                    'lock'             => [
                        'sha256'       => hash('sha256', $lockBytes),
                        'content_hash' => json_decode($lockBytes, true, flags: JSON_THROW_ON_ERROR)['content-hash']
                    ],
                    'probe'            => [
                        'sha256'       => hash('sha256', $probeBytes),
                        'observations' => [
                            'uuid'       => '00000000-0000-0000-0000-000000000000',
                            'meta'       => ['consumer' => 'disposable'],
                            'collection' => ['alpha', 'beta']
                        ]
                    ]
                ],
                $receipt
            );
        } finally {
            new Filesystem()->remove($consumer);
        }
    }

    /**
     * Proves run refuses to describe a Composer path symlink as an installed copy.
     */
    public function test_that_run_rejects_a_symlinked_installed_candidate_before_emitting_a_copy_receipt(): void
    {
        $root = dirname(__DIR__, 3);
        $workspace = sys_get_temp_dir().'/fight-common-symlinked-consumer-'.bin2hex(random_bytes(8));
        $fixture = $workspace.'/fixture';
        $consumer = $workspace.'/consumer';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$fixture, $consumer]);
        $filesystem->copy($root.'/release/fixtures/PublicApiConsumer/probe.php', $fixture.'/probe.php');

        $composer = json_decode(
            (string) file_get_contents($root.'/release/fixtures/PublicApiConsumer/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $composer['repositories'][0]['options']['symlink'] = true;
        $filesystem->dumpFile(
            $fixture.'/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
        );

        try {
            new DisposablePublicConsumer()->run($root, $fixture, $consumer);
            self::fail('A symlinked candidate package was described as an installed copy.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(
                'Installed candidate package is not an authenticated copy.',
                $runtimeException->getMessage()
            );
            self::assertTrue(is_link($consumer.'/vendor/johnnickell/fight-common'));
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Proves copied-package receipts include every production PSR-4 root and fail when one is absent.
     */
    public function test_that_production_digest_includes_and_requires_the_test_case_autoload_root(): void
    {
        $root = dirname(__DIR__, 3);
        $fixture = $root.'/release/fixtures/PublicApiConsumer';
        $workspace = sys_get_temp_dir().'/fight-common-production-digest-'.bin2hex(random_bytes(8));
        $candidate = $workspace.'/candidate';
        $mutatedConsumer = $workspace.'/mutated-consumer';
        $missingConsumer = $workspace.'/missing-consumer';
        $invalidConsumer = $workspace.'/invalid-consumer';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$candidate, $mutatedConsumer, $missingConsumer, $invalidConsumer]);
        $filesystem->copy($root.'/composer.json', $candidate.'/composer.json');
        $filesystem->mirror($root.'/src', $candidate.'/src');
        $filesystem->mirror($root.'/tests/TestCase', $candidate.'/tests/TestCase');

        try {
            $mutation = $candidate.'/tests/TestCase/UnitTestCase.php';
            self::assertFileExists($mutation);
            file_put_contents($mutation, "\n// receipt mutation\n", FILE_APPEND);
            $receipt = new DisposablePublicConsumer()->run($candidate, $fixture, $mutatedConsumer);
            $expectedMutated = $this->productionTreeDigest($candidate);

            self::assertSame($expectedMutated, $receipt['candidate']['production_tree_sha256']);
            self::assertSame($expectedMutated, $receipt['resolved_package']['production_tree_sha256']);
            self::assertNotSame($this->productionTreeDigest($root), $expectedMutated);

            $filesystem->remove($candidate.'/tests/TestCase');
            try {
                new DisposablePublicConsumer()->run($candidate, $fixture, $missingConsumer);
                self::fail('A missing production PSR-4 root did not fail the copied-package receipt.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame(
                    'Production autoload path is unavailable: tests/TestCase',
                    $runtimeException->getMessage()
                );
            }

            $composer = json_decode(
                (string) file_get_contents($candidate.'/composer.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $composer['autoload']['psr-4']["Fight\\Test\\Common\\TestCase\\"] = '../outside';
            file_put_contents(
                $candidate.'/composer.json',
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
            );
            try {
                new DisposablePublicConsumer()->run($candidate, $fixture, $invalidConsumer);
                self::fail('A non-canonical production PSR-4 root did not fail the copied-package receipt.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame(
                    'Production autoload path is not canonical: ../outside',
                    $runtimeException->getMessage()
                );
            }
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Independently calculates the specified production-tree framing used by the receipt
     */
    private function productionTreeDigest(string $package): string
    {
        $files = ['composer.json'];
        $composer = json_decode(
            (string) file_get_contents($package.'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $roots = array_values($composer['autoload']['psr-4']);

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($package.'/'.$root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $files[] = substr($file->getPathname(), strlen($package) + 1);
                }
            }
        }

        $files = [...$files, ...$composer['autoload']['files']];
        $files = array_values(array_unique($files));

        sort($files, SORT_STRING);
        $context = hash_init('sha256');

        foreach ($files as $relativePath) {
            $bytes = file_get_contents($package.'/'.$relativePath);
            is_string($bytes) || throw new RuntimeException('Production package input is unreadable.');
            hash_update($context, $relativePath."\0".strlen($bytes)."\0".$bytes);
        }

        return hash_final($context);
    }
}
