<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Guzzle;

use GuzzleHttp\Exception as GuzzleExceptions;
use GuzzleHttp\Promise\PromiseInterface;
use Fight\Common\Application\HttpClient\Exception as FightExceptions;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Domain\Exception\MethodCallException;
use Fight\Common\Domain\Exception\RuntimeException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class GuzzlePromise
 */
final class GuzzlePromise implements Promise
{
    private readonly PromiseInterface $promise;
    private string $state;
    private ?ResponseInterface $response;
    private ?Throwable $exception;

    /**
     * Constructs GuzzlePromise
     */
    public function __construct(PromiseInterface $promise, private readonly RequestInterface $request)
    {
        $this->state = Promise::PENDING;
        $this->promise = $promise->then(
            function (ResponseInterface $response) {
                $this->response = $response;
                $this->state = Promise::FULFILLED;

                return $response;
            },
            function (Throwable $reason) use ($request): void {
                if ($reason instanceof FightExceptions\Exception) {
                    $this->state = Promise::REJECTED;
                    $this->exception = $reason;

                    throw $this->exception;
                }

                if (!($reason instanceof GuzzleExceptions\GuzzleException)) {
                    $this->state = Promise::REJECTED;
                    $this->exception = new RuntimeException('Invalid reason');

                    throw $this->exception;
                }

                $this->state = Promise::REJECTED;
                $this->exception = $this->handleException($reason);

                throw $this->exception;
            }
        );
    }

    /**
     * @inheritDoc
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): static
    {
        return new static(
            $this->promise->then($onFulfilled, $onRejected),
            $this->request
        );
    }

    /**
     * @inheritDoc
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @inheritDoc
     */
    public function getResponse(): ResponseInterface
    {
        if ($this->state !== Promise::FULFILLED) {
            throw new MethodCallException('Response not available for the current state');
        }

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function getException(): Throwable
    {
        if ($this->state !== Promise::REJECTED) {
            throw new MethodCallException('Error not available for the current state');
        }

        return $this->exception;
    }

    /**
     * @inheritDoc
     */
    public function wait(): void
    {
        $this->promise->wait(false);
    }

    /**
     * Converts a Guzzle exception into a Novuso exception
     */
    protected function handleException(Throwable $exception): Throwable
    {
        if ($exception instanceof GuzzleExceptions\ConnectException) {
            return new FightExceptions\NetworkException(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception
            );
        }

        if ($exception instanceof GuzzleExceptions\RequestException) {
            if ($exception->hasResponse()) {
                return new FightExceptions\HttpException(
                    $exception->getMessage(),
                    $exception->getRequest(),
                    $exception->getResponse(),
                    $exception
                );
            }

            return new FightExceptions\RequestException(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception
            );
        }

        return new FightExceptions\TransferException(
            $exception->getMessage(),
            0,
            $exception
        );
    }
}
