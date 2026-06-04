<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Process;

use Fight\Common\Application\Process\Process;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Process::class)]
class ProcessTest extends UnitTestCase
{
    public function test_that_command_is_required_and_defaults_are_applied(): void
    {
        $process = new Process('echo hello');

        self::assertSame('echo hello', $process->command());
        self::assertNull($process->directory());
        self::assertNull($process->environment());
        self::assertNull($process->input());
        self::assertSame(60.0, $process->timeout());
        self::assertNull($process->stdout());
        self::assertNull($process->stderr());
        self::assertFalse($process->isOutputDisabled());
    }

    public function test_that_all_properties_can_be_set(): void
    {
        $stdout = static function (string $data): void {};
        $stderr = static function (string $data): void {};

        $process = new Process(
            command: 'php artisan migrate',
            directory: '/var/www',
            environment: ['APP_ENV' => 'production'],
            input: 'y',
            timeout: 120.0,
            stdout: $stdout,
            stderr: $stderr,
            outputDisabled: true
        );

        self::assertSame('php artisan migrate', $process->command());
        self::assertSame('/var/www', $process->directory());
        self::assertSame(['APP_ENV' => 'production'], $process->environment());
        self::assertSame('y', $process->input());
        self::assertSame(120.0, $process->timeout());
        self::assertSame($stdout, $process->stdout());
        self::assertSame($stderr, $process->stderr());
        self::assertTrue($process->isOutputDisabled());
    }

    public function test_that_timeout_can_be_null(): void
    {
        $process = new Process('sleep 1', timeout: null);

        self::assertNull($process->timeout());
    }
}
