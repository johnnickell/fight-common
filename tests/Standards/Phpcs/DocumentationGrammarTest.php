<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DocumentationGrammarTest extends TestCase
{
    public function test_that_consumers_receive_the_builtin_documentation_conventions(): void
    {
        $result = $this->runPhpcs('BuiltinDocumentation.consumer.inc');

        self::assertNotSame(0, $result->getExitCode(), $result->getErrorOutput().$result->getOutput());

        $report = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $sources = array_column(array_values($report['files'])[0]['messages'], 'source');

        self::assertContains('Squiz.Commenting.ClassComment.Missing', $sources);
    }

    public function test_that_consumers_receive_documentation_grammar_from_the_fight_common_standard(): void
    {
        $process = $this->runPhpcs('DocumentationGrammar.consumer.inc');

        self::assertNotSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());

        $report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $sources = array_column(array_values($report['files'])[0]['messages'], 'source');

        self::assertContains('Phpcs.Commenting.RequireTypeDocComment.MissingDocComment', $sources);
        self::assertContains('Phpcs.Commenting.RequireMethodDocComment.MissingDocComment', $sources);
    }

    private function runPhpcs(string $fixture): Process
    {
        $root = dirname(__DIR__, 3);
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/phpcs',
            '--standard='.$root.'/src/Standards/Phpcs/ruleset.xml',
            '--report=json',
            __DIR__.'/'.$fixture,
        ], $root);
        $process->run();

        return $process;
    }
}
