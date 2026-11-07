<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Repository\InMemory;

use DateTimeImmutable;
use Fight\Common\Adapter\Repository\InMemory\InMemoryNonceRepository;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Common\Domain\Auth\Exception\NonceAlreadyConsumedException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryNonceRepository::class)]
class InMemoryNonceRepositoryTest extends UnitTestCase
{
    private InMemoryNonceRepository $nonceRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nonceRepository = new InMemoryNonceRepository();
    }

    public function test_that_consume_succeeds_for_new_nonce(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));
        $this->nonceRepository->consume($nonce);
        $this->addToAssertionCount(1);
    }

    public function test_that_consume_throws_when_nonce_already_consumed(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));
        $this->nonceRepository->consume($nonce);

        $this->expectException(NonceAlreadyConsumedException::class);
        $this->nonceRepository->consume($nonce);
    }

    public function test_that_different_nonces_can_be_consumed_independently(): void
    {
        $this->nonceRepository->consume(new Nonce('nonce-a', new DateTimeImmutable('+5 minutes')));
        $this->nonceRepository->consume(new Nonce('nonce-b', new DateTimeImmutable('+5 minutes')));
        $this->addToAssertionCount(1);
    }

    public function test_that_purge_expired_removes_expired_nonces(): void
    {
        $expired = new Nonce('old-nonce', new DateTimeImmutable('-1 second'));
        $this->nonceRepository->consume($expired);

        $this->nonceRepository->purgeExpired();

        // After purging, the same nonce value can be consumed again
        $this->nonceRepository->consume(new Nonce('old-nonce', new DateTimeImmutable('+5 minutes')));
        $this->addToAssertionCount(1);
    }

    public function test_that_purge_expired_does_not_remove_valid_nonces(): void
    {
        $valid = new Nonce('current-nonce', new DateTimeImmutable('+5 minutes'));
        $this->nonceRepository->consume($valid);

        $this->nonceRepository->purgeExpired();

        $this->expectException(NonceAlreadyConsumedException::class);
        $this->nonceRepository->consume(new Nonce('current-nonce', new DateTimeImmutable('+5 minutes')));
    }
}
