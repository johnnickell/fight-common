<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing\Exception;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Class UnrecognizedEventException
 */
final class UnrecognizedEventException extends DomainException
{
    /**
     * Constructs UnrecognizedEventException
     */
    public function __construct(Event $event)
    {
        parent::__construct(sprintf('Unrecognized event: %s', $event::class));
    }
}
