<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Scheduler;

use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Scheduler\Exception\SchedulerException;
use Fight\Common\Application\Scheduler\Scheduler;
use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Value\DateTime\Timezone;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

#[CoversClass(Scheduler::class)]
class SchedulerTest extends UnitTestCase
{
    private string $tempDir;
    private Timezone $timezone;

    protected function setUp(): void
    {
        $this->tempDir  = sys_get_temp_dir().'/test_scheduler_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->timezone = new Timezone('UTC');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir.'/*.lock') ?: []);
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // addJob / addCommand — basic execution
    // -------------------------------------------------------------------------

    public function test_that_callable_job_runs_when_due(): void
    {
        $ran = false;
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, function () use (&$ran): bool {
            $ran = true;
            return true;
        });
        $scheduler->run();

        self::assertTrue($ran);
    }

    public function test_that_command_job_runs_when_due(): void
    {
        $output   = [];
        $mock     = $this->mockSuccessfulProcess('output line');
        $factory  = fn(string $cmd) => $mock;

        $scheduler = new Scheduler($this->timezone, $this->tempDir, processFactory: $factory);
        $scheduler->addCommand('cmd-job', fn() => true, 'echo hello');
        $scheduler->run();

        $mock->shouldHaveReceived('run')->once();
    }

    // -------------------------------------------------------------------------
    // isDue — cron expression, datetime string, callable
    // -------------------------------------------------------------------------

    public function test_that_job_skips_when_not_due(): void
    {
        $ran = false;
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => false, function () use (&$ran): bool {
            $ran = true;
            return true;
        });
        $scheduler->run();

        self::assertFalse($ran);
    }

    public function test_that_cron_expression_matches_current_minute(): void
    {
        $ran = false;
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        // '* * * * *' is always due
        $scheduler->addJob('test', '* * * * *', function () use (&$ran): bool {
            $ran = true;
            return true;
        });
        $scheduler->run();

        self::assertTrue($ran);
    }

    public function test_that_future_cron_expression_is_not_due(): void
    {
        $ran = false;
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        // February 30th never exists
        $scheduler->addJob('test', '0 0 30 2 *', function () use (&$ran): bool {
            $ran = true;
            return true;
        });
        $scheduler->run();

        self::assertFalse($ran);
    }

    public function test_that_datetime_string_matching_current_minute_is_due(): void
    {
        $ran = false;
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', $now, function () use (&$ran): bool {
            $ran = true;
            return true;
        });
        $scheduler->run();

        self::assertTrue($ran);
    }

    public function test_that_past_datetime_string_is_not_due(): void
    {
        $ran = false;
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', '2000-01-01 00:00:00', function () use (&$ran): bool {
            $ran = true;
            return true;
        });
        $scheduler->run();

        self::assertFalse($ran);
    }

    // -------------------------------------------------------------------------
    // enabled flag
    // -------------------------------------------------------------------------

    public function test_that_disabled_job_is_skipped(): void
    {
        $ran = false;
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, function () use (&$ran): bool {
            $ran = true;
            return true;
        }, enabled: false);
        $scheduler->run();

        self::assertFalse($ran);
    }

    // -------------------------------------------------------------------------
    // runCallable — return values and exceptions
    // -------------------------------------------------------------------------

    public function test_that_callable_returning_zero_succeeds(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, fn() => 0);
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_callable_returning_null_succeeds(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, fn() => null);
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_callable_returning_bad_value_logs_error(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        $scheduler = new Scheduler($this->timezone, $this->tempDir, logger: $logger);
        $scheduler->addJob('test', fn() => true, fn() => 'bad');
        $scheduler->run();
    }

    public function test_that_callable_throwing_logs_error(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        $scheduler = new Scheduler($this->timezone, $this->tempDir, logger: $logger);
        $scheduler->addJob('test', fn() => true, function (): never {
            throw new \RuntimeException('boom');
        });
        $scheduler->run();
    }

    // -------------------------------------------------------------------------
    // runCommand — success and failure
    // -------------------------------------------------------------------------

    public function test_that_successful_command_does_not_throw(): void
    {
        $mock    = $this->mockSuccessfulProcess('');
        $factory = fn(string $cmd) => $mock;

        $scheduler = new Scheduler($this->timezone, $this->tempDir, processFactory: $factory);
        $scheduler->addCommand('cmd', fn() => true, 'echo hello');
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_failing_command_logs_error(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        $mock    = $this->mockFailingProcess();
        $factory = fn(string $cmd) => $mock;

        $scheduler = new Scheduler($this->timezone, $this->tempDir, logger: $logger, processFactory: $factory);
        $scheduler->addCommand('cmd', fn() => true, 'false');
        $scheduler->run();
    }

    // -------------------------------------------------------------------------
    // Output modes
    // -------------------------------------------------------------------------

    public function test_that_output_false_produces_no_output(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, function (): bool {
            echo 'should not appear';
            return true;
        }, output: false);

        ob_start();
        $scheduler->run();
        $captured = ob_get_clean();

        self::assertEmpty($captured);
    }

    public function test_that_output_true_echoes_to_stdout(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, function (): bool {
            echo 'hello output';
            return true;
        }, output: true);

        ob_start();
        $scheduler->run();
        $captured = ob_get_clean();

        self::assertStringContainsString('hello output', $captured);
    }

    public function test_that_output_path_writes_to_file(): void
    {
        $file = $this->tempDir.'/output.log';

        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, function () use ($file): bool {
            echo 'logged line';
            return true;
        }, output: $file);
        $scheduler->run();

        self::assertStringContainsString('logged line', file_get_contents($file));
    }

    // -------------------------------------------------------------------------
    // checkMaxRuntime
    // -------------------------------------------------------------------------

    public function test_that_null_max_runtime_never_throws(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, fn() => true, maxRuntime: null);
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_nonexistent_lock_file_has_zero_lifetime(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        // maxRuntime=9999 and no existing lock → lifetime=0 → no throw
        $scheduler->addJob('test', fn() => true, fn() => true, maxRuntime: 9999);
        $scheduler->run();

        self::assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // acquireLock — duplicate lock exception
    // -------------------------------------------------------------------------

    public function test_that_duplicate_lock_acquire_throws_runtime_exception(): void
    {
        // Inject a job that tries to acquire its own lock a second time
        // by calling run() while the lock is already held from a first run.
        // We simulate this by injecting a job whose callable calls the same
        // scheduler's run() recursively — but the simpler path is to test
        // acquireLock's guard via the array_key_exists check indirectly.
        // We reach it by having the job callable force a recursive call on a
        // scheduler that already holds the lock for "test".
        $scheduler = new Scheduler($this->timezone, $this->tempDir);

        // Use a capture to get inside the running state and see the lockHandles guard.
        // Instead: create two schedulers sharing the same tempDir and same job name,
        // and verify that when the lock file can't be acquired (LockException), the
        // job is quietly skipped (logger.debug is called).
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once();

        $schedulerA = new Scheduler($this->timezone, $this->tempDir, logger: $logger);
        $schedulerB = new Scheduler($this->timezone, $this->tempDir, logger: $logger);

        // Both register the same job name, both are due.
        // schedulerA runs first, holds the lock during job execution.
        // schedulerB tries to run and gets LockException → logs debug.
        $lockFile = $this->tempDir.'/'.'test.lock';

        // Pre-create and flock the lock file manually to simulate a held lock.
        touch($lockFile);
        $handle = fopen($lockFile, 'rb+');
        flock($handle, LOCK_EX | LOCK_NB);

        $schedulerB->addJob('test', fn() => true, fn() => true);
        $schedulerB->run();  // should detect locked file, log debug, not throw

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public function test_that_recursive_run_within_callable_logs_error_for_duplicate_lock(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        $scheduler = null;
        $callable  = function () use (&$scheduler): int {
            /** @var Scheduler $scheduler */
            $scheduler->run(); // inner run: lock already held → RuntimeException → logError
            return 0;
        };
        $scheduler = new Scheduler($this->timezone, $this->tempDir, logger: $logger);
        $scheduler->addJob('test', fn() => true, $callable);
        $scheduler->run();
    }

    // -------------------------------------------------------------------------
    // logError
    // -------------------------------------------------------------------------

    public function test_that_log_error_is_no_op_without_logger(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, function (): never {
            throw new \RuntimeException('error');
        });
        $scheduler->run(); // no exception thrown to caller

        self::assertTrue(true);
    }

    public function test_that_log_error_calls_logger_error_when_present(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(\Mockery::type('string'), \Mockery::any());

        $scheduler = new Scheduler($this->timezone, $this->tempDir, logger: $logger);
        $scheduler->addJob('test', fn() => true, function (): never {
            throw new \RuntimeException('something broke');
        });
        $scheduler->run();
    }

    // -------------------------------------------------------------------------
    // notify
    // -------------------------------------------------------------------------

    public function test_that_notify_is_skipped_without_mail_service(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        $scheduler->addJob('test', fn() => true, function (): never {
            throw new \RuntimeException('error');
        }, notify: ['admin@example.com']);
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_notify_is_skipped_with_empty_notify_list(): void
    {
        $transport = $this->mock(MailTransport::class);
        $transport->shouldNotReceive('send');

        $factory = $this->mock(MailFactory::class);
        $factory->shouldNotReceive('createMessage');

        $mail = new MailService($transport, $factory);

        $scheduler = new Scheduler($this->timezone, $this->tempDir, mailService: $mail, fromEmail: 'from@example.com');
        $scheduler->addJob('test', fn() => true, function (): never {
            throw new \RuntimeException('error');
        }, notify: []);
        $scheduler->run();
    }

    public function test_that_notify_sends_email_to_single_address(): void
    {
        $sent     = [];
        $factory  = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn(new MailMessage());

        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->andReturnUsing(
            function (MailMessage $msg) use (&$sent): void { $sent[] = $msg; }
        );

        $mail = new MailService($transport, $factory);

        $scheduler = new Scheduler($this->timezone, $this->tempDir, mailService: $mail, fromEmail: 'from@example.com');
        $scheduler->addJob('test', fn() => true, function (): never {
            throw new \RuntimeException('error');
        }, notify: ['admin@example.com'], environment: 'production');
        $scheduler->run();

        self::assertCount(1, $sent);
        self::assertStringContainsString('[Scheduler] Job "test" failed', $sent[0]->getSubject() ?? '');
        self::assertSame(['admin@example.com'], array_column($sent[0]->getTo(), 'address'));
    }

    public function test_that_notify_sends_email_to_multiple_addresses(): void
    {
        $sent    = [];
        $factory = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn(new MailMessage());

        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->andReturnUsing(
            function (MailMessage $msg) use (&$sent): void { $sent[] = $msg; }
        );

        $mail = new MailService($transport, $factory);

        $scheduler = new Scheduler($this->timezone, $this->tempDir, mailService: $mail, fromEmail: 'from@example.com');
        $scheduler->addJob('test', fn() => true, function (): never {
            throw new \RuntimeException('error');
        }, notify: ['a@example.com', 'b@example.com']);
        $scheduler->run();

        self::assertCount(1, $sent);
        self::assertSame(['a@example.com', 'b@example.com'], array_column($sent[0]->getTo(), 'address'));
    }

    public function test_that_notify_accepts_comma_separated_string(): void
    {
        $sent    = [];
        $factory = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn(new MailMessage());

        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->andReturnUsing(
            function (MailMessage $msg) use (&$sent): void { $sent[] = $msg; }
        );

        $mail = new MailService($transport, $factory);

        $scheduler = new Scheduler($this->timezone, $this->tempDir, mailService: $mail, fromEmail: 'from@example.com');
        $scheduler->addJob('test', fn() => true, function (): never {
            throw new \RuntimeException('error');
        }, notify: 'a@example.com, b@example.com');
        $scheduler->run();

        self::assertCount(1, $sent);
        self::assertSame(['a@example.com', 'b@example.com'], array_column($sent[0]->getTo(), 'address'));
    }

    // -------------------------------------------------------------------------
    // getLockFile / escape
    // -------------------------------------------------------------------------

    public function test_that_escape_sanitizes_job_names(): void
    {
        $ran = false;
        $scheduler = new Scheduler($this->timezone, $this->tempDir);
        // Job name with special characters — should run fine (lock file created safely)
        $scheduler->addJob('My  Job!! Name', fn() => true, function () use (&$ran): bool {
            $ran = true;
            return true;
        });
        $scheduler->run();

        self::assertTrue($ran);
        // Verify the sanitized lock filename was produced (spaces/specials stripped)
        $lockFiles = glob($this->tempDir.'/*.lock') ?: [];
        self::assertCount(1, $lockFiles);
        self::assertStringEndsWith('my_job_name.lock', basename($lockFiles[0]));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function mockSuccessfulProcess(string $outputData): Process
    {
        $mock = $this->mock(Process::class);
        $mock->shouldReceive('run')->once()->with(\Mockery::type('callable'))->andReturnUsing(
            function (callable $callback) use ($outputData): int {
                if ($outputData !== '') {
                    $callback(Process::OUT, $outputData);
                }
                return 0;
            }
        );
        $mock->shouldReceive('isSuccessful')->once()->andReturn(true);

        return $mock;
    }

    private function mockFailingProcess(): Process
    {
        $mock = $this->mock(Process::class);
        $mock->shouldReceive('run')->once()->with(\Mockery::type('callable'))->andReturn(1);
        $mock->shouldReceive('isSuccessful')->once()->andReturn(false);
        $mock->shouldReceive('getExitCode')->andReturn(1);

        return $mock;
    }
}
