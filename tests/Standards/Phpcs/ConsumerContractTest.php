<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class ConsumerContractTest extends UnitTestCase
{
    public function test_that_builtin_documentation_rule_exclusions_remain_compatible(): void
    {
        $ruleset = simplexml_load_file(
            dirname(__DIR__, 3).'/src/Standards/Phpcs/ruleset.xml',
        );
        self::assertNotFalse($ruleset);
        self::assertSame('FightCommon', (string) $ruleset['name']);
        self::assertSame('Fight\Common\Standards\Phpcs', (string) $ruleset['namespace']);

        $expectedExclusions = [
            'Squiz.Commenting.ClassComment' => [
                'Squiz.Commenting.ClassComment.TagNotAllowed',
            ],
            'Squiz.Commenting.FunctionComment' => [
                'Squiz.Commenting.FunctionComment.MissingParamComment',
                'Squiz.Commenting.FunctionComment.MissingParamName',
                'Squiz.Commenting.FunctionComment.MissingParamTag',
                'Squiz.Commenting.FunctionComment.MissingReturn',
                'Squiz.Commenting.FunctionComment.MissingReturnTag',
                'Squiz.Commenting.FunctionComment.ParamCommentFullStop',
                'Squiz.Commenting.FunctionComment.ParamCommentNotCapital',
                'Squiz.Commenting.FunctionComment.SpacingAfterParamName',
                'Squiz.Commenting.FunctionComment.SpacingAfterParamType',
                'Squiz.Commenting.FunctionComment.ThrowsNoFullStop',
                'Squiz.Commenting.FunctionComment.ThrowsNotCapital',
                'Squiz.Commenting.FunctionComment.EmptyThrows',
                'Squiz.Commenting.FunctionComment.ThrowsNotForType',
            ],
            'Squiz.Commenting.FunctionCommentThrowTag' => [
                'Squiz.Commenting.FunctionCommentThrowTag.WrongType',
                'Squiz.Commenting.FunctionCommentThrowTag.Missing',
            ],
            'Generic.Commenting.DocComment' => [
                'Generic.Commenting.DocComment.TagValueIndent',
                'Generic.Commenting.DocComment.MissingShort',
                'Generic.Commenting.DocComment.ContentAfterOpen',
                'Generic.Commenting.DocComment.ContentBeforeClose',
            ],
        ];

        foreach ($expectedExclusions as $ruleName => $expected) {
            $rules = $ruleset->xpath(sprintf('rule[@ref="%s"]', $ruleName));
            self::assertIsArray($rules);
            self::assertCount(1, $rules);
            self::assertSame(
                $expected,
                array_map(
                    static fn (\SimpleXMLElement $exclude): string => (string) $exclude['name'],
                    iterator_to_array($rules[0]->exclude, false),
                ),
            );
        }
    }

    public function test_that_a_dist_installed_standard_resolves_slevomat_from_the_consumer_vendor(): void
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
        $filesystem->mirror(
            $root.'/vendor/slevomat/coding-standard',
            $consumer.'/vendor/slevomat/coding-standard',
        );
        $filesystem->mirror(
            $root.'/vendor/squizlabs/php_codesniffer',
            $consumer.'/vendor/squizlabs/php_codesniffer',
        );

        try {
            file_put_contents($consumer.'/vendor/autoload.php', <<<'PHP'
                <?php

                spl_autoload_register(static function (string $class): void {
                    $prefix = 'Fight\\Common\\';

                    if (str_starts_with($class, $prefix) === false) {
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
                'php',
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

    public function test_that_an_installed_consumer_can_explicitly_configure_the_public_standard(): void
    {
        $root = dirname(__DIR__, 3);
        $consumer = sys_get_temp_dir().'/fight-common-consumer-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mkdir([$consumer.'/selected', $consumer.'/src/Excluded', $consumer.'/vendor/johnnickell']);
        $filesystem->symlink($root, $consumer.'/vendor/johnnickell/fight-common');

        try {
            file_put_contents($consumer.'/src/Accepted.php', <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace Consumer;

                /**
                 * Class Accepted
                 */
                final class Accepted
                {
                    /**
                     * Returns the answer
                     */
                    public function answer(): int
                    {
                        return 42;
                    }
                }
                PHP."\n");
            file_put_contents($consumer.'/src/Excluded/Ignored.php', "<?php\nreturn [1,];\n");
            file_put_contents($consumer.'/phpcs.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <ruleset name="Consumer">
                    <file>src</file>
                    <exclude-pattern>src/Excluded/*</exclude-pattern>
                    <rule ref="./vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml">
                        <exclude name="Phpcs.Commenting.RequireMethodDocComment" />
                    </rule>
                    <rule ref="Phpcs.Commenting.RequireTypeDocComment">
                        <properties>
                            <property name="strict" value="false" />
                        </properties>
                    </rule>
                </ruleset>
                XML);

            $scan = new Process([$root.'/vendor/bin/phpcs', '--report=json', '--standard=phpcs.xml'], $consumer);
            $scan->run();

            self::assertSame(0, $scan->getExitCode(), $scan->getOutput().$scan->getErrorOutput());

            file_put_contents($consumer.'/selected/Selected.php', "<?php\nfinal class Selected { public function answer(){return [1,];}}\n");
            file_put_contents($consumer.'/phpcs-selected.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <ruleset name="Selected">
                    <arg name="sniffs" value="Phpcs.Files.RequireStrictTypes" />
                    <file>selected</file>
                    <rule ref="./vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml" />
                </ruleset>
                XML);
            $selected = new Process([
                $root.'/vendor/bin/phpcs',
                '--report=json',
                '--standard=phpcs-selected.xml',
            ], $consumer);
            $selected->run();
            $selectedReport = json_decode($selected->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $selectedMessages = array_values($selectedReport['files'])[0]['messages'];

            self::assertNotSame(0, $selected->getExitCode());
            self::assertSame(
                ['Phpcs.Files.RequireStrictTypes.Missing'],
                array_column($selectedMessages, 'source'),
            );

            $listing = new Process([
                $root.'/vendor/bin/phpcs',
                '-e',
                '--standard='.$consumer.'/vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml',
            ], $consumer);
            $listing->mustRun();
            $enabledSniffs = [
                'Phpcs.Arrays.DisallowTrailingArrayComma',
                'Phpcs.Arrays.RequireAlignedArrayArrow',
                'Phpcs.Classes.NamedClassMemberSpacing',
                'Phpcs.Classes.NamedClassStructure',
                'Phpcs.Classes.NamedMethodSpacing',
                'Phpcs.Commenting.RequireMethodDocComment',
                'Phpcs.Commenting.RequireTypeDocComment',
                'Phpcs.Files.RequireStrictTypes',
                'Phpcs.Formatting.RequireBlankLineBeforeReturn',
                'Phpcs.Formatting.RequireVisibilityGroupSpacing',
            ];
            $builtinDocumentationSniffs = [
                'Generic.Commenting.DocComment',
                'Squiz.Commenting.ClassComment',
                'Squiz.Commenting.FunctionComment',
                'Squiz.Commenting.FunctionCommentThrowTag',
            ];

            foreach (array_merge($enabledSniffs, $builtinDocumentationSniffs) as $sniff) {
                self::assertStringContainsString($sniff, $listing->getOutput());
            }

            $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('library', $composer['type'] ?? 'library');
            self::assertSame(
                'Required to run the optional FightCommon PHP_CodeSniffer standard',
                $composer['suggest']['squizlabs/php_codesniffer'] ?? null,
            );
            self::assertSame(
                'Required by the optional FightCommon PHP_CodeSniffer standard',
                $composer['suggest']['slevomat/coding-standard'] ?? null,
            );
            self::assertFalse(
                $composer['config']['allow-plugins']['dealerdirect/phpcodesniffer-composer-installer'] ?? true,
            );

            $documentation = file_get_contents($root.'/docs/coding-standard.md');
            self::assertIsString($documentation);
            $ruleset = file_get_contents($root.'/src/Standards/Phpcs/ruleset.xml');
            self::assertIsString($ruleset);
            self::assertStringNotContainsString('<file>', $ruleset);
            self::assertStringNotContainsString('<exclude-pattern>', $ruleset);

            foreach ($enabledSniffs as $sniff) {
                self::assertStringContainsString($sniff, $documentation);
            }

            foreach ([
                'DisallowTrailingArrayComma',
                'ArrowNotAligned',
                'IncorrectCountOfBlankLinesBetweenMembers',
                'IncorrectGroupOrder',
                'IncorrectLinesCountBetweenMethods',
                'AmbiguousSummary',
                'InheritDocWithContent',
                'InvalidConstructorSummary',
                'MissingBlankLine',
                'MissingDocComment',
                'TerminalPunctuation',
                'UnapprovedVerb',
                'WrappedSummary',
                'IncorrectSummary',
                'Missing',
                'MissingBlankLineBetweenVisibilityGroups',
                'UnexpectedBlankLineWithinVisibilityGroup',
            ] as $diagnosticCode) {
                self::assertStringContainsString($diagnosticCode, $documentation);
            }

            foreach (['groups', 'linesCountBetweenMembers', 'minLinesCount', 'maxLinesCount', 'strict'] as $property) {
                self::assertStringContainsString($property, $documentation);
            }

            foreach ([
                'DocumentationComment',
                'MechanicalConventions.*.inc',
                'MemberLayout.*.inc',
                'DocumentationGrammar.*.inc',
                'Fight Common is the canonical implementation after T-00018 is accepted',
            ] as $parityEvidence) {
                self::assertStringContainsString($parityEvidence, $documentation);
            }

            file_put_contents($consumer.'/phpcs-properties.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <ruleset name="Properties">
                    <rule ref="./vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml" />
                    <rule ref="Phpcs.Classes.NamedClassStructure">
                        <properties>
                            <property name="groups" type="array">
                                <element value="uses" />
                                <element value="public methods" />
                            </property>
                        </properties>
                    </rule>
                    <rule ref="Phpcs.Classes.NamedClassMemberSpacing">
                        <properties>
                            <property name="linesCountBetweenMembers" value="2" />
                        </properties>
                    </rule>
                    <rule ref="Phpcs.Classes.NamedMethodSpacing">
                        <properties>
                            <property name="minLinesCount" value="0" />
                            <property name="maxLinesCount" value="2" />
                        </properties>
                    </rule>
                    <rule ref="Phpcs.Commenting.RequireTypeDocComment">
                        <properties>
                            <property name="strict" value="false" />
                        </properties>
                    </rule>
                </ruleset>
                XML);
            $propertyOverrides = new Process([
                $root.'/vendor/bin/phpcs',
                '-e',
                '--standard=phpcs-properties.xml',
            ], $consumer);

            $propertyOverrides->mustRun();
        } finally {
            $filesystem->remove($consumer);
        }
    }
}
