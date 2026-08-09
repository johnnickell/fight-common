<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Repository\InMemory;

use DateTimeImmutable;
use Fight\Common\Domain\Auth\Exception\NonceAlreadyConsumedException;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Common\Domain\Auth\NonceRepository;

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
            throw new NonceAlreadyConsumedException('Nonce already consumed');
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
