<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

use Fight\Common\Application\Messaging\Event\EventHandlerFailure;

/**
 * Class EventPublicationHandlerFailure
 *
 * Portable snapshot of one failed event-handler invocation
 */
final readonly class EventPublicationHandlerFailure
{
    private const int MAX_DIAGNOSTIC_BYTES = 4096;

    /**
     * Constructs EventPublicationHandlerFailure
     */
    private function __construct(
        private string $callableDescription,
        private string $exceptionClass,
        private int $exceptionCode,
        private string $diagnosticMessage,
    ) {
    }

    /**
     * Creates a portable snapshot of one transient dispatcher handler failure
     */
    public static function fromHandlerFailure(EventHandlerFailure $failure): self
    {
        $throwable = $failure->throwable();

        return new self(
            $failure->callableDescription(),
            $throwable::class,
            $throwable->getCode(),
            self::normalizeDiagnosticMessage($throwable->getMessage()),
        );
    }

    /**
     * Returns the invoked callable description
     */
    public function callableDescription(): string
    {
        return $this->callableDescription;
    }

    /**
     * Returns the failed exception class name
     */
    public function exceptionClass(): string
    {
        return $this->exceptionClass;
    }

    /**
     * Returns the failed exception code
     */
    public function exceptionCode(): int
    {
        return $this->exceptionCode;
    }

    /**
     * Returns the bounded, valid UTF-8 diagnostic message
     */
    public function diagnosticMessage(): string
    {
        return $this->diagnosticMessage;
    }

    /**
     * Returns a valid, control-safe UTF-8 diagnostic of at most 4 KiB
     */
    private static function normalizeDiagnosticMessage(string $message): string
    {
        $validUtf8 = (string) iconv('UTF-8', 'UTF-8//IGNORE', $message);
        $controlSafe = (string) preg_replace(
            '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u',
            '',
            $validUtf8,
        );

        return mb_strcut($controlSafe, 0, self::MAX_DIAGNOSTIC_BYTES, 'UTF-8');
    }
}
