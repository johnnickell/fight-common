# Process

A port-and-adapter layer for running shell processes. `Process` describes what to run;
`ProcessRunner` manages a queue of processes, controls concurrency, and handles failures.
`SymfonyProcessRunner` is the concrete adapter backed by `symfony/process`.

```
Application\Process
├── Process                             — Immutable process descriptor
├── ProcessRunner (interface)           — attach(), clear(), run()
├── ProcessErrorBehavior (enum: int)    — EXCEPTION, IGNORE, RETRY
└── Exception\
    ├── ProcessException                — extends SystemException
    └── ProcessFailedException          — extends ProcessException

Adapter\Process
└── Symfony\
    └── SymfonyProcessRunner            — Concurrent runner via symfony/process
```

---

## Table of Contents

1. [Process](#process-descriptor)
2. [ProcessRunner](#processrunner)
3. [ProcessErrorBehavior](#processerrorbehavior)
4. [SymfonyProcessRunner](#symfonyprocessrunner)
5. [Symfony Configuration](#symfony-configuration)
6. [Usage Examples](#usage-examples)

---

## Process Descriptor

`Fight\Common\Application\Process\Process`

An immutable value object that describes a process to be run. All fields beyond `$command`
are optional.

```php
use Fight\Common\Application\Process\Process;

$process = new Process(
    command:        'bin/console cache:clear',
    directory:      '/var/www/html',
    environment:    ['APP_ENV' => 'prod'],
    input:          null,
    timeout:        60.0,          // seconds; null = no timeout (default 60.0)
    stdout:         $stdoutFn,     // callable(?string $data): void
    stderr:         $stderrFn,     // callable(?string $data): void
    outputDisabled: false
);
```

### Fields

| Method | Returns | Description |
|---|---|---|
| `command()` | `string` | Shell command string |
| `directory()` | `?string` | Working directory (null = inherit) |
| `environment()` | `?array<string, string>` | Additional env vars (null = inherit) |
| `input()` | `mixed` | Stdin input (string, resource, or null) |
| `timeout()` | `?float` | Timeout in seconds (null = unlimited) |
| `stdout()` | `callable\|null` | Called for each chunk of stdout output |
| `stderr()` | `callable\|null` | Called for each chunk of stderr output |
| `isOutputDisabled()` | `bool` | Whether output capturing is disabled |

---

## ProcessRunner

`Fight\Common\Application\Process\ProcessRunner`

```php
interface ProcessRunner
{
    public function attach(Process $process): void;

    public function clear(): void;

    /**
     * @throws ProcessException
     */
    public function run(?ProcessErrorBehavior $errorBehavior = null): void;
}
```

Processes are queued via `attach()`, then all started when `run()` is called. `run()`
blocks until all queued processes complete and then clears the queue automatically.
Pass a `ProcessErrorBehavior` to control how failures are handled (defaults to
`EXCEPTION`).

---

## ProcessErrorBehavior

`Fight\Common\Application\Process\ProcessErrorBehavior`

```php
enum ProcessErrorBehavior: int
{
    case EXCEPTION = 1;   // Throw ProcessFailedException on non-zero exit (default)
    case IGNORE    = 2;   // Continue silently when a process fails
    case RETRY     = 3;   // Re-run failed processes up to the configured tries limit
}
```

---

## SymfonyProcessRunner

`Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner`

A queue-based, concurrent process runner built on `symfony/process`. Processes are
started up to the concurrency limit; as they finish, the next queued process is launched.

```php
use Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner;

$runner = new SymfonyProcessRunner(
    logger:        $logger,       // ?LoggerInterface (default null)
    maxConcurrent: 4,             // max simultaneous processes; 0 = unlimited (default 1)
    delay:         1000,          // microseconds between polling ticks (default 1000)
    tries:         3,             // max attempts per process when using RETRY (default 3)
    logLevel:      LogLevel::DEBUG
);
```

### Concurrency

Set `maxConcurrent` to control how many processes run at the same time:

```php
$runner = new SymfonyProcessRunner(maxConcurrent: 8);

foreach ($jobs as $job) {
    $runner->attach(new Process($job->command()));
}

$runner->run();  // runs up to 8 at a time, blocks until all complete
```

`maxConcurrent: 0` disables the concurrency limit — all queued processes are started
immediately.

### Retry

When `ProcessErrorBehavior::RETRY` is passed to `run()`, a failed process is re-enqueued
and started again, up to `$tries` total attempts:

```php
$runner = new SymfonyProcessRunner(tries: 5);

$runner->attach(new Process('bin/flaky-script'));
$runner->run(ProcessErrorBehavior::RETRY);
// attempts up to 5 times before throwing ProcessFailedException
```

### Logging

When a `LoggerInterface` is provided, the runner logs:
- Process started (at configured `$logLevel`)
- Process restarted after failure (at configured `$logLevel`)
- Process failed — includes exit code, exit code text, stdout, and stderr (at `error`)

### Output Callbacks

Attach per-process callbacks to stream output in real time:

```php
$process = new Process(
    command: 'bin/long-running-task',
    stdout:  fn(string $data) => $this->logger->info($data),
    stderr:  fn(string $data) => $this->logger->error($data),
);
```

---

## Symfony Configuration

```yaml
# config/packages/common_process.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner:
        arguments:
            - '@logger'
            - 4        # maxConcurrent
            - 1000     # delay (µs)
            - 3        # tries

    Fight\Common\Application\Process\ProcessRunner:
        alias: Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner
```

---

## Usage Examples

### Running a Single Process

```php
use Fight\Common\Application\Process\Process;
use Fight\Common\Application\Process\ProcessRunner;

class CacheClearer
{
    public function __construct(private ProcessRunner $runner) {}

    public function clear(string $env): void
    {
        $this->runner->attach(new Process(
            command:   'bin/console cache:clear',
            directory: '/var/www/html',
            environment: ['APP_ENV' => $env]
        ));

        $this->runner->run();
    }
}
```

### Running Jobs in Parallel

```php
$runner = new SymfonyProcessRunner(maxConcurrent: 4);

foreach ($files as $file) {
    $runner->attach(new Process("bin/process-file {$file}"));
}

$runner->run();  // 4 at a time until all complete
```

### Ignoring Failures

```php
$runner->attach(new Process('bin/optional-cleanup'));
$runner->run(ProcessErrorBehavior::IGNORE);  // non-zero exit silently discarded
```

### Retrying Flaky Processes

```php
$runner = new SymfonyProcessRunner(tries: 3);
$runner->attach(new Process('curl https://api.example.com/sync'));
$runner->run(ProcessErrorBehavior::RETRY);
// retries up to 3 times; throws ProcessFailedException if all attempts fail
```

### Streaming Output

```php
$lines = [];

$runner->attach(new Process(
    command: 'bin/generate-report',
    stdout:  function (string $data) use (&$lines): void {
        $lines[] = trim($data);
    }
));

$runner->run();
```
