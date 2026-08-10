<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Logging;

use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\EventSourcing\EventPublicationHandlerFailure;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggingPublicationFailureRecorder
 *
 * Logs portable publication-failure evidence before recording it
 */
final readonly class LoggingPublicationFailureRecorder implements PublicationFailureRecorder
{
    /**
     * Constructs LoggingPublicationFailureRecorder
     */
    public function __construct(
        private PublicationFailureRecorder $recorder,
        private LoggerInterface $logger,
        private string $logLevel = LogLevel::ERROR,
    ) {
    }

    /**
     * Logs and records one aggregated publication failure
     */
    public function record(EventPublicationFailure $failure): void
    {
        $this->logger->log($this->logLevel, '[Event Publication]: Failure', [
            'publication_name'     => $failure->publicationName(),
            'aggregate_name'       => $failure->streamId()->aggregateName(),
            'aggregate_identifier' => $failure->streamId()->identifier(),
            'event_name'           => $failure->eventName(),
            'schema_version'       => $failure->schemaVersion(),
            'stream_version'       => $failure->streamVersion(),
            'global_position'      => $failure->globalPosition(),
            'message_id'           => $failure->messageId()->toString(),
            'dispatch_started_at'  => $failure->dispatchStartedAt()->format('Y-m-d\TH:i:s.uP'),
            'handler_failures'     => array_map(
                static fn (EventPublicationHandlerFailure $handlerFailure): array => [
                    'callable_description' => $handlerFailure->callableDescription(),
                    'exception_class'      => $handlerFailure->exceptionClass(),
                    'exception_code'       => $handlerFailure->exceptionCode(),
                    'diagnostic_message'   => $handlerFailure->diagnosticMessage()
                ],
                $failure->handlerFailures()
            )
        ]);

        $this->recorder->record($failure);
    }
}
