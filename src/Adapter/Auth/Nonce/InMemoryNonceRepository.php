<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Nonce;

use DateTimeImmutable;
use Fight\Common\Application\Auth\Exception\AuthException;
use Fight\Common\Application\Auth\NonceRepository;
use Fight\Common\Application\HttpFoundation\HttpStatus;
use Fight\Common\Domain\Auth\Nonce;

/**
 * Class InMemoryNonceRepository
 */
final class InMemoryNonceRepository implements NonceRepository
{
    /** @var array<string, DateTimeImmutable> */
    private array $consumed = [];

    /**
     * @inheritDoc
     */
    public function consume(Nonce $nonce): void
    {
        $this->purgeExpired();

        if (isset($this->consumed[$nonce->value()])) {
            throw new AuthException('Nonce already consumed', HttpStatus::UNAUTHORIZED);
        }

        $this->consumed[$nonce->value()] = $nonce->expiresAt();
    }

    /**
     * @inheritDoc
     */
    public function purgeExpired(): void
    {
        $now = new DateTimeImmutable();

        foreach ($this->consumed as $value => $expiresAt) {
            if ($expiresAt < $now) {
                unset($this->consumed[$value]);
            }
        }
    }
}
