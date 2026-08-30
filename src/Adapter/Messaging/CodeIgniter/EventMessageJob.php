<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\CodeIgniter;

use CodeIgniter\Config\Services as CoreServices;
use CodeIgniter\Queue\BaseJob;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageType;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class EventMessageJob
 */
final class EventMessageJob extends BaseJob
{
    public const string HANDLER_SERVICE = 'fightEventMessageHandler';

    /**
     * Handles the queued event envelope
     */
    public function process(): void
    {
        if (($this->data['kind'] ?? null) !== MessageType::EVENT->value) {
            throw new InvalidArgumentException('Queued payload kind must be event.');
        }

        $serializedMessage = $this->data['message'] ?? null;

        if (!is_array($serializedMessage)) {
            throw new InvalidArgumentException('Queued payload message must be an array.');
        }

        $handler = CoreServices::get(self::HANDLER_SERVICE);

        if (!$handler instanceof EventMessageHandler) {
            throw new RuntimeException(sprintf(
                'CodeIgniter service "%s" must resolve to %s.',
                self::HANDLER_SERVICE,
                EventMessageHandler::class
            ));
        }

        $handler(EventMessage::arrayDeserialize($serializedMessage));
    }
}
