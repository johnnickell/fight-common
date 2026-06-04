<?php

declare(strict_types=1);

namespace Fight\Common\Application\Process;

/**
 * Class Process
 */
final readonly class Process
{
    /**
     * Constructs Process
     *
     * @param string                     $command
     * @param string|null                $directory
     * @param array<string, string>|null $environment
     * @param mixed                      $input
     * @param float|null                 $timeout
     * @param callable|null              $stdout
     * @param callable|null              $stderr
     * @param boolean                    $outputDisabled
     */
    public function __construct(
        private string $command,
        private ?string $directory = null,
        private ?array $environment = null,
        private mixed $input = null,
        private ?float $timeout = 60.0,
        private mixed $stdout = null,
        private mixed $stderr = null,
        private bool $outputDisabled = false
    ) {
    }

    /**
     * Retrieves the command string
     */
    public function command(): string
    {
        return $this->command;
    }

    /**
     * Retrieves the working directory
     */
    public function directory(): ?string
    {
        return $this->directory;
    }

    /**
     * Retrieves the environment variables
     *
     * @return array<string, string>|null
     */
    public function environment(): ?array
    {
        return $this->environment;
    }

    /**
     * Retrieves the stdin input
     */
    public function input(): mixed
    {
        return $this->input;
    }

    /**
     * Retrieves the timeout in seconds
     */
    public function timeout(): ?float
    {
        return $this->timeout;
    }

    /**
     * Retrieves the stdout callback
     *
     * @return callable|null
     */
    public function stdout(): mixed
    {
        return $this->stdout;
    }

    /**
     * Retrieves the stderr callback
     *
     * @return callable|null
     */
    public function stderr(): mixed
    {
        return $this->stderr;
    }

    /**
     * Checks whether output is disabled
     */
    public function isOutputDisabled(): bool
    {
        return $this->outputDisabled;
    }
}
