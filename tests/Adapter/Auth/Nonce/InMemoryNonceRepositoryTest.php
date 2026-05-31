<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Nonce;

use DateTimeImmutable;
use Fight\Common\Adapter\Auth\Nonce\InMemoryNonceRepository;
use Fight\Common\Application\Auth\Exception\AuthException;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryNonceRepository::class)]
class InMemoryNonceRepositoryTest extends UnitTestCase
{
    private InMemoryNonceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemoryNonceRepository();
    }

    public function test_that_consume_succeeds_for_new_nonce(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));
        $this->repository->consume($nonce);
        $this->addToAssertionCount(1);
    }

    public function test_that_consume_throws_when_nonce_already_consumed(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));
        $this->repository->consume($nonce);

        $this->expectException(AuthException::class);
        $this->repository->consume($nonce);
    }

    public function test_that_different_nonces_can_be_consumed_independently(): void
    {
        $this->repository->consume(new Nonce('nonce-a', new DateTimeImmutable('+5 minutes')));
        $this->repository->consume(new Nonce('nonce-b', new DateTimeImmutable('+5 minutes')));
        $this->addToAssertionCount(1);
    }

    public function test_that_purge_expired_removes_expired_nonces(): void
    {
        $expired = new Nonce('old-nonce', new DateTimeImmutable('-1 second'));
        $this->repository->consume($expired);

        $this->repository->purgeExpired();

        // After purging, the same nonce value can be consumed again
        $this->repository->consume(new Nonce('old-nonce', new DateTimeImmutable('+5 minutes')));
        $this->addToAssertionCount(1);
    }

    public function test_that_purge_expired_does_not_remove_valid_nonces(): void
    {
        $valid = new Nonce('current-nonce', new DateTimeImmutable('+5 minutes'));
        $this->repository->consume($valid);

        $this->repository->purgeExpired();

        $this->expectException(AuthException::class);
        $this->repository->consume(new Nonce('current-nonce', new DateTimeImmutable('+5 minutes')));
    }
}
