<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class MemberLayoutTest extends TestCase
{
    public function test_that_consumers_receive_the_member_layout_rules_from_the_fight_common_standard(): void
    {
        $result = $this->runPhpcs('MemberLayout.noncompliant.inc');

        self::assertNotSame(0, $result->getExitCode(), $result->getErrorOutput().$result->getOutput());

        $report = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $sources = array_column(array_values($report['files'])[0]['messages'], 'source');

        self::assertContains('Phpcs.Classes.NamedClassMemberSpacing.IncorrectCountOfBlankLinesBetweenMembers', $sources);
        self::assertContains('Phpcs.Classes.NamedClassStructure.IncorrectGroupOrder', $sources);
        self::assertContains('Phpcs.Classes.NamedMethodSpacing.IncorrectLinesCountBetweenMethods', $sources);
        self::assertContains(
            'Phpcs.Formatting.RequireVisibilityGroupSpacing.MissingBlankLineBetweenVisibilityGroups',
            $sources,
        );
        self::assertContains(
            'Phpcs.Formatting.RequireVisibilityGroupSpacing.UnexpectedBlankLineWithinVisibilityGroup',
            $sources,
        );
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
