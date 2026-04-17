<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\Security;

use Fight\Common\Application\Auth\Exception\TokenException;

/**
 * Interface TokenDecoder
 */
interface TokenDecoder
{
    /**
     * Decodes a token into claims
     *
     * @throws TokenException When an error occurs during decoding
     */
    public function decode(string $token): array;
}
