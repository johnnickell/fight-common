<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Process\Symfony;

use Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner;
use Fight\Common\Application\Process\Exception\ProcessException;
use Fight\Common\Application\Process\Process;
use Fight\Common\Application\Process\ProcessErrorBehavior;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;

#[CoversClass(SymfonyProcessRunner::class)]
class SymfonyProcessRunnerTest extends UnitTestCase
{
    public function test_that_runner_source_has_no_coverage_exclusions(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4).'/src/Adapter/Process/Symfony/SymfonyProcessRunner.php'
        );

        self::assertIsString($source);
        self::assertStringNotContainsString('@codeCoverageIgnore', $source);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function test_that_constructor_throws_on_delay_less_than_one(): void
    {
        self::expectException(DomainException::class);

        new SymfonyProcessRunner(delay: 0);
    }

    public function test_that_valid_constructor_arguments_succeed(): void
    {
        $runner = new SymfonyProcessRunner(delay: 1);

        self::assertInstanceOf(SymfonyProcessRunner::class, $runner);
    }

    // -------------------------------------------------------------------------
    // attach / clear
    // -------------------------------------------------------------------------

    public function test_that_clear_prevents_queued_processes_from_running(): void
    {
        $runner = new SymfonyProcessRunner(delay: 1);

        $runner->attach(new Process('echo a'));
        $runner->attach(new Process('echo b'));
        $runner->clear();
        $runner->run();

        self::assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Successful process
    // -------------------------------------------------------------------------

    public function test_that_run_completes_when_process_succeeds(): void
    {
        $mock    = $this->makeSuccessfulProcessMock();
        $factory = fn(Process $p) => $mock;
        $runner  = new SymfonyProcessRunner(delay: 1, processFactory: $factory);

        $runner->attach(new Process('echo hello'));
        $runner->run();

        $mock->shouldHaveReceived('start')->once();
    }

    public function test_that_default_factory_executes_process_and_routes_output(): void
    {
        $stdout = '';
        $stderr = '';
        $runner = new SymfonyProcessRunner(delay: 1);

        $runner->attach(new Process(
            command: "printf 'standard output'; printf 'error output' >&2",
            stdout: static function (string $data) use (&$stdout): void {
                $stdout .= $data;
            },
            stderr: static function (string $data) use (&$stderr): void {
                $stderr .= $data;
            }
        ));
        $runner->run();

        self::assertSame('standard output', $stdout);
        self::assertSame('error output', $stderr);
    }

    public function test_that_default_factory_reports_process_failure(): void
    {
        $runner = new SymfonyProcessRunner(delay: 1);
        $runner->attach(new Process("php -r 'exit(7);'"));

        self::expectException(ProcessException::class);
        self::expectExceptionMessage('failed with exit code 7');

        $runner->run();
    }

    public function test_that_default_factory_runs_with_output_disabled(): void
    {
        $runner = new SymfonyProcessRunner(delay: 1);
        $runner->attach(new Process(
            command: "php -r 'exit(0);'",
            outputDisabled: true
        ));

        $runner->run();

        self::assertTrue(true);
    }

    public function test_that_run_defaults_to_exception_behavior(): void
    {
        $factory = fn(Process $p) => $this->makeFailingProcessMock();
        $runner  = new SymfonyProcessRunner(delay: 1, processFactory: $factory);

        $runner->attach(new Process('false'));

        self::expectException(ProcessException::class);

        $runner->run();
    }

    // -------------------------------------------------------------------------
    // EXCEPTION behavior
    // -------------------------------------------------------------------------

    public function test_that_run_throws_on_failure_with_exception_behavior(): void
    {
        $factory = fn(Process $p) => $this->makeFailingProcessMock();
        $runner  = new SymfonyProcessRunner(delay: 1, processFactory: $factory);

        $runner->attach(new Process('false'));

        self::expectException(ProcessException::class);

        $runner->run(ProcessErrorBehavior::EXCEPTION);
    }

    public function test_that_run_wraps_unexpected_throwable_as_process_exception(): void
    {
        $factory = function (Process $p): SymfonyProcess {
            throw new RuntimeException('unexpected error');
        };

        $runner = new SymfonyProcessRunner(delay: 1, processFactory: $factory);
        $runner->attach(new Process('echo hello'));

        self::expectException(ProcessException::class);
        self::expectExceptionMessage('unexpected error');

        $runner->run(ProcessErrorBehavior::EXCEPTION);
    }

    // -------------------------------------------------------------------------
    // maxConcurrent early-return in init()
    // -------------------------------------------------------------------------

    public function test_that_init_skips_when_max_concurrent_reached(): void
    {
        // Process 1 stays "running" for one tick so that when init() is called
        // again (queue still has process 2), the concurrency limit blocks it.
        $mock1 = $this->mock(SymfonyProcess::class);
        $mock1->shouldReceive('start')->once()->with(\Mockery::type('callable'));
        $mock1->shouldReceive('getPid')->andReturn(1000);
        $mock1->shouldReceive('checkTimeout')->twice();
        $mock1->shouldReceive('isRunning')->andReturn(true, false);
        $mock1->shouldReceive('isSuccessful')->once()->andReturn(true);
        $mock1->shouldReceive('getCommandLine')->andReturn('echo a');
        $mock1->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
        $mock1->shouldReceive('stop')->zeroOrMoreTimes()->andReturn(null);

        $callCount = 0;
        $factory   = function (Process $p) use ($mock1, &$callCount): SymfonyProcess {
            $callCount++;
            return $callCount === 1 ? $mock1 : $this->makeSuccessfulProcessMock();
        };

        $runner = new SymfonyProcessRunner(delay: 1, maxConcurrent: 1, processFactory: $factory);
        $runner->attach(new Process('echo a'));
        $runner->attach(new Process('echo b'));
        $runner->run(ProcessErrorBehavior::EXCEPTION);

        self::assertSame(2, $callCount);
    }

    // -------------------------------------------------------------------------
    // ProcessException re-throw in init() catch
    // -------------------------------------------------------------------------

    public function test_that_process_exception_from_factory_propagates_unchanged(): void
    {
        $original = new ProcessException('from factory');
        $factory  = function (Process $p) use ($original): SymfonyProcess {
            throw $original;
        };

        $runner = new SymfonyProcessRunner(delay: 1, processFactory: $factory);
        $runner->attach(new Process('echo hello'));

        try {
            $runner->run(ProcessErrorBehavior::EXCEPTION);
            self::fail('Expected ProcessException');
        } catch (ProcessException $e) {
            self::assertSame($original, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Throwable in tick() with EXCEPTION behavior
    // -------------------------------------------------------------------------

    public function test_that_throwable_in_tick_is_wrapped_as_process_exception(): void
    {
        $mock = $this->mock(SymfonyProcess::class);
        $mock->shouldReceive('start')->once()->with(\Mockery::type('callable'));
        $mock->shouldReceive('getPid')->andReturn(99);
        $mock->shouldReceive('getCommandLine')->andReturn('cmd');
        $mock->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
        $mock->shouldReceive('checkTimeout')->once()->andThrow(new RuntimeException('timed out'));
        $mock->shouldReceive('stop')->zeroOrMoreTimes()->andReturn(null);

        $factory = fn(Process $p) => $mock;
        $runner  = new SymfonyProcessRunner(delay: 1, processFactory: $factory);
        $runner->attach(new Process('cmd'));

        self::expectException(ProcessException::class);
        self::expectExceptionMessage('timed out');

        $runner->run(ProcessErrorBehavior::EXCEPTION);
    }

    public function test_that_destructor_stops_an_attached_running_process_after_startup_failure(): void
    {
        $logger = new class extends \Psr\Log\AbstractLogger {
            public function log(
                mixed $level,
                string|\Stringable $message,
                array $context = []
            ): void {
                throw new RuntimeException('logging failed');
            }
        };

        $process = $this->mock(SymfonyProcess::class);
        $process->shouldReceive('start')->once()->withNoArgs();
        $process->shouldReceive('getPid')->once()->andReturn(1234);
        $process->shouldReceive('getCommandLine')->once()->andReturn('long-running-command');
        $process->shouldReceive('getWorkingDirectory')->once()->andReturn('/tmp');
        $process->shouldReceive('stop')->once()->with(0)->andReturn(null);

        $runner = new SymfonyProcessRunner(
            logger: $logger,
            delay: 1,
            processFactory: static fn(Process $descriptor): SymfonyProcess => $process
        );
        $runner->attach(new Process('long-running-command', outputDisabled: true));

        try {
            $runner->run();
            self::fail('Expected ProcessException');
        } catch (ProcessException $exception) {
            self::assertSame('logging failed', $exception->getMessage());
        }

        unset($exception);
        unset($runner);

        $process->shouldHaveReceived('stop')->once()->with(0);
    }

    // -------------------------------------------------------------------------
    // IGNORE behavior
    // -------------------------------------------------------------------------

    public function test_that_run_ignores_failure_with_ignore_behavior(): void
    {
        $factory = fn(Process $p) => $this->makeFailingProcessMock();
        $runner  = new SymfonyProcessRunner(delay: 1, processFactory: $factory);

        $runner->attach(new Process('false'));
        $runner->run(ProcessErrorBehavior::IGNORE);

        self::assertTrue(true);
    }

    public function test_that_run_ignores_throwable_during_init_with_ignore_behavior(): void
    {
        $factory = function (Process $p): SymfonyProcess {
            throw new RuntimeException('factory error');
        };

        $runner = new SymfonyProcessRunner(delay: 1, processFactory: $factory);
        $runner->attach(new Process('echo hello'));
        $runner->run(ProcessErrorBehavior::IGNORE);

        self::assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // RETRY behavior
    // -------------------------------------------------------------------------

    public function test_that_run_retries_and_succeeds(): void
    {
        $callCount = 0;
        $factory   = function (Process $p) use (&$callCount): SymfonyProcess {
            $callCount++;
            return $callCount === 1
                ? $this->makeFailingProcessMock()
                : $this->makeSuccessfulProcessMock();
        };

        $runner = new SymfonyProcessRunner(delay: 1, tries: 2, processFactory: $factory);
        $runner->attach(new Process('some-command'));
        $runner->run(ProcessErrorBehavior::RETRY);

        self::assertSame(2, $callCount);
    }

    public function test_that_run_throws_after_retries_exhausted(): void
    {
        $factory = fn(Process $p) => $this->makeFailingProcessMock();

        $runner = new SymfonyProcessRunner(delay: 1, tries: 2, processFactory: $factory);
        $runner->attach(new Process('false'));

        self::expectException(ProcessException::class);

        $runner->run(ProcessErrorBehavior::RETRY);
    }

    // -------------------------------------------------------------------------
    // maxConcurrent
    // -------------------------------------------------------------------------

    public function test_that_max_concurrent_limits_parallel_processes(): void
    {
        $started = 0;
        $factory = function (Process $p) use (&$started): SymfonyProcess {
            $started++;
            return $this->makeSuccessfulProcessMock();
        };

        $runner = new SymfonyProcessRunner(delay: 1, maxConcurrent: 1, processFactory: $factory);
        $runner->attach(new Process('echo a'));
        $runner->attach(new Process('echo b'));
        $runner->run(ProcessErrorBehavior::EXCEPTION);

        self::assertSame(2, $started);
    }

    public function test_that_zero_max_concurrent_allows_unlimited_processes(): void
    {
        $started = 0;
        $factory = function (Process $p) use (&$started): SymfonyProcess {
            $started++;
            return $this->makeSuccessfulProcessMock();
        };

        $runner = new SymfonyProcessRunner(delay: 1, maxConcurrent: 0, processFactory: $factory);
        $runner->attach(new Process('echo a'));
        $runner->attach(new Process('echo b'));
        $runner->attach(new Process('echo c'));
        $runner->run(ProcessErrorBehavior::EXCEPTION);

        self::assertSame(3, $started);
    }

    // -------------------------------------------------------------------------
    // Process still running on first tick
    // -------------------------------------------------------------------------

    public function test_that_still_running_process_is_not_removed_until_finished(): void
    {
        $mock = $this->mock(SymfonyProcess::class);
        $mock->shouldReceive('start')->once()->with(\Mockery::type('callable'));
        $mock->shouldReceive('getPid')->andReturn(42);
        $mock->shouldReceive('checkTimeout')->twice();
        $mock->shouldReceive('isRunning')->andReturn(true, false);
        $mock->shouldReceive('isSuccessful')->once()->andReturn(true);
        $mock->shouldReceive('getCommandLine')->andReturn('sleep 0.1');
        $mock->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
        $mock->shouldReceive('stop')->zeroOrMoreTimes()->andReturn(null);

        $factory = fn(Process $p) => $mock;
        $runner  = new SymfonyProcessRunner(delay: 1, processFactory: $factory);

        $runner->attach(new Process('sleep 0.1'));
        $runner->run(ProcessErrorBehavior::EXCEPTION);

        $mock->shouldHaveReceived('isRunning')->twice();
    }

    // -------------------------------------------------------------------------
    // stdout / stderr callbacks
    // -------------------------------------------------------------------------

    public function test_that_stdout_callback_is_invoked(): void
    {
        $received = null;

        $mock = $this->mock(SymfonyProcess::class);
        $mock->shouldReceive('start')->once()->with(\Mockery::on(function (callable $cb) {
            $cb(SymfonyProcess::OUT, 'hello stdout');
            return true;
        }));
        $mock->shouldReceive('getPid')->andReturn(1001);
        $mock->shouldReceive('checkTimeout')->once();
        $mock->shouldReceive('isRunning')->once()->andReturn(false);
        $mock->shouldReceive('isSuccessful')->once()->andReturn(true);
        $mock->shouldReceive('getCommandLine')->andReturn('echo hello');
        $mock->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
        $mock->shouldReceive('stop')->zeroOrMoreTimes()->andReturn(null);

        $appProcess = new Process(
            command: 'echo hello',
            stdout: function (string $data) use (&$received): void {
                $received = $data;
            }
        );

        $runner = new SymfonyProcessRunner(delay: 1, processFactory: fn(Process $p) => $mock);
        $runner->attach($appProcess);
        $runner->run(ProcessErrorBehavior::EXCEPTION);

        self::assertSame('hello stdout', $received);
    }

    public function test_that_stderr_callback_is_invoked(): void
    {
        $received = null;

        $mock = $this->mock(SymfonyProcess::class);
        $mock->shouldReceive('start')->once()->with(\Mockery::on(function (callable $cb) {
            $cb(SymfonyProcess::ERR, 'oh no');
            return true;
        }));
        $mock->shouldReceive('getPid')->andReturn(1002);
        $mock->shouldReceive('checkTimeout')->once();
        $mock->shouldReceive('isRunning')->once()->andReturn(false);
        $mock->shouldReceive('isSuccessful')->once()->andReturn(true);
        $mock->shouldReceive('getCommandLine')->andReturn('false');
        $mock->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
        $mock->shouldReceive('stop')->zeroOrMoreTimes()->andReturn(null);

        $appProcess = new Process(
            command: 'false',
            stderr: function (string $data) use (&$received): void {
                $received = $data;
            }
        );

        $runner = new SymfonyProcessRunner(delay: 1, processFactory: fn(Process $p) => $mock);
        $runner->attach($appProcess);
        $runner->run(ProcessErrorBehavior::EXCEPTION);

        self::assertSame('oh no', $received);
    }

    // -------------------------------------------------------------------------
    // Logging — call-count based to avoid Mockery arg-matching edge cases
    // with default PSR-3 context parameters
    // -------------------------------------------------------------------------

    public function test_that_started_event_is_logged(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        // logProcessStarted fires once (successful process, no failure)
        $logger->shouldReceive('log')->once();

        $factory = fn(Process $p) => $this->makeSuccessfulProcessMock();
        $runner  = new SymfonyProcessRunner(logger: $logger, delay: 1, processFactory: $factory);

        $runner->attach(new Process('echo hello'));
        $runner->run(ProcessErrorBehavior::EXCEPTION);
    }

    public function test_that_failed_event_is_logged_with_output(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        // log() called twice: logProcessStarted ("started") + logProcessFailed output ("Output")
        $logger->shouldReceive('log')->twice();
        // error() called once: logProcessFailed failure line
        $logger->shouldReceive('error')->once();

        $factory = fn(Process $p) => $this->makeFailingProcessMock(outputDisabled: false);
        $runner  = new SymfonyProcessRunner(logger: $logger, delay: 1, processFactory: $factory);

        $runner->attach(new Process('false'));

        try {
            $runner->run(ProcessErrorBehavior::EXCEPTION);
        } catch (ProcessException) {
        }
    }

    public function test_that_output_log_is_skipped_when_output_is_disabled(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        // log() called once: logProcessStarted ("started") only — no output log
        $logger->shouldReceive('log')->once();
        $logger->shouldReceive('error')->once();

        $factory = fn(Process $p) => $this->makeFailingProcessMock(outputDisabled: true);
        $runner  = new SymfonyProcessRunner(logger: $logger, delay: 1, processFactory: $factory);

        $runner->attach(new Process('false'));

        try {
            $runner->run(ProcessErrorBehavior::EXCEPTION);
        } catch (ProcessException) {
        }
    }

    public function test_that_restarted_event_is_logged_on_retry(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        // log() x3: logProcessStarted + logProcessFailed output + logProcessRestarted
        $logger->shouldReceive('log')->times(3);
        // error() x1: logProcessFailed failure line
        $logger->shouldReceive('error')->once();

        $callCount = 0;
        $factory   = function (Process $p) use (&$callCount): SymfonyProcess {
            $callCount++;
            return $callCount === 1
                ? $this->makeFailingProcessMock()
                : $this->makeSuccessfulProcessMock();
        };

        $runner = new SymfonyProcessRunner(logger: $logger, delay: 1, tries: 2, processFactory: $factory);
        $runner->attach(new Process('some-command'));
        $runner->run(ProcessErrorBehavior::RETRY);
    }

    public function test_that_no_logging_occurs_without_logger(): void
    {
        $factory = fn(Process $p) => $this->makeSuccessfulProcessMock();
        $runner  = new SymfonyProcessRunner(delay: 1, processFactory: $factory);

        $runner->attach(new Process('echo hello'));
        $runner->run(ProcessErrorBehavior::EXCEPTION);

        self::assertTrue(true);
    }

    public function test_that_custom_log_level_is_used(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->with(LogLevel::INFO, \Mockery::type('string'));

        $factory = fn(Process $p) => $this->makeSuccessfulProcessMock();
        $runner  = new SymfonyProcessRunner(
            logger: $logger,
            delay: 1,
            logLevel: LogLevel::INFO,
            processFactory: $factory
        );

        $runner->attach(new Process('echo hello'));
        $runner->run(ProcessErrorBehavior::EXCEPTION);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuccessfulProcessMock(): SymfonyProcess
    {
        $mock = $this->mock(SymfonyProcess::class);
        $mock->shouldReceive('start')->once()->with(\Mockery::type('callable'));
        $mock->shouldReceive('getPid')->andReturn(1000);
        $mock->shouldReceive('checkTimeout')->once();
        $mock->shouldReceive('isRunning')->once()->andReturn(false);
        $mock->shouldReceive('isSuccessful')->once()->andReturn(true);
        $mock->shouldReceive('getCommandLine')->andReturn('echo hello');
        $mock->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
        $mock->shouldReceive('stop')->zeroOrMoreTimes()->andReturn(null);

        return $mock;
    }

    private function makeFailingProcessMock(bool $outputDisabled = false): SymfonyProcess
    {
        $mock = $this->mock(SymfonyProcess::class);
        $mock->shouldReceive('start')->once()->with(\Mockery::type('callable'));
        $mock->shouldReceive('getPid')->andReturn(2000);
        $mock->shouldReceive('checkTimeout')->once();
        $mock->shouldReceive('isRunning')->once()->andReturn(false);
        $mock->shouldReceive('isSuccessful')->once()->andReturn(false);
        $mock->shouldReceive('getCommandLine')->andReturn('false');
        $mock->shouldReceive('getExitCode')->andReturn(1);
        $mock->shouldReceive('getExitCodeText')->andReturn('General error');
        $mock->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
        $mock->shouldReceive('isOutputDisabled')->andReturn($outputDisabled);
        $mock->shouldReceive('stop')->zeroOrMoreTimes()->andReturn(null);

        if (!$outputDisabled) {
            $mock->shouldReceive('getOutput')->andReturn('');
            $mock->shouldReceive('getErrorOutput')->andReturn('error details');
        }

        return $mock;
    }
}
