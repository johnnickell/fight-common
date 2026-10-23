<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Observation\CommandHandler;

use Fight\Common\Application\Execution\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Execution\Observation\Command\RecordProtectedDetail;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class RecordProtectedDetailHandler
 */
final readonly class RecordProtectedDetailHandler implements CommandHandler
{
    /**
     * Constructs RecordProtectedDetailHandler
     */
    public function __construct(private ObservationRecorder $recorder)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RecordProtectedDetail::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RecordProtectedDetail) {
            throw new InvalidArgumentException('Unexpected recording command.');
        }

        $this->recorder->record($command->observation(), $command->content());
    }
}
