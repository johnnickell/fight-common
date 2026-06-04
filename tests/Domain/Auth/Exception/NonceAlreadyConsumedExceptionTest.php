<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Auth\Exception;

use Fight\Common\Domain\Auth\Exception\NonceAlreadyConsumedException;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NonceAlreadyConsumedException::class)]
class NonceAlreadyConsumedExceptionTest extends UnitTestCase
{
    public function test_that_exception_extends_domain_exception(): void
    {
        $e = new NonceAlreadyConsumedException('Nonce already consumed');

        self::assertInstanceOf(DomainException::class, $e);
        self::assertSame('Nonce already consumed', $e->getMessage());
    }
}
