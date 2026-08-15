<?php

declare(strict_types=1);

namespace Fight\Common\Application\Process;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Exception\MethodCallException;

/**
 * Class ProcessBuilder
 */
final class ProcessBuilder
{
    /** @var list<string> */
    private array $prefix = [];
    /** @var list<string> */
    private array $arguments = [];
    private ?string $shellCommand = null;
    private ?string $directory = null;
    private mixed $input = null;
    private ?float $timeout = 60.0;
    /** @var array<string, string> */
    private array $environment = [];
    private mixed $stdout = null;
    private mixed $stderr = null;
    private bool $outputDisabled = false;

    /**
     * Constructs ProcessBuilder
     *
     * @param string|list<string>|null $arguments
     */
    public function __construct(string|array|null $arguments = null)
    {
        foreach ((array) ($arguments ?? []) as $arg) {
            $this->arg($arg);
        }
    }

    /**
     * Creates a new instance
     *
     * @param string|list<string>|null $arguments
     */
    public static function create(string|array|null $arguments = null): static
    {
        return new self($arguments);
    }

    /**
     * Sets the command prefix (e.g. the executable and any fixed flags that precede user arguments)
     *
     * @param string|list<string> $prefix
     */
    public function prefix(string|array $prefix): static
    {
        $this->shellCommand = null;
        $this->prefix = (array) $prefix;

        return $this;
    }

    /**
     * Appends a positional argument; empty strings are silently ignored
     */
    public function arg(string $arg): static
    {
        if ($arg !== '') {
            $this->shellCommand = null;
            $this->arguments[] = $arg;
        }

        return $this;
    }

    /**
     * Appends a long option; a leading -- is added when absent
     */
    public function option(string $option, ?string $value = null): static
    {
        if ($option === '') {
            return $this;
        }

        $this->shellCommand = null;

        if (!str_starts_with($option, '-')) {
            $option = '--'.$option;
        }

        $this->arguments[] = $option;

        if ($value !== null) {
            $this->arguments[] = $value;
        }

        return $this;
    }

    /**
     * Appends a short option; a leading - is added when absent
     */
    public function short(string $option, ?string $value = null): static
    {
        if ($option === '') {
            return $this;
        }

        $this->shellCommand = null;

        if (!str_starts_with($option, '-')) {
            $option = '-'.$option;
        }

        $this->arguments[] = $option;

        if ($value !== null) {
            $this->arguments[] = $value;
        }

        return $this;
    }

    /**
     * Clears all positional arguments (prefix is unaffected)
     */
    public function clearArgs(): static
    {
        $this->arguments = [];

        return $this;
    }

    /**
     * Sets an already accepted shell command without escaping its operators or arguments
     */
    public function shellCommand(string $command): static
    {
        $this->prefix = [];
        $this->arguments = [];
        $this->shellCommand = $command;

        return $this;
    }

    /**
     * Sets the working directory
     */
    public function directory(?string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Sets stdin input; accepts a string, resource, or null
     */
    public function input(mixed $input): static
    {
        if ($input === null || is_resource($input)) {
            $this->input = $input;
        } elseif (is_scalar($input)) {
            $this->input = (string) $input;
        }

        return $this;
    }

    /**
     * Sets the timeout in seconds; null disables the timeout
     *
     * @throws DomainException When the value is negative
     */
    public function timeout(int|float|null $timeout): static
    {
        if ($timeout === null) {
            $this->timeout = null;

            return $this;
        }

        $timeout = (float) $timeout;

        if ($timeout < 0) {
            throw new DomainException('Timeout must be a positive number');
        }

        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Adds or overrides a single environment variable
     */
    public function setEnv(string $name, string $value): static
    {
        $this->environment[$name] = $value;

        return $this;
    }

    /**
     * Sets the stdout callback
     *
     * @param callable|null $stdout
     */
    public function stdout(mixed $stdout): static
    {
        $this->stdout = $stdout;

        return $this;
    }

    /**
     * Sets the stderr callback
     *
     * @param callable|null $stderr
     */
    public function stderr(mixed $stderr): static
    {
        $this->stderr = $stderr;

        return $this;
    }

    /**
     * Disables output capturing
     */
    public function disableOutput(): static
    {
        $this->outputDisabled = true;

        return $this;
    }

    /**
     * Enables output capturing
     */
    public function enableOutput(): static
    {
        $this->outputDisabled = false;

        return $this;
    }

    /**
     * Builds and returns a Process descriptor
     *
     * @throws MethodCallException When no prefix or arguments have been set
     */
    public function getProcess(): Process
    {
        if ($this->shellCommand === null && $this->prefix === [] && $this->arguments === []) {
            throw new MethodCallException(
                'You must add arguments before calling getProcess()'
            );
        }

        $parts = array_merge($this->prefix, $this->arguments);
        $command = $this->shellCommand ?? implode(' ', array_map(escapeshellarg(...), $parts));

        return new Process(
            command:        $command,
            directory:      $this->directory,
            environment:    $this->environment !== [] ? $this->environment : null,
            input:          $this->input,
            timeout:        $this->timeout,
            stdout:         $this->stdout,
            stderr:         $this->stderr,
            outputDisabled: $this->outputDisabled
        );
    }
}
