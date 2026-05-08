<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\HttpClient\Exception;

use Fight\Common\Application\HttpClient\Exception\RequestException;
use Fight\Common\Application\HttpClient\Exception\TransferException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;

#[CoversClass(RequestException::class)]
class RequestExceptionTest extends UnitTestCase
{
    public function test_that_construction_with_message_and_request_sets_message(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);

        $exception = new RequestException('Something went wrong', $request);

        self::assertSame('Something went wrong', $exception->getMessage());
    }

    public function test_that_get_request_returns_the_request_passed_to_constructor(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);

        $exception = new RequestException('Error', $request);

        self::assertSame($request, $exception->getRequest());
    }

    public function test_that_request_exception_extends_transfer_exception(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);

        $exception = new RequestException('Error', $request);

        self::assertInstanceOf(TransferException::class, $exception);
    }
}
