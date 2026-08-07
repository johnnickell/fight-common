<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing\Exception;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Reports an invalid event mapping or unsupported stored event identity
 */
final class EventMappingException extends DomainException
{
}
