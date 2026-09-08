<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation;

use Fight\Test\Common\TestCase\UnitTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class QuickStartDocumentationContractTest extends UnitTestCase
{
    public function test_that_the_quick_start_uses_only_the_named_executable_fixture_snippets(): void
    {
        $guide = $this->readRepositoryFile('docs/quickstart.md');
        $fixture = $this->readRepositoryFile('tests/Documentation/QuickStart/OrderProcessingExample.php');

        self::assertStringContainsString(
            '--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:order-processing-composition"',
            $guide,
        );
        self::assertStringContainsString(
            '--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:process-order"',
            $guide,
        );
        self::assertStringContainsString(
            '--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:complete-order-processing-example"',
            $guide,
        );
        self::assertStringContainsString('// --8<-- [start:order-processing-composition]', $fixture);
        self::assertStringContainsString('// --8<-- [end:order-processing-composition]', $fixture);
        self::assertStringContainsString('// --8<-- [start:process-order]', $fixture);
        self::assertStringContainsString('// --8<-- [end:process-order]', $fixture);
        self::assertStringContainsString('// --8<-- [start:complete-order-processing-example]', $fixture);
        self::assertStringContainsString('// --8<-- [end:complete-order-processing-example]', $fixture);
        self::assertStringContainsString('namespace Fight\\Test\\Common\\Documentation\\QuickStart;', $fixture);
        $completeExample = $this->fixtureRegion($fixture, 'complete-order-processing-example');
        self::assertStringStartsWith('use Fight\\Common\\', $completeExample);
        self::assertStringNotContainsString('namespace ', $completeExample);
        self::assertSame(3, substr_count($guide, '```php-inline'));
        self::assertSame(6, substr_count($guide, '```'));
        self::assertStringContainsString('<?php', $guide);
        self::assertStringContainsString('composer require johnnickell/fight-common', $guide);
        self::assertStringContainsString('content.code.copy', $this->readRepositoryFile('mkdocs.yml'));
    }

    public function test_that_the_reader_visible_complete_example_executes_from_the_documented_relative_command(): void
    {
        $fixture = $this->readRepositoryFile('tests/Documentation/QuickStart/OrderProcessingExample.php');
        $directory = sys_get_temp_dir().'/fight-common-quick-start-'.bin2hex(random_bytes(8));
        $path = $directory.'/order-processing.php';
        $repository = dirname(__DIR__, 2);
        $composerFixture = $repository.'/release/fixtures/ComposerConsumer/composer.json';

        self::assertTrue(mkdir($directory));

        try {
            $manifest = json_decode(
                $this->readRepositoryFile('release/fixtures/ComposerConsumer/composer.json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertIsArray($manifest);
            $manifest['repositories'][0]['url'] = $repository;
            $manifest['repositories'][0]['options']['symlink'] = false;
            $manifest['minimum-stability'] = 'dev';
            $manifest['prefer-stable'] = true;
            self::assertFileExists($composerFixture);
            file_put_contents(
                $directory.'/composer.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
            );

            $install = new Process(
                ['/usr/local/bin/composer', 'install', '--no-dev', '--no-interaction', '--no-progress', '--no-plugins', '--no-scripts'],
                $directory,
                [
                    'COMPOSER_DISABLE_NETWORK' => '1',
                    'COMPOSER_HOME' => $directory.'/.composer',
                    'PATH' => '/usr/local/bin:/usr/bin:/bin',
                ],
                null,
                120,
            );
            $install->run();

            self::assertSame(0, $install->getExitCode(), $install->getErrorOutput());

            $package = $directory.'/vendor/johnnickell/fight-common';
            self::assertDirectoryExists($package);
            self::assertFalse(is_link($package));
            $installedPackage = realpath($package);
            self::assertIsString($installedPackage);
            self::assertStringStartsWith($directory.'/', $installedPackage);
            self::assertNotSame(realpath($repository), $installedPackage);

            file_put_contents($path, "<?php\n".$this->fixtureRegion($fixture, 'complete-order-processing-example'));
            $process = new Process(['php', 'order-processing.php'], $directory);
            $process->run();

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertSame(
                "Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested.\n",
                $process->getOutput(),
            );
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function test_that_the_quick_start_references_only_public_fight_symbols_and_operations(): void
    {
        $fixture = $this->readRepositoryFile('tests/Documentation/QuickStart/OrderProcessingExample.php');
        $manifest = $this->compatibilityManifest();

        preg_match_all('/^use (Fight\\\\Common\\\\[^;]+);$/m', $fixture, $matches);
        $symbols = $matches[1];

        self::assertNotSame([], $symbols);
        foreach ($symbols as $symbol) {
            self::assertArrayHasKey($symbol, $manifest, sprintf('Quick Start symbol is absent from the public compatibility manifest: %s', $symbol));
            self::assertSame('public', $manifest[$symbol]['classification']);
        }

        $operationsBySymbol = [
            'Fight\\Common\\Adapter\\Messaging\\Command\\Sync\\Routing\\InMemoryCommandRouter' => ['constructible', 'callable'],
            'Fight\\Common\\Adapter\\Messaging\\Command\\Sync\\RoutingCommandBus' => ['constructible', 'callable'],
            'Fight\\Common\\Adapter\\Messaging\\Event\\Sync\\SimpleEventDispatcher' => ['constructible', 'callable'],
            'Fight\\Common\\Application\\Messaging\\Command\\CommandBus' => ['callable', 'implementable'],
            'Fight\\Common\\Application\\Messaging\\Command\\CommandHandler' => ['callable', 'implementable'],
            'Fight\\Common\\Application\\Messaging\\Event\\EventDispatcher' => ['callable', 'implementable'],
            'Fight\\Common\\Application\\Messaging\\Event\\EventDispatchFailed' => ['callable', 'constructible'],
            'Fight\\Common\\Application\\Messaging\\Event\\EventSubscriber' => ['callable', 'implementable'],
            'Fight\\Common\\Application\\Repository\\TransactionalUnitOfWork' => ['callable', 'implementable'],
            'Fight\\Common\\Domain\\Exception\\DomainException' => ['callable', 'constructible', 'extensible'],
            'Fight\\Common\\Domain\\Messaging\\Command\\Command' => ['callable', 'implementable'],
            'Fight\\Common\\Domain\\Messaging\\Command\\CommandMessage' => ['callable', 'constructible'],
            'Fight\\Common\\Domain\\Messaging\\Event\\Event' => ['callable', 'implementable'],
            'Fight\\Common\\Domain\\Messaging\\Event\\EventMessage' => ['callable', 'constructible'],
        ];

        self::assertSame([], array_values(array_diff($symbols, array_keys($operationsBySymbol))));

        foreach ($operationsBySymbol as $symbol => $operations) {
            self::assertArrayHasKey($symbol, $manifest);
            foreach ($operations as $operation) {
                self::assertTrue(
                    $manifest[$symbol]['operations'][$operation]['promised'],
                    sprintf('Quick Start operation is not public in the compatibility manifest: %s (%s)', $symbol, $operation),
                );
            }
        }
    }

    /** @return array<string, array{classification: string, operations: array<string, array{promised: bool}>}> */
    private function compatibilityManifest(): array
    {
        try {
            $decoded = json_decode($this->readRepositoryFile('compatibility/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }

        self::assertIsArray($decoded);
        self::assertArrayHasKey('declarations', $decoded);
        self::assertIsArray($decoded['declarations']);

        $manifest = [];
        foreach ($decoded['declarations'] as $declaration) {
            self::assertIsArray($declaration);
            self::assertArrayHasKey('name', $declaration);
            self::assertIsString($declaration['name']);
            self::assertArrayHasKey('classification', $declaration);
            self::assertIsString($declaration['classification']);
            self::assertArrayHasKey('operations', $declaration);
            self::assertIsArray($declaration['operations']);

            $operations = [];
            foreach ($declaration['operations'] as $operation => $details) {
                self::assertIsString($operation);
                self::assertIsArray($details);
                self::assertArrayHasKey('promised', $details);
                self::assertIsBool($details['promised']);
                $operations[$operation] = ['promised' => $details['promised']];
            }

            $manifest[$declaration['name']] = [
                'classification' => $declaration['classification'],
                'operations' => $operations,
            ];
        }

        return $manifest;
    }

    private function readRepositoryFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        self::assertIsString($contents);

        return $contents;
    }

    private function fixtureRegion(string $fixture, string $name): string
    {
        $matched = preg_match(
            sprintf('/\/\/ --8<-- \\[start:%s\\]\\R(.*?)\/\/ --8<-- \\[end:%s\\]/s', preg_quote($name, '/'), preg_quote($name, '/')),
            $fixture,
            $matches,
        );

        self::assertSame(1, $matched);
        self::assertArrayHasKey(1, $matches);

        return $matches[1];
    }
}
