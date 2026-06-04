<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Auth;

use Fight\Common\Domain\Auth\Exception\NonceAlreadyConsumedException;

/**
 * Interface NonceRepository
 */
interface NonceRepository
{
    /**
     * Consumes a nonce, preventing replay attacks
     *
     * @throws NonceAlreadyConsumedException When the nonce has already been consumed
     */
    public function consume(Nonce $nonce): void;

    /**
     * Removes expired nonces from storage
     */
    public function purgeExpired(): void;
}
