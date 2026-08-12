<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Scheduler;

require_once __DIR__.'/RuntimeInspectionFunctionController.php';
require_once __DIR__.'/LockLifecycleFunctionController.php';

use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Process\Exception\ProcessException;
use Fight\Common\Application\Process\Process as ApplicationProcess;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Application\Scheduler\Exception\SchedulerException;
use Fight\Common\Application\Scheduler\Scheduler;
use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Value\DateTime\Timezone;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

#[CoversClass(Scheduler::class)]
class SchedulerTest extends UnitTestCase
{
    private string $tempDir;
    private Timezone $timezone;
    private ProcessRunner $processRunner;

    public function test_that_constructor_requires_process_runner_as_its_third_parameter(): void
    {
        $parameters = (new ReflectionMethod(Scheduler::class, '__construct'))->getParameters();

        self::assertSame('processRunner', $parameters[2]->getName());
        self::assertSame(ProcessRunner::class, (string) $parameters[2]->getType());
        self::assertFalse($parameters[2]->isOptional());
        self::assertFalse($parameters[2]->allowsNull());
    }

    public function test_that_scheduler_source_has_no_coverage_exclusions(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/src/Application/Scheduler/Scheduler.php');

        self::assertIsString($source);
        self::assertStringNotContainsString('@codeCoverageIgnore', $source);
    }

    protected function setUp(): void
    {
        LockLifecycleFunctionController::reset();
        RuntimeInspectionFunctionController::reset();
        $this->tempDir  = sys_get_temp_dir().'/test_scheduler_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->timezone = new Timezone('UTC');
        $this->processRunner = $this->mock(ProcessRunner::class);
    }

    protected function tearDown(): void
    {
        LockLifecycleFunctionController::reset();
        RuntimeInspectionFunctionController::reset();
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
        $scheduler->addJob('test', fn() => true, function () use (&$ran): bool {
            $ran = true;
            return true;
        });
        $scheduler->run();

        self::assertTrue($ran);
    }

