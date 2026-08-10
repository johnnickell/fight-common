<?php

declare(strict_types=1);

namespace Fight\Common\Application\Scheduler;

use Closure;
use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Application\Mail\Exception\MailException;
use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Scheduler\Exception\LockException;
use Fight\Common\Application\Scheduler\Exception\SchedulerException;
use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Utility\VarPrinter;
use Fight\Common\Domain\Value\DateTime\Timezone;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Class Scheduler
 *
 * @phpstan-type JobConfig array{
 *     name: string,
 *     schedule: mixed,
 *     command: mixed,
 *     enabled: bool,
 *     output: bool|string,
 *     maxRuntime: int|null,
 *     notify: array<string>,
 *     environment: string
 * }
 */
final class Scheduler
{
    /** @var list<JobConfig> */
    private array $jobs = [];
    /** @var array<string, resource> */
    private array $lockHandles = [];

    /**
     * Constructs Scheduler
     */
    public function __construct(
        private readonly Timezone $timezone,
        private readonly string $tempDirectory,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?MailService $mailService = null,
        private readonly string $fromEmail = '',
        private readonly ?Closure $processFactory = null
    ) {
    }

    /**
     * Adds a shell command job
     *
     * @phpstan-param string|array<string> $notify
     */
    public function addCommand(
        string $name,
        string|callable $schedule,
        string $command,
        bool $enabled = true,
        bool|string $output = false,
        ?int $maxRuntime = null,
        string|array $notify = [],
        string $environment = ''
    ): void {
        $this->jobs[] = $this->normalizeJob(
            $name,
            $schedule,
            $command,
            $enabled,
            $output,
            $maxRuntime,
            $notify,
            $environment
        );
    }

    /**
     * Adds a callable job
     *
     * @phpstan-param string|array<string> $notify
     */
    public function addJob(
        string $name,
        string|callable $schedule,
        callable $job,
        bool $enabled = true,
        bool|string $output = false,
        ?int $maxRuntime = null,
        string|array $notify = [],
        string $environment = ''
    ): void {
        $this->jobs[] = $this->normalizeJob(
            $name,
            $schedule,
            $job,
            $enabled,
            $output,
            $maxRuntime,
            $notify,
            $environment
        );
    }

    /**
     * Runs all due jobs
     */
    public function run(): void
    {
        foreach ($this->jobs as $job) {
            if (!$job['enabled']) {
                continue;
            }

            if (!$this->isDue($job['schedule'])) {
                continue;
            }

            $this->runJob($job);
        }
    }

    /**
     * Runs a single job with locking, error handling, and notification
     *
     * @phpstan-param JobConfig $job
     */
    private function runJob(array $job): void
    {
        $lockFile = $this->getLockFile($job['name']);

        try {
            $this->checkMaxRuntime($lockFile, $job['maxRuntime']);
        // @codeCoverageIgnoreStart
        } catch (Throwable $throwable) {
            $this->logError($throwable);
            $this->notify($throwable, $job);

            return;
        }

        // @codeCoverageIgnoreEnd

        try {
            $this->acquireLock($lockFile);
        } catch (LockException $e) {
            if ($this->logger instanceof LoggerInterface) {
                $this->logger->debug($e->getMessage(), ['job' => $job['name']]);
            }

            return;
        } catch (Throwable $throwable) {
            $this->logError($throwable);
            $this->notify($throwable, $job);

            return;
        }

        try {
            if (is_string($job['command'])) {
                $this->runCommand($job);
            } else {
                $this->runCallable($job);
            }
        } catch (Throwable $throwable) {
            $this->logError($throwable);
            $this->notify($throwable, $job);
        } finally {
            $this->releaseLock($lockFile);
        }
    }

