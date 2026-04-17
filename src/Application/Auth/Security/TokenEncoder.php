<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\Security;

use DateTimeImmutable;
use Fight\Common\Application\Auth\Exception\TokenException;

/**
 * Interface TokenEncoder
 */
interface TokenEncoder
{
    /**
     * Encodes claims into a token
     *
     * @throws TokenException When an error occurs during encoding
     */
    public function encode(array $claims, DateTimeImmutable $expiration): string;
}
