<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class MechanicalConventionsTest extends TestCase
{
    public function test_that_consumers_receive_the_mechanical_conventions_from_the_fight_common_standard(): void
    {
        $result = $this->runPhpcs('MechanicalConventions.noncompliant.inc');

        self::assertSame(1, $result->getExitCode(), $result->getErrorOutput());

        $report = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $messages = array_values($report['files'])[0]['messages'];
        $sources = array_column($messages, 'source');

        self::assertContains('FightCommon.Arrays.DisallowTrailingArrayComma.DisallowTrailingArrayComma', $sources);
        self::assertContains('FightCommon.Arrays.RequireAlignedArrayArrow.ArrowNotAligned', $sources);
        self::assertContains('FightCommon.Formatting.RequireBlankLineBeforeReturn.Missing', $sources);
        self::assertContains('SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses.IncorrectlyOrderedUses', $sources);

        $compliant = $this->runPhpcs('MechanicalConventions.compliant.inc');

        self::assertSame(0, $compliant->getExitCode(), $compliant->getErrorOutput().$compliant->getOutput());
    }

    private function runPhpcs(string $fixture): Process
    {
        $root = dirname(__DIR__, 3);
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/phpcs',
            '--standard='.$root.'/src/Standards/Phpcs/FightCommon/ruleset.xml',
            '--report=json',
            __DIR__.'/'.$fixture,
        ], $root);
        $process->run();

        return $process;
    }
}