    /**
     * Checks whether a schedule is currently due
     */
    private function isDue(mixed $schedule): bool
    {
        if (is_callable($schedule)) {
            return (bool) call_user_func($schedule);
        }

        $schedule = (string) $schedule;
        $tz  = new DateTimeZone($this->timezone->value());
        $now = new DateTimeImmutable('now', $tz);

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $schedule, $tz);
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i') === $now->format('Y-m-d H:i');
        }

        return new CronExpression($schedule)->isDue($now);
    }

    /**
     * Runs a callable job, capturing output
     *
     * @phpstan-param JobConfig $job
     *
     * @throws SchedulerException When the callable fails or returns an unexpected value
     */
    private function runCallable(array $job): void
    {
        ob_start();

        $returnValue = null;
        try {
            $returnValue = call_user_func($job['command']);
        } catch (Throwable $throwable) {
            $output = explode("\n", (string) ob_get_clean());
            foreach ($output as $line) {
                $this->writeLine($line, $job);
            }

            throw new SchedulerException($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }

        $output = explode("\n", (string) ob_get_clean());
        foreach ($output as $line) {
            $this->writeLine($line, $job);
        }

        if (!in_array($returnValue, [0, true, null], true)) {
            throw new SchedulerException(sprintf(
                'Job did not return 0, true, or null; returned (%s) "%s"',
                gettype($returnValue),
                VarPrinter::toString($returnValue)
            ));
        }
    }

    /**
     * Runs a shell command job
     *
     * @phpstan-param JobConfig $job
     *
     * @throws SchedulerException When the command exits with a non-zero status
     */
    private function runCommand(array $job): void
    {
        $process = $this->createProcess($job['command']);

        $process->run(function (string $type, string $data) use ($job): void {
            $this->writeLine($data, $job);
        });

        if (!$process->isSuccessful()) {
            throw new SchedulerException(sprintf(
                'Command exited with non-zero status %d',
                $process->getExitCode()
            ));
        }
    }

    /**
     * Creates a Symfony Process for the given command
     */
    private function createProcess(string $command): Process
    {
        if ($this->processFactory instanceof Closure) {
            return ($this->processFactory)($command);
        }

        // @codeCoverageIgnoreStart
        return Process::fromShellCommandline($command);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Writes a line to the configured output destination
     *
     * @phpstan-param JobConfig $job
     */
    private function writeLine(string $line, array $job): void
    {
        if ($job['output'] === false) {
            return;
        }

        if (is_string($job['output'])) {
            file_put_contents($job['output'], sprintf("%s\n", $line), FILE_APPEND);

            return;
        }

        echo sprintf("%s\n", $line);
    }

    /**
     * Logs an error if a logger is available
     */
    private function logError(Throwable $e): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $this->logger->error($e->getMessage(), ['exception' => $e]);
    }

    /**
     * Sends a failure notification email if configured
     *
     * @phpstan-param JobConfig $job
     *
     * @throws MailException
     */
    private function notify(Throwable $e, array $job): void
    {
        if (!$this->mailService instanceof MailService || $job['notify'] === []) {
            return;
        }

        $body = implode("\n", [
            sprintf('Environment: %s', $job['environment']),
            '',
            sprintf('Error: %s', $e->getMessage()),
            '',
            sprintf('Code: %s', $e->getCode()),
            '',
            sprintf('File: %s', $e->getFile()),
            '',
            sprintf('Line: %d', $e->getLine()),
            '',
            $e->getTraceAsString()
        ]);

        $message = $this->mailService->createMessage()
            ->setSubject(sprintf('[Scheduler] Job "%s" failed', $job['name']))
            ->addFrom($this->fromEmail)
            ->addContent($body, MailMessage::CONTENT_TYPE_PLAIN);

        foreach ($job['notify'] as $address) {
            $message->addTo($address);
        }

        $this->mailService->send($message);
    }

    /**
     * Checks whether the job's max runtime has been exceeded
     *
     * @throws SchedulerException When the max runtime is exceeded
     */
    private function checkMaxRuntime(string $lockFile, ?int $maxRuntime): void
    {
        if ($maxRuntime === null) {
            return;
        }

        $runtime = $this->getLockLifetime($lockFile);

        // @codeCoverageIgnoreStart
        if ($runtime > $maxRuntime) {
            throw new SchedulerException(sprintf(
                'Max runtime of %d seconds exceeded (current runtime: %d seconds)',
                $maxRuntime,
                $runtime
            ));
        }

        // @codeCoverageIgnoreEnd
    }

    /**
     * Returns the number of seconds a lock file has been held by an active process
     */
    private function getLockLifetime(string $lockFile): int
    {
        if (!file_exists($lockFile)) {
            return 0;
        }

        // @codeCoverageIgnoreStart
        $pid = file_get_contents($lockFile);

        if (empty($pid)) {
            return 0;
        }

        if (!posix_kill((int) $pid, 0)) {
            return 0;
        }

        $stat = stat($lockFile);

        return (time() - $stat['mtime']);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Acquires an exclusive file lock for the job
     *
     * @throws RuntimeException When the lock is already acquired by this instance
     * @throws LockException When the file is locked by another process
     */
    private function acquireLock(string $lockFile): void
    {
        if (array_key_exists($lockFile, $this->lockHandles)) {
            throw new RuntimeException(sprintf('Lock already acquired (File: %s)', $lockFile));
        }

        // @codeCoverageIgnoreStart
        if (!file_exists($lockFile) && !touch($lockFile)) {
            throw new RuntimeException(sprintf('Unable to create lock file (File: %s)', $lockFile));
        }

        $handle = fopen($lockFile, 'rb+');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open lock file (File: %s)', $lockFile));
        }

        $attempts = 5;
        while ($attempts > 0) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $this->lockHandles[$lockFile] = $handle;
                ftruncate($handle, 0);
                fwrite($handle, (string) getmypid());

                return;
            }

            usleep(250);
            $attempts--;
        }

        throw new LockException(sprintf('Job is still locked (File: %s)', $lockFile));
        // @codeCoverageIgnoreEnd
    }

    /**
     * Releases the file lock for the job
     */
    private function releaseLock(string $lockFile): void
    {
        // @codeCoverageIgnoreStart
        if (!empty($this->lockHandles[$lockFile])) {
            ftruncate($this->lockHandles[$lockFile], 0);
            flock($this->lockHandles[$lockFile], LOCK_UN);
            fclose($this->lockHandles[$lockFile]);
        }

        // @codeCoverageIgnoreEnd

        unset($this->lockHandles[$lockFile]);
    }

    /**
     * Returns the lock file path for a job
     */
    private function getLockFile(string $name): string
    {
        return sprintf('%s/%s.lock', rtrim($this->tempDirectory, '/'), $this->escape($name));
    }

    /**
     * Normalizes a job name for use as a filename
     */
    private function escape(string $name): string
    {
        $name = strtolower($name);
        $name = (string) preg_replace('/[^a-z0-9_. -]+/', '', $name);
        $name = trim($name);
        $name = str_replace(' ', '_', $name);
        $name = (string) preg_replace('/_{2,}/', '_', $name);

        return $name;
    }

    /**
     * Normalizes job registration arguments into a consistent shape
     *
     * @phpstan-param string|array<string> $notify
     *
     * @return JobConfig
     */
    private function normalizeJob(
        string $name,
        mixed $schedule,
        mixed $command,
        bool $enabled,
        bool|string $output,
        ?int $maxRuntime,
        string|array $notify,
        string $environment
    ): array {
        $notifyAddresses = is_array($notify) ? $notify : array_filter(array_map(trim(...), explode(',', $notify)));

        return [
            'name'        => $name,
            'schedule'    => $schedule,
            'command'     => $command,
            'enabled'     => $enabled,
            'output'      => $output,
            'maxRuntime'  => $maxRuntime,
            'notify'      => array_values($notifyAddresses),
            'environment' => $environment
        ];
    }
}
