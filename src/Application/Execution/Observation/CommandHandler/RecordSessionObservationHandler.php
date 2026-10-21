<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Observation\CommandHandler;

use Fight\Common\Application\Execution\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Execution\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class RecordSessionObservationHandler
 */
final readonly class RecordSessionObservationHandler implements CommandHandler
{
    /**
     * Constructs RecordSessionObservationHandler
     */
    public function __construct(private ObservationRecorder $recorder)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RecordSessionObservation::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RecordSessionObservation) {
            throw new InvalidArgumentException('Unexpected recording command.');
        }

        $this->recorder->record($command->observation());
    }
}
