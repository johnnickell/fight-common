<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\CodeIgniter;

use CodeIgniter\Config\Services as CoreServices;
use CodeIgniter\Queue\BaseJob;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\MessageType;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class CommandMessageJob
 */
final class CommandMessageJob extends BaseJob
{
    public const string HANDLER_SERVICE = 'fightCommandMessageHandler';

    /**
     * Handles the queued command envelope
     */
    public function process(): void
    {
        if (($this->data['kind'] ?? null) !== MessageType::COMMAND->value) {
            throw new InvalidArgumentException('Queued payload kind must be command.');
        }

        $serializedMessage = $this->data['message'] ?? null;

        if (!is_array($serializedMessage)) {
            throw new InvalidArgumentException('Queued payload message must be an array.');
        }

        $handler = CoreServices::get(self::HANDLER_SERVICE);

        if (!$handler instanceof CommandMessageHandler) {
            throw new RuntimeException(sprintf(
                'CodeIgniter service "%s" must resolve to %s.',
                self::HANDLER_SERVICE,
                CommandMessageHandler::class
            ));
        }

        $handler(CommandMessage::arrayDeserialize($serializedMessage));
    }
}
