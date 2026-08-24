<?php

declare(strict_types=1);

use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Mail\Message\Attachment;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Process\Process as ApplicationProcess;
use Fight\Common\Application\Process\ProcessErrorBehavior;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Application\Scheduler\Scheduler;
use Fight\Common\Domain\Value\DateTime\Timezone;

final class LegacySchedulerProcessProbe
{
    public const string OUT = 'out';
    public const string ERR = 'err';

    /** @var list<string> */
    public static array $defaultCommands = [];

    /** @var list<int> */
    public static array $reportedExitCodes = [];

    public function __construct(private readonly string $command)
    {
    }

    public static function fromShellCommandline(string $command): self
    {
        self::$defaultCommands[] = $command;

        return new self($command);
    }

    public function run(callable $callback): int
    {
        if ($this->isSuccessful()) {
            $callback(self::OUT, 'scheduler command');

            return 0;
        }

        return 1;
    }

    public function isSuccessful(): bool
    {
        return $this->command !== 'false';
    }

    public function getExitCode(): int
    {
        $exitCode = $this->isSuccessful() ? 0 : 1;
        self::$reportedExitCodes[] = $exitCode;

        return $exitCode;
    }
}

interface LegacySchedulerLoggerInterfaceProbe
{
    /** @param array<string, mixed> $context */
    public function emergency(string|\Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function alert(string|\Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function critical(string|\Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string|\Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function warning(string|\Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function notice(string|\Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function info(string|\Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function debug(string|\Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void;
}

class_alias(LegacySchedulerLoggerInterfaceProbe::class, 'Psr\\Log\\LoggerInterface');

$runtimeDeprecations = [];
set_error_handler(
    static function (int $severity, string $message) use (&$runtimeDeprecations): bool {
        if ($severity !== E_DEPRECATED && $severity !== E_USER_DEPRECATED) {
            return false;
        }

        $runtimeDeprecations[] = [
            'severity' => $severity === E_DEPRECATED ? 'E_DEPRECATED' : 'E_USER_DEPRECATED',
            'message'  => $message
        ];

        return true;
    }
);

try {
    require $argv[1];
} catch (\Throwable $throwable) {
    restore_error_handler();

    throw $throwable;
}

final class LegacySchedulerLoggerProbe implements LegacySchedulerLoggerInterfaceProbe
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $context */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $exception = $context['exception'] ?? null;
        $this->records[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
            'context' => [
                'keys'      => array_keys($context),
                'exception' => $exception instanceof \Throwable ? [
                    'class'   => $exception::class,
                    'message' => $exception->getMessage(),
                    'code'    => (int) $exception->getCode()
                ] : null
            ]
        ];
    }
}

final class LegacySchedulerMailProbe implements MailFactory, MailTransport
{
    /** @var list<array<string, mixed>> */
    public array $messages = [];

    public function send(MailMessage $message): void
    {
        $content = $message->getContent()[0] ?? [];
        $bodyLines = explode("\n", $content['content'] ?? '');
        $this->messages[] = [
            'subject' => $message->getSubject(),
            'from'    => $message->getFrom(),
            'to'      => $message->getTo(),
            'content' => [
                'environment'  => $bodyLines[0] ?? null,
                'error'        => $bodyLines[2] ?? null,
                'code'         => $bodyLines[4] ?? null,
                'content_type' => $content['content_type'] ?? null,
                'charset'      => $content['charset'] ?? null
            ]
        ];
    }

    public function createMessage(): MailMessage
    {
        return MailMessage::create();
    }

    public function createAttachmentFromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        throw new \LogicException('Attachments are outside the Scheduler compatibility probe.');
    }

    public function createAttachmentFromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        throw new \LogicException('Attachments are outside the Scheduler compatibility probe.');
    }

    public function generateEmbedId(): string
    {
        throw new \LogicException('Attachments are outside the Scheduler compatibility probe.');
    }
}

class_alias(LegacySchedulerProcessProbe::class, 'Symfony\\Component\\Process\\Process');

$timezone = new Timezone('UTC');
$schedulerDirectory = sys_get_temp_dir().'/fight-common-public-api-probe-'.bin2hex(random_bytes(8));
$schedulerOutput = $schedulerDirectory.'/callable-output.log';
$schedulerCommandOutput = $schedulerDirectory.'/command-output.log';
$findings = [
    [
        'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-construction-passed',
        'evidence_id' => 'fight-common.behavior.scheduler-legacy-construction',
        'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
        'status'      => 'passed'
    ],
    [
        'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-command-passed',
        'evidence_id' => 'fight-common.behavior.scheduler-legacy-command',
        'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
        'status'      => 'passed'
    ]
];

mkdir($schedulerDirectory, 0777, true);

try {
    $processFactoryCommands = [];
    $logger = new LegacySchedulerLoggerProbe();
    $mailProbe = new LegacySchedulerMailProbe();
    $mailService = new MailService($mailProbe, $mailProbe);
    $processFactory = static function (string $command) use (&$processFactoryCommands): LegacySchedulerProcessProbe {
        $processFactoryCommands[] = $command;

        return new LegacySchedulerProcessProbe($command);
    };
    $schedulerConstructions = [
        'two_argument'        => new Scheduler($timezone, $schedulerDirectory),
        'positional_optional' => new Scheduler(
            $timezone,
            $schedulerDirectory,
            $logger,
            $mailService,
            'scheduler@example.com',
            $processFactory
        ),
        'named_arguments'     => new Scheduler(
            timezone: $timezone,
            tempDirectory: $schedulerDirectory,
            logger: null,
            mailService: null,
            fromEmail: 'scheduler@example.com',
            processFactory: $processFactory
        )
    ];

    $schedulerConstructions['two_argument']->addJob(
        'consumer-callable',
        static fn(): bool => true,
        static function (): bool {
            echo 'scheduler callable';

            return true;
        },
        output: $schedulerOutput
    );
    $schedulerConstructions['two_argument']->addCommand(
        'consumer-default-command',
        static fn(): bool => true,
        'default-command',
        output: $schedulerCommandOutput
    );
    $schedulerConstructions['named_arguments']->addCommand(
        'consumer-factory-command',
        static fn(): bool => true,
        'factory-command',
        output: $schedulerCommandOutput
    );
    $schedulerConstructions['positional_optional']->addCommand(
        'consumer-failing-command',
        static fn(): bool => true,
        'false',
        notify: 'operator@example.com',
        environment: 'consumer'
    );
    $schedulerConstructions['two_argument']->run();
    $schedulerConstructions['named_arguments']->run();
    $schedulerConstructions['positional_optional']->run();
    $failureLock = $schedulerDirectory.'/consumer-failing-command.lock';
    $observeReleasedLock = static function (string $lockFile): bool {
        $handle = fopen($lockFile, 'rb+');
        if ($handle === false) {
            return false;
        }

        $acquired = flock($handle, LOCK_EX | LOCK_NB);
        if ($acquired) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return $acquired;
    };
    $lockReacquiredAfterAttempts = [$observeReleasedLock($failureLock)];
    $schedulerConstructions['positional_optional']->run();
    $lockReacquiredAfterAttempts[] = $observeReleasedLock($failureLock);
    $schedulerObservation = [
        'construction_styles'      => array_keys($schedulerConstructions),
        'callable_output'          => file_get_contents($schedulerOutput),
        'command_output'           => file_get_contents($schedulerCommandOutput),
        'default_process_commands' => LegacySchedulerProcessProbe::$defaultCommands,
        'factory_process_commands' => $processFactoryCommands,
        'non_zero_failure'         => [
            'attempts'                        => 2,
            'reported_exit_codes'             => LegacySchedulerProcessProbe::$reportedExitCodes,
            'logs'                            => $logger->records,
            'notification_count'              => count($mailProbe->messages),
            'notifications'                   => $mailProbe->messages,
            'lock_reacquired_after_attempts' => $lockReacquiredAfterAttempts
        ]
    ];

    if (method_exists(Scheduler::class, 'withProcessRunner')) {
        $portableOutput = $schedulerDirectory.'/portable-command-output.log';
        $portableRunner = new class implements ProcessRunner {
            /** @var list<ApplicationProcess> */
            private array $processes = [];
            /** @var list<string> */
            public array $commands = [];

            public function attach(ApplicationProcess $process): void
            {
                $this->processes[] = $process;
                $this->commands[] = $process->command();
            }

            public function clear(): void
            {
                $this->processes = [];
            }

            public function run(?ProcessErrorBehavior $errorBehavior = null): void
            {
                foreach ($this->processes as $process) {
                    $stdout = $process->stdout();
                    if (is_callable($stdout)) {
                        $stdout('scheduler portable command');
                    }
                }

                $this->clear();
            }
        };
        $portableScheduler = Scheduler::withProcessRunner($timezone, $schedulerDirectory, $portableRunner);
        $portableScheduler->addCommand(
            'consumer-portable-command',
            static fn(): bool => true,
            'portable-command',
            output: $portableOutput
        );
        $portableScheduler->run();
        $schedulerObservation['portable_process_runner'] = [
            'commands' => $portableRunner->commands,
            'output'   => file_get_contents($portableOutput)
        ];
        $findings[] = [
            'finding_id'  => 'release.compatibility.consumer.scheduler-portable-runner-passed',
            'evidence_id' => 'fight-common.behavior.scheduler-portable-runner',
            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
            'status'      => 'passed'
        ];
    }
} finally {
    foreach (glob($schedulerDirectory.'/*') ?: [] as $schedulerArtifact) {
        unlink($schedulerArtifact);
    }

    rmdir($schedulerDirectory);
    restore_error_handler();
}

echo json_encode(
    [
        'schema_version' => 'fight-common.scheduler-probe/v1',
        'findings'       => $findings,
        'observations'   => [
            'runtime_deprecations' => $runtimeDeprecations,
            'scheduler'            => $schedulerObservation
        ]
    ],
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
)."\n";
