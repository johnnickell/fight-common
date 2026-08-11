<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Process\Symfony;

use Closure;
use Fight\Common\Application\Process\Exception\ProcessException;
use Fight\Common\Application\Process\Exception\ProcessFailedException;
use Fight\Common\Application\Process\Process;
use Fight\Common\Application\Process\ProcessErrorBehavior;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Domain\Collection\Contract\Queue;
use Fight\Common\Domain\Collection\LinkedQueue;
use Fight\Common\Domain\Exception\DomainException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

/**
 * Class SymfonyProcessRunner
 */
final class SymfonyProcessRunner implements ProcessRunner
{
    /** @var Queue<Process> */
    private Queue $queue;
    /** @var array<int, array{iteration: int, original: Process, process: SymfonyProcess}> */
    private array $processes = [];
    private readonly int $delay;

    /**
     * Constructs SymfonyProcessRunner
     *
     * @throws DomainException When delay is less than 1
     */
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly int $maxConcurrent = 1,
        int $delay = 1000,
        private readonly int $tries = 3,
        private readonly string $logLevel = LogLevel::DEBUG,
        private readonly ?Closure $processFactory = null
    ) {
        if ($delay < 1) {
            throw new DomainException(
                sprintf('%s expects delay to be a natural number', __METHOD__)
            );
        }

        $this->delay = $delay;
        $this->queue = LinkedQueue::of(Process::class);
    }

    /**
     * @inheritDoc
     */
    public function attach(Process $process): void
    {
        $this->queue->enqueue($process);
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->queue = LinkedQueue::of(Process::class);
        $this->processes = [];
    }

    /**
     * @inheritDoc
     */
    public function run(?ProcessErrorBehavior $errorBehavior = null): void
    {
        $errorBehavior ??= ProcessErrorBehavior::EXCEPTION;

        while (!$this->queue->isEmpty()) {
            $this->init($errorBehavior);
            $this->tick($errorBehavior);
        }

        while ($this->processes !== []) {
            $this->tick($errorBehavior);
        }

        $this->clear();
    }

    /**
     * Starts the next queued process if the concurrency limit allows
     *
     * @throws ProcessException When startup fails and behavior is EXCEPTION
     */
    private function init(ProcessErrorBehavior $errorBehavior): void
    {
        if ($this->maxConcurrent !== 0 && count($this->processes) >= $this->maxConcurrent) {
            return;
        }

        try {
            /** @var Process $process */
            $process = $this->queue->dequeue();
            $symfonyProcess = $this->exchangeProcess($process);

            $this->startProcess(
                $symfonyProcess,
                $process->stdout(),
                $process->stderr(),
                $process->isOutputDisabled()
            );

            $pid = (int) $symfonyProcess->getPid();
            $this->processes[$pid] = [
                'iteration' => 1,
                'original'  => $process,
                'process'   => $symfonyProcess
            ];

            $this->logProcessStarted($symfonyProcess);
        } catch (ProcessException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($errorBehavior === ProcessErrorBehavior::EXCEPTION) {
                throw new ProcessException($e->getMessage(), (int) $e->getCode(), $e);
            }
        }
    }

    /**
     * Checks all running processes for completion or failure
     *
     * @throws ProcessFailedException When a process exits with a non-zero code
     * @throws ProcessException When an unexpected error occurs and behavior is EXCEPTION
     */
    private function tick(ProcessErrorBehavior $errorBehavior): void
    {
        usleep($this->delay);

        try {
            foreach ($this->processes as $pid => $processData) {
                /** @var SymfonyProcess $symfonyProcess */
                $symfonyProcess = $processData['process'];
                $symfonyProcess->checkTimeout();

                if ($symfonyProcess->isRunning()) {
                    continue;
                }

                unset($this->processes[$pid]);

                if ($symfonyProcess->isSuccessful()) {
                    continue;
                }

                $this->logProcessFailed($symfonyProcess);

                if ($errorBehavior === ProcessErrorBehavior::RETRY) {
                    /** @var Process $original */
                    $original  = $processData['original'];
                    $iteration = $processData['iteration'];

                    if ($iteration >= $this->tries) {
                        throw new ProcessFailedException(
                            $this->buildFailureMessage($symfonyProcess)
                        );
                    }

                    $retried = $this->exchangeProcess($original);
                    $this->startProcess(
                        $retried,
                        $original->stdout(),
                        $original->stderr(),
                        $original->isOutputDisabled()
                    );

                    $retryPid = (int) $retried->getPid();
                    $this->processes[$retryPid] = [
                        'iteration' => $iteration + 1,
                        'original'  => $original,
                        'process'   => $retried
                    ];

                    $this->logProcessRestarted($retried);
                }

                if ($errorBehavior === ProcessErrorBehavior::EXCEPTION) {
                    throw new ProcessFailedException(
                        $this->buildFailureMessage($symfonyProcess)
                    );
                }
            }
        } catch (ProcessException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($errorBehavior === ProcessErrorBehavior::EXCEPTION) {
                throw new ProcessException($e->getMessage(), (int) $e->getCode(), $e);
            }
        }
    }

    /**
     * Starts a Symfony process with optional output callbacks
     */
    private function startProcess(
        SymfonyProcess $process,
        mixed $stdout = null,
        mixed $stderr = null,
        bool $outputDisabled = false
    ): void {
        if ($outputDisabled) {
            $process->start();

            return;
        }

        $out = SymfonyProcess::OUT;

        $process->start(function ($type, $data) use ($stdout, $stderr, $out): void {
            if ($type === $out) {
                if ($stdout !== null) {
                    call_user_func($stdout, $data);
                }
            } else {
                if ($stderr !== null) {
                    call_user_func($stderr, $data);
                }
            }
        });
    }

    /**
     * Creates a Symfony process from an application Process descriptor
     */
    private function exchangeProcess(Process $process): SymfonyProcess
    {
        if ($this->processFactory instanceof Closure) {
            return ($this->processFactory)($process);
        }

        $symfonyProcess = SymfonyProcess::fromShellCommandline(
            $process->command(),
            $process->directory(),
            $process->environment(),
            $process->input(),
            $process->timeout()
        );

        if ($process->isOutputDisabled()) {
            $symfonyProcess->disableOutput();
        }

        return $symfonyProcess;
    }

    /**
     * Builds a failure message from a completed process
     */
    private function buildFailureMessage(SymfonyProcess $process): string
    {
        return sprintf(
            'Process "%s" failed with exit code %d(%s)',
            $process->getCommandLine(),
            $process->getExitCode(),
            $process->getExitCodeText()
        );
    }

    /**
     * Ends all running processes
     */
    private function stop(): void
    {
        foreach ($this->processes as $processData) {
            /** @var SymfonyProcess $process */
            $process = $processData['process'];
            $process->stop(0);
        }

        $this->clear();
    }

    /**
     * Logs that a process has started
     */
    private function logProcessStarted(SymfonyProcess $process): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $this->logger->log(
            $this->logLevel,
            sprintf(
                '[Process]: "%s" started; Working directory: %s',
                $process->getCommandLine(),
                $process->getWorkingDirectory()
            )
        );
    }

    /**
     * Logs that a process has been restarted
     */
    private function logProcessRestarted(SymfonyProcess $process): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $this->logger->log(
            $this->logLevel,
            sprintf(
                '[Process]: "%s" restarted; Working directory: %s',
                $process->getCommandLine(),
                $process->getWorkingDirectory()
            )
        );
    }

    /**
     * Logs that a process has failed
     */
    private function logProcessFailed(SymfonyProcess $process): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $this->logger->error(
            sprintf(
                '[Process]: "%s" failed; Exit code: %s(%s); Working directory: %s',
                $process->getCommandLine(),
                $process->getExitCode(),
                $process->getExitCodeText(),
                $process->getWorkingDirectory()
            )
        );

        if ($process->isOutputDisabled()) {
            return;
        }

        $this->logger->log(
            $this->logLevel,
            sprintf(
                '[Process]: Output: {%s}; Error output: {%s}',
                $process->getOutput(),
                $process->getErrorOutput()
            )
        );
    }

    /**
     * Handles SymfonyProcessRunner destruct
     */
    public function __destruct()
    {
        $this->stop();
    }
}