    public function test_that_command_job_runs_when_due(): void
    {
        $runner = $this->mockProcessRunner();

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $runner);
        $scheduler->addCommand('cmd-job', fn() => true, 'echo hello');
        $scheduler->run();
    }

    public function test_that_command_job_builds_and_runs_process_while_routing_output(): void
    {
        $process = null;
        $runner = $this->mock(ProcessRunner::class);
        $runner->shouldReceive('attach')->once()->withArgs(
            function (ApplicationProcess $attachedProcess) use (&$process): bool {
                $process = $attachedProcess;

                return true;
            }
        );
        $runner->shouldReceive('run')->once()->andReturnUsing(
            function () use (&$process): void {
                /** @var ApplicationProcess $process */
                ($process->stdout())('standard output');
                ($process->stderr())('standard error');
            }
        );

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $runner);
        $scheduler->addCommand('cmd-job', fn() => true, 'printf "hello" && printf "error" >&2', output: true);

        ob_start();
        $scheduler->run();
        $output = ob_get_clean();

        self::assertSame('printf "hello" && printf "error" >&2', $process?->command());
        self::assertSame("standard output\nstandard error\n", $output);
    }

    // -------------------------------------------------------------------------
    // isDue — cron expression, datetime string, callable
    // -------------------------------------------------------------------------

    public function test_that_job_skips_when_not_due(): void
    {
        $ran = false;
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
        $scheduler->addJob('test', fn() => true, fn() => 0);
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_callable_returning_null_succeeds(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
        $scheduler->addJob('test', fn() => true, fn() => null);
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_callable_returning_bad_value_logs_error(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner, logger: $logger);
        $scheduler->addJob('test', fn() => true, fn() => 'bad');
        $scheduler->run();
    }

    public function test_that_callable_throwing_logs_error(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner, logger: $logger);
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
        $runner = $this->mockProcessRunner();

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $runner);
        $scheduler->addCommand('cmd', fn() => true, 'echo hello');
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_failing_command_logs_error(): void
    {
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        $runner = $this->mockProcessRunner(new ProcessException('Command failed'));

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $runner, logger: $logger);
        $scheduler->addCommand('cmd', fn() => true, 'false');
        $scheduler->run();
    }

    public function test_that_failing_command_logs_notifies_and_releases_its_lock(): void
    {
        $failure = new ProcessException('Command failed');

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Command failed', ['exception' => $failure]);

        $message = new MailMessage();
        $factory = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn($message);

        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        $runCount = 0;
        $runner   = $this->mock(ProcessRunner::class);
        $runner->shouldReceive('attach')->twice()->with(\Mockery::type(ApplicationProcess::class));
        $runner->shouldReceive('run')->twice()->andReturnUsing(function () use (&$runCount, $failure): void {
            if ($runCount++ === 0) {
                throw $failure;
            }
        });

        $scheduler = new Scheduler(
            $this->timezone,
            $this->tempDir,
            $runner,
            logger: $logger,
            mailService: new MailService($transport, $factory),
            fromEmail: 'scheduler@example.com'
        );
        $scheduler->addCommand(
            'cmd',
            fn() => true,
            'false',
            notify: ['admin@example.com'],
            environment: 'test'
        );

        $scheduler->run();
        $scheduler->run();

        self::assertSame('[Scheduler] Job "cmd" failed', $message->getSubject());
        self::assertSame(['admin@example.com'], array_column($message->getTo(), 'address'));
        self::assertStringContainsString('Error: Command failed', $message->getContent()[0]['content']);
    }

    // -------------------------------------------------------------------------
    // Output modes
    // -------------------------------------------------------------------------

    public function test_that_output_false_produces_no_output(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
        $scheduler->addJob('test', fn() => true, fn() => true, maxRuntime: null);
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_nonexistent_lock_file_has_zero_lifetime(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
        // maxRuntime=9999 and no existing lock → lifetime=0 → no throw
        $scheduler->addJob('test', fn() => true, fn() => true, maxRuntime: 9999);
        $scheduler->run();

        self::assertTrue(true);
    }

    public function test_that_empty_lock_file_has_zero_lifetime_and_job_runs(): void
    {
        $ran      = false;
        $lockFile = $this->tempDir.'/test.lock';
        touch($lockFile);

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
        $scheduler->addJob('test', fn() => true, function () use (&$ran): bool {
            $ran = true;

            return true;
        }, maxRuntime: 5);
        $scheduler->run();

        self::assertTrue($ran);
    }

    public function test_that_dead_lock_process_has_zero_lifetime_and_job_runs(): void
    {
        $ran      = false;
        $lockFile = $this->tempDir.'/test.lock';
        file_put_contents($lockFile, '4242');
        RuntimeInspectionFunctionController::control($lockFile, processActive: false);

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
        $scheduler->addJob('test', fn() => true, function () use (&$ran): bool {
            $ran = true;

            return true;
        }, maxRuntime: 5);
        $scheduler->run();

        self::assertTrue($ran);
    }

    public function test_that_active_lock_within_max_runtime_allows_job_to_run(): void
    {
        $ran      = false;
        $lockFile = $this->tempDir.'/test.lock';
        file_put_contents($lockFile, '4242');
        touch($lockFile, 1_999_999_999);
        RuntimeInspectionFunctionController::control($lockFile, processActive: true, currentTime: 2_000_000_000);

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
        $scheduler->addJob('test', fn() => true, function () use (&$ran): bool {
            $ran = true;

            return true;
        }, maxRuntime: 5);
        $scheduler->run();

        self::assertTrue($ran);
    }

    public function test_that_expired_lock_logs_notifies_and_leaves_job_unexecuted(): void
    {
        $ran      = false;
        $lockFile = $this->tempDir.'/test.lock';
        file_put_contents($lockFile, '4242');
        touch($lockFile, 1_999_999_990);
        RuntimeInspectionFunctionController::control($lockFile, processActive: true, currentTime: 2_000_000_000);

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(
            static fn(string $message, array $context): bool =>
                $message === 'Max runtime of 5 seconds exceeded (current runtime: 10 seconds)'
                && $context['exception'] instanceof SchedulerException
        );

        $message = new MailMessage();
        $factory = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn($message);
        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        $scheduler = new Scheduler(
            $this->timezone,
            $this->tempDir,
            $this->processRunner,
            logger: $logger,
            mailService: new MailService($transport, $factory),
            fromEmail: 'scheduler@example.com'
        );
        $scheduler->addJob(
            'test',
            fn() => true,
            function () use (&$ran): bool {
                $ran = true;

                return true;
            },
            maxRuntime: 5,
            notify: ['admin@example.com'],
            environment: 'test'
        );
        $scheduler->run();

        self::assertFalse($ran);
        self::assertSame('4242', \file_get_contents($lockFile));
        self::assertSame('[Scheduler] Job "test" failed', $message->getSubject());
        self::assertStringContainsString(
            'Error: Max runtime of 5 seconds exceeded (current runtime: 10 seconds)',
            $message->getContent()[0]['content']
        );
    }

    public function test_that_runtime_inspection_failure_logs_notifies_and_leaves_job_unexecuted(): void
    {
        $ran      = false;
        $lockFile = $this->tempDir.'/test.lock';
        file_put_contents($lockFile, '4242');
        $failure = new RuntimeException('Runtime inspection failed');
        RuntimeInspectionFunctionController::control($lockFile, readFailure: $failure);

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Runtime inspection failed', ['exception' => $failure]);

        $message = new MailMessage();
        $factory = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn($message);
        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        $scheduler = new Scheduler(
            $this->timezone,
            $this->tempDir,
            $this->processRunner,
            logger: $logger,
            mailService: new MailService($transport, $factory),
            fromEmail: 'scheduler@example.com'
        );
        $scheduler->addJob(
            'test',
            fn() => true,
            function () use (&$ran): bool {
                $ran = true;

                return true;
            },
            maxRuntime: 5,
            notify: ['admin@example.com'],
            environment: 'test'
        );
        $scheduler->run();

        self::assertFalse($ran);
        self::assertSame('4242', \file_get_contents($lockFile));
        self::assertSame('[Scheduler] Job "test" failed', $message->getSubject());
        self::assertStringContainsString('Error: Runtime inspection failed', $message->getContent()[0]['content']);
    }

    // -------------------------------------------------------------------------
    // acquireLock / releaseLock
    // -------------------------------------------------------------------------

    public function test_that_lock_creation_failure_logs_notifies_and_leaves_job_unexecuted(): void
    {
        $ran      = false;
        $lockFile = $this->tempDir.'/creation_failure.lock';
        LockLifecycleFunctionController::control($lockFile, failCreate: true);

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(
            static fn(string $message, array $context): bool =>
                $message === sprintf('Unable to create lock file (File: %s)', $lockFile)
                && $context['exception'] instanceof RuntimeException
        );

        $message = new MailMessage();
        $factory = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn($message);
        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        $scheduler = new Scheduler(
            $this->timezone,
            $this->tempDir,
            $this->processRunner,
            logger: $logger,
            mailService: new MailService($transport, $factory),
            fromEmail: 'scheduler@example.com'
        );
        $scheduler->addJob(
            'creation failure',
            fn() => true,
            function () use (&$ran): bool {
                $ran = true;

                return true;
            },
            notify: ['admin@example.com'],
            environment: 'test'
        );
        $scheduler->run();

        self::assertFalse($ran);
        self::assertFileDoesNotExist($lockFile);
        self::assertSame('[Scheduler] Job "creation failure" failed', $message->getSubject());
        self::assertStringContainsString(
            sprintf('Error: Unable to create lock file (File: %s)', $lockFile),
            $message->getContent()[0]['content']
        );
    }

    public function test_that_lock_open_failure_logs_notifies_and_leaves_job_unexecuted(): void
    {
        $ran      = false;
        $lockFile = $this->tempDir.'/open_failure.lock';
        touch($lockFile);
        LockLifecycleFunctionController::control($lockFile, failOpen: true);

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(
            static fn(string $message, array $context): bool =>
                $message === sprintf('Unable to open lock file (File: %s)', $lockFile)
                && $context['exception'] instanceof RuntimeException
        );

        $message = new MailMessage();
        $factory = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn($message);
        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        $scheduler = new Scheduler(
            $this->timezone,
            $this->tempDir,
            $this->processRunner,
            logger: $logger,
            mailService: new MailService($transport, $factory),
            fromEmail: 'scheduler@example.com'
        );
        $scheduler->addJob(
            'open failure',
            fn() => true,
            function () use (&$ran): bool {
                $ran = true;

                return true;
            },
            notify: ['admin@example.com'],
            environment: 'test'
        );
        $scheduler->run();

        self::assertFalse($ran);
        self::assertSame('', file_get_contents($lockFile));
        self::assertSame('[Scheduler] Job "open failure" failed', $message->getSubject());
        self::assertStringContainsString(
            sprintf('Error: Unable to open lock file (File: %s)', $lockFile),
            $message->getContent()[0]['content']
        );
    }

    public function test_that_lock_held_by_another_process_logs_debug_and_skips_job(): void
    {
        $ran      = false;
        $lockFile = $this->tempDir.'/contended.lock';

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once()->with(
            sprintf('Job is still locked (File: %s)', $lockFile),
            ['job' => 'contended']
        );
        $factory = $this->mock(MailFactory::class);
        $factory->shouldNotReceive('createMessage');
        $transport = $this->mock(MailTransport::class);
        $transport->shouldNotReceive('send');

        touch($lockFile);
        $handle = fopen($lockFile, 'rb+');
        flock($handle, LOCK_EX | LOCK_NB);

        try {
            $scheduler = new Scheduler(
                $this->timezone,
                $this->tempDir,
                $this->processRunner,
                logger: $logger,
                mailService: new MailService($transport, $factory),
                fromEmail: 'scheduler@example.com'
            );
            $scheduler->addJob('contended', fn() => true, function () use (&$ran): bool {
                $ran = true;

                return true;
            });
            $scheduler->run();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        self::assertFalse($ran);
    }

    public function test_that_recursive_run_logs_notifies_and_executes_the_outer_job_once(): void
    {
        $lockFile = $this->tempDir.'/recursive.lock';
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(
            static fn(string $message, array $context): bool =>
                $message === sprintf('Lock already acquired (File: %s)', $lockFile)
                && $context['exception'] instanceof RuntimeException
        );

        $message = new MailMessage();
        $factory = $this->mock(MailFactory::class);
        $factory->shouldReceive('createMessage')->once()->andReturn($message);
        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        $scheduler = null;
        $runCount  = 0;
        $callable  = function () use (&$scheduler, &$runCount): int {
            $runCount++;
            /** @var Scheduler $scheduler */
            $scheduler->run();

            return 0;
        };
        $scheduler = new Scheduler(
            $this->timezone,
            $this->tempDir,
            $this->processRunner,
            logger: $logger,
            mailService: new MailService($transport, $factory),
            fromEmail: 'scheduler@example.com'
        );
        $scheduler->addJob(
            'recursive',
            fn() => true,
            $callable,
            notify: ['admin@example.com'],
            environment: 'test'
        );
        $scheduler->run();

        self::assertSame(1, $runCount);
        self::assertSame('[Scheduler] Job "recursive" failed', $message->getSubject());
        self::assertStringContainsString(
            sprintf('Error: Lock already acquired (File: %s)', $lockFile),
            $message->getContent()[0]['content']
        );
    }

    // -------------------------------------------------------------------------
    // logError
    // -------------------------------------------------------------------------

    public function test_that_log_error_is_no_op_without_logger(): void
    {
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner, logger: $logger);
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner, mailService: $mail, fromEmail: 'from@example.com');
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

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner, mailService: $mail, fromEmail: 'from@example.com');
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

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner, mailService: $mail, fromEmail: 'from@example.com');
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

        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner, mailService: $mail, fromEmail: 'from@example.com');
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
        $scheduler = new Scheduler($this->timezone, $this->tempDir, $this->processRunner);
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

    private function mockProcessRunner(?ProcessException $failure = null): ProcessRunner
    {
        $mock = $this->mock(ProcessRunner::class);
        $mock->shouldReceive('attach')->once()->with(\Mockery::type(ApplicationProcess::class));
        $run = $mock->shouldReceive('run')->once();
        if ($failure instanceof ProcessException) {
            $run->andThrow($failure);
        }

        return $mock;
    }
}
