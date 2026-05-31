<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth;

use Fight\Common\Application\Auth\Exception\AuthException;
use Fight\Common\Domain\Auth\Nonce;

/**
 * Interface NonceRepository
 */
interface NonceRepository
{
    /**
     * Consumes a nonce, preventing replay attacks
     *
     * @throws AuthException When the nonce has already been consumed
     */
    public function consume(Nonce $nonce): void;

    /**
     * Removes expired nonces from storage
     */
    public function purgeExpired(): void;
}
