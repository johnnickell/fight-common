<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs;

use PHPUnit\Framework\Attributes\CoversNothing;
use Fight\Test\Common\TestCase\UnitTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class ConsumerContractTest extends UnitTestCase
{
    public function test_that_a_distributed_standard_resolves_its_consumer_dependencies(): void
    {
        $root = dirname(__DIR__, 3);
        $consumer = sys_get_temp_dir().'/fight-common-dist-consumer-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mkdir([
            $consumer.'/src',
            $consumer.'/vendor/johnnickell/fight-common/src',
            $consumer.'/vendor/slevomat',
            $consumer.'/vendor/squizlabs',
        ]);
        $filesystem->mirror(
            $root.'/src/Standards',
            $consumer.'/vendor/johnnickell/fight-common/src/Standards',
        );
        $filesystem->mirror($root.'/vendor/slevomat/coding-standard', $consumer.'/vendor/slevomat/coding-standard');
        $filesystem->mirror($root.'/vendor/squizlabs/php_codesniffer', $consumer.'/vendor/squizlabs/php_codesniffer');

        try {
            file_put_contents($consumer.'/vendor/autoload.php', <<<'PHP'
                <?php

                spl_autoload_register(static function (string $class): void {
                    $prefix = 'Fight\\Common\\';

                    if (!str_starts_with($class, $prefix)) {
                        return;
                    }

                    $path = __DIR__.'/johnnickell/fight-common/src/'
                        .str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                    if (is_file($path)) {
                        require $path;
                    }
                });
                PHP);
            file_put_contents($consumer.'/src/UnsortedImports.php', <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace Consumer;

                use Zebra\Example;
                use Alpha\Example as AlphaExample;

                final class UnsortedImports
                {
                    public function example(): AlphaExample|Example
                    {
                        return new AlphaExample();
                    }
                }
                PHP."\n");
            file_put_contents($consumer.'/phpcs.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <ruleset name="DistConsumer">
                    <arg name="sniffs" value="SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses" />
                    <file>src</file>
                    <rule ref="./vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml" />
                </ruleset>
                XML);

            $scan = new Process([
                PHP_BINARY,
                $consumer.'/vendor/squizlabs/php_codesniffer/bin/phpcs',
                '--report=json',
                '--standard=phpcs.xml',
            ], $consumer);
            $scan->run();

            self::assertSame(1, $scan->getExitCode(), $scan->getOutput().$scan->getErrorOutput());
            $report = json_decode($scan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(
                ['SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses.IncorrectlyOrderedUses'],
                array_column(array_values($report['files'])[0]['messages'], 'source'),
            );
        } finally {
            $filesystem->remove($consumer);
        }
    }

    public function test_that_an_installed_consumer_can_load_the_public_standard_and_override_a_sniff_property(): void
    {
        $root = dirname(__DIR__, 3);
        $consumer = sys_get_temp_dir().'/fight-common-consumer-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mkdir([$consumer.'/src', $consumer.'/vendor/johnnickell']);
        $filesystem->symlink($root, $consumer.'/vendor/johnnickell/fight-common');

        try {
            file_put_contents($consumer.'/src/Accepted.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace Consumer;\n\n/**\n * Class Accepted\n */\nfinal class Accepted\n{\n}\n");
            file_put_contents($consumer.'/phpcs.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <ruleset name="Consumer">
                    <file>src</file>
                    <rule ref="./vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml">
                        <exclude name="Phpcs.Commenting.RequireTypeDocComment" />
                    </rule>
                </ruleset>
                XML);

            $scan = new Process([$root.'/vendor/bin/phpcs', '--report=json', '--standard=phpcs.xml'], $consumer);
            $scan->run();

            self::assertSame(0, $scan->getExitCode(), $scan->getOutput().$scan->getErrorOutput());
        } finally {
            $filesystem->remove($consumer);
        }
    }
}
