# Scheduler

A cron-style job scheduler for PHP CLI processes. Jobs are registered with a name, a
schedule, and a command string or PHP callable. On each `run()` call the scheduler checks
which jobs are due and executes them with file-based exclusive locking to prevent
overlapping runs.

```
Application\Scheduler
├── Scheduler                           — Job registry and runner
└── Exception\
    ├── SchedulerException              — extends SystemException
    └── LockException                   — extends SchedulerException

Domain\Value\DateTime
└── Timezone                            — Immutable timezone value object
```

---

## Table of Contents

1. [Scheduler](#scheduler-class)
2. [Schedule Formats](#schedule-formats)
3. [Locking](#locking)
4. [Output Modes](#output-modes)
5. [Error Handling and Notification](#error-handling-and-notification)
6. [Max Runtime Guard](#max-runtime-guard)
7. [Timezone](#timezone)
8. [Symfony Configuration](#symfony-configuration)
9. [Usage Examples](#usage-examples)

---

## Scheduler Class

`Fight\Common\Application\Scheduler\Scheduler`

```php
use Fight\Common\Application\Scheduler\Scheduler;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Domain\Value\DateTime\Timezone;

$scheduler = new Scheduler(
    timezone:       new Timezone('America/New_York'),
    tempDirectory:  '/var/run/scheduler',
    processRunner:  $processRunner,    // ProcessRunner (required)
    logger:         $logger,           // ?LoggerInterface (default null)
    mailService:    $mailService,      // ?MailService (default null)
    fromEmail:      'cron@example.com'
);
```

Command jobs are described with `ProcessBuilder::shellCommand()` and executed through the
Application-owned `ProcessRunner` port. The runner is a required Scheduler dependency, including when a
Scheduler currently registers only callable jobs, so every Scheduler is ready to execute either job type.

### Registering Jobs

**Callable job** — runs a PHP closure or callable:

```php
$scheduler->addJob(
    name:        'send-digest',
    schedule:    '0 8 * * *',       // daily at 08:00
    job:         fn() => $this->digestService->send(),
    enabled:     true,
    output:      false,
    maxRuntime:  120,
    notify:      ['ops@example.com'],
    environment: 'production'
);
```

**Shell command job** — runs an accepted command string through the configured `ProcessRunner`:

```php
$scheduler->addCommand(
    name:    'cache-warm',
    schedule: '*/15 * * * *',       // every 15 minutes
    command:  'bin/console cache:warmup --env=prod',
    output:   '/var/log/cache-warm.log'
);
```

### Running

```php
// Typically called from a cron entry that runs every minute:
// * * * * * php bin/scheduler.php

$scheduler->run();
```

`run()` iterates all registered jobs, skips disabled ones and those not currently due,
and executes the rest with exclusive locking.

### Callable Return Values

A callable job is considered successful if it returns `0`, `true`, or `null`. Any other
return value throws `SchedulerException`. Exceptions thrown inside the callable are caught,
logged, and (if configured) emailed — they do not propagate to the caller.

---

## Schedule Formats

Three formats are accepted for the `$schedule` parameter:

| Format | Example | Description |
|---|---|---|
| Cron expression | `'0 8 * * *'` | Standard 5-field cron, powered by `dragonmantank/cron-expression` |
| Datetime string | `'2026-12-31 23:59:00'` | Runs once at that exact minute |
| Callable | `fn() => $myCondition` | Returns `true` when the job should run |

The callable form is useful for event-driven or condition-based scheduling:

```php
$scheduler->addJob('cleanup', fn() => $this->isMaintenanceWindow(), $callable);
```

---

## Locking

Each job acquires an exclusive file lock before running, preventing the same job from
running concurrently across multiple processes. Lock files are written to `$tempDirectory`
with names derived from the job name (lowercased, special characters stripped):

- `My Nightly Job!` → `my_nightly_job.lock`

If a lock cannot be acquired (another process holds it), the job is silently skipped and
a `DEBUG` entry is written to the logger. If the scheduler itself holds the lock (e.g.,
via a recursive call), a `RuntimeException` is caught, logged as an error, and the job
is skipped.

---

## Output Modes

The `$output` parameter controls where job output is written:

| Value | Behavior |
|---|---|
| `false` (default) | Output is suppressed |
| `true` | Output is echoed to stdout |
| `'/path/to/file.log'` | Output is appended to the specified file |

---

## Error Handling and Notification

When a job fails the scheduler:

1. Logs the error via `LoggerInterface::error()` (if a logger is configured)
2. Sends a failure email (if a `MailService` and `$notify` addresses are configured)

The notification email includes the environment, error message, code, file, line, and
full stack trace. The `$notify` parameter accepts an array of addresses or a
comma-separated string:

```php
$scheduler->addJob(
    name:        'import',
    schedule:    '0 2 * * *',
    job:         $importCallable,
    notify:      'alice@example.com, bob@example.com',
    environment: 'production'
);
```

---

## Max Runtime Guard

The `$maxRuntime` parameter (in seconds) is checked at the start of each run. If the job's
lock file exists and the owning PID has been alive for longer than `$maxRuntime`, a
`SchedulerException` is thrown, logged, and (if configured) emailed.

```php
$scheduler->addJob('long-import', '0 1 * * *', $callable, maxRuntime: 3600);
```

---

## Timezone

`Fight\Common\Domain\Value\DateTime\Timezone`

An immutable value object that wraps a `DateTimeZone` name with construction-time
validation. Passed to `Scheduler` to anchor cron and datetime schedule comparisons.

```php
use Fight\Common\Domain\Value\DateTime\Timezone;

$tz = new Timezone('America/Chicago');
$tz->value();     // 'America/Chicago'
(string) $tz;     // 'America/Chicago'

Timezone::fromString('Europe/London');  // factory method

new Timezone('Not/Real');  // throws DomainException
```

---

## Symfony Configuration

```yaml
# config/packages/common_scheduler.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    Fight\Common\Domain\Value\DateTime\Timezone:
        arguments:
            - '%env(APP_TIMEZONE)%'

    Fight\Common\Application\Scheduler\Scheduler:
        arguments:
            - '@Fight\Common\Domain\Value\DateTime\Timezone'
            - '%kernel.cache_dir%/scheduler'
            - '@Fight\Common\Application\Process\ProcessRunner'
            - '@logger'
            - '@Fight\Common\Application\Mail\MailService'
            - '%env(SCHEDULER_FROM_EMAIL)%'

    Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner: ~

    Fight\Common\Application\Process\ProcessRunner:
        alias: Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner
```

Then add the entry point to the project (e.g. `bin/scheduler.php`):

```php
#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

$scheduler = $container->get(Scheduler::class);

$scheduler->addCommand(
    'cache-warm',
    '*/15 * * * *',
    'bin/console cache:warmup --env=prod',
    output: '/var/log/scheduler/cache-warm.log',
    notify: 'ops@example.com'
);

$scheduler->addJob('report', '0 6 * * 1', fn() => $container->get(WeeklyReporter::class)->run());

$scheduler->run();
```

And the crontab entry that runs every minute:

```
* * * * * /usr/bin/php /var/www/html/bin/scheduler.php >> /var/log/scheduler.log 2>&1
```

---

## Usage Examples

### Basic Cron Job

```php
$scheduler->addJob(
    name:     'send-reminders',
    schedule: '0 9 * * *',        // 09:00 every day
    job:      fn() => $reminderService->sendAll()
);

$scheduler->run();
```

### Logging Output to a File

```php
$scheduler->addCommand(
    name:     'database-backup',
    schedule: '0 0 * * *',
    command:  'bin/backup.sh',
    output:   '/var/log/backup.log',
    notify:   'dba@example.com'
);
```

### Conditional Schedule

```php
$scheduler->addJob(
    name:     'maintenance-cleanup',
    schedule: fn() => $this->featureFlags->isMaintenanceWindow(),
    job:      fn() => $this->cleanupService->run()
);
```

### One-Time Scheduled Run

```php
$scheduler->addJob(
    name:     'data-migration',
    schedule: '2026-12-01 02:00:00',
    job:      fn() => $migrationService->run()
);
```

### Disabled Job

```php
$scheduler->addJob(
    name:     'experimental-sync',
    schedule: '*/5 * * * *',
    job:      $syncCallable,
    enabled:  false   // won't run until re-enabled
);
```
