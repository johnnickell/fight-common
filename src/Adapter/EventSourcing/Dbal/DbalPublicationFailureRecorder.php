<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;

/**
 * Class DbalPublicationFailureRecorder
 *
 * Doctrine DBAL adapter for durable publication-failure evidence
 */
final readonly class DbalPublicationFailureRecorder implements PublicationFailureRecorder
{
    /**
     * Constructs DbalPublicationFailureRecorder
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Records the first failure for one publication position atomically
     */
    public function record(EventPublicationFailure $failure): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($failure): void {
                $connection->insert('publication_failures', [
                    'publication_name'     => $failure->publicationName(),
                    'aggregate_name'       => $failure->streamId()->aggregateName(),
                    'aggregate_identifier' => $failure->streamId()->identifier(),
                    'event_name'           => $failure->eventName(),
                    'schema_version'       => $failure->schemaVersion(),
                    'stream_version'       => $failure->streamVersion(),
                    'global_position'      => $failure->globalPosition(),
                    'message_id'           => $failure->messageId()->toString(),
                    'dispatch_started_at'  => $failure->dispatchStartedAt()->format('Y-m-d\TH:i:s.uP')
                ]);

                foreach ($failure->handlerFailures() as $handlerPosition => $handlerFailure) {
                    $connection->insert('publication_handler_failures', [
                        'publication_name'     => $failure->publicationName(),
                        'global_position'      => $failure->globalPosition(),
                        'handler_position'     => $handlerPosition,
                        'callable_description' => $handlerFailure->callableDescription(),
                        'exception_class'      => $handlerFailure->exceptionClass(),
                        'exception_code'       => $handlerFailure->exceptionCode(),
                        'diagnostic_message'   => $handlerFailure->diagnosticMessage()
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // A retry keeps the first evidence stored for this correlation key.
        }
    }
}
