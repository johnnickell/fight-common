<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Http;

use Fiber;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\Protocol\McpRequest;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionDelivery;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Tool\McpToolExecution;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Class McpEventStream
 *
 * @internal A single-use PSR body driven by the MCP emitter, not a retained event log
 */
final class McpEventStream implements StreamInterface
{
    /**
     * @var Fiber<mixed, mixed, void, mixed>
     */
    private readonly Fiber $producer;
    private readonly McpToolExecution|McpSubscriptionExecution $execution;
    private string $pending = '';
    private ?McpSubscriptionDelivery $delivery = null;
    private int $position = 0;
    private bool $closed = false;
    private bool $delivering = false;

    /**
     * Constructs McpEventStream
     */
    public function __construct(
        McpRequest $request,
        McpResponder $responder,
        McpDiagnostics $diagnostics,
        ?McpSubscriptionExecution $subscription = null
    ) {
        if ($subscription !== null) {
            $this->execution = $subscription;
            $this->producer = new Fiber(fn() => $subscription->run(function (string $frame): void {
                $this->pending = $frame;
                Fiber::suspend();
            }, $diagnostics, function (McpSubscriptionDelivery $delivery): void {
                $this->delivery = $delivery;
                Fiber::suspend();
            }));

            return;
        }

        $this->execution = new McpToolExecution($request, function (array $message): void {
            $this->pending = 'data: '.json_encode($message, JSON_THROW_ON_ERROR)."\n\n";
            Fiber::suspend();
        }, $diagnostics);
        $this->producer = new Fiber(fn() => $this->execution->run($responder));
    }

    /**
     * Sets cancellation and resumes suspended work so stopping remains cooperative
     */
    public function close(): void
    {
        $this->closed = true;
        $this->pending = '';
        $this->delivery = null;
        $this->execution->cancel();
        if (!$this->delivering && $this->producer->isSuspended()) {
            $this->producer->resume();
        }
    }

    /**
     * @inheritDoc
     */
    public function detach(): mixed
    {
        $this->close();

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        return $this->closed
            || ($this->pending === '' && $this->delivery === null && $this->producer->isTerminated());
    }

    /**
     * @inheritDoc
     */
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('An MCP event stream cannot be replayed.');
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        throw new RuntimeException('An MCP event stream is read-only.');
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return !$this->closed;
    }

    /**
     * Returns at most one available frame fragment without running ahead of the emitter
     */
    public function read(int $length): string
    {
        if ($length < 0 || $this->closed) {
            throw new RuntimeException('An MCP event stream requires an open body and nonnegative read length.');
        }

        if ($length === 0 || $this->eof()) {
            return '';
        }

        if ($this->pending === '' && $this->delivery === null) {
            $this->produce();
        }

        if ($this->delivery !== null) {
            $chunk = '';
            $this->sendDelivery(function (string $bytes) use (&$chunk): void {
                if (!$this->closed) {
                    $chunk = $bytes;
                    $this->position += strlen($bytes);
                }
            }, $length);
            if ($chunk === '' && !$this->eof()) {
                return $this->read($length);
            }

            return $chunk;
        }

        // Consumer callbacks may close this body while start/resume is still on the read stack.
        if ($this->closed) {
            $this->pending = '';

            return '';
        }

        $chunk = substr($this->pending, 0, $length);
        $this->pending = substr($this->pending, strlen($chunk));
        $this->position += strlen($chunk);

        return $chunk;
    }

    /**
     * Sends one bounded native write directly inside any Task guard without a queued-byte or Fiber-yield gap
     *
     * @phpstan-param callable(string): void $emit
     */
    public function emitNext(callable $emit): void
    {
        if ($this->closed || $this->eof()) {
            return;
        }

        if ($this->pending === '' && $this->delivery === null) {
            $this->produce();
        }

        if ($this->delivery !== null) {
            $this->sendDelivery(function (string $bytes) use ($emit): void {
                if (!$this->closed) {
                    $emit($bytes);
                    $this->position += strlen($bytes);
                }
            }, 65536);

            return;
        }

        if (!$this->closed) {
            $emit($this->read(8192));
        }
    }

    /**
     * Returns remaining bytes for explicit buffering consumers without claiming live delivery
     */
    public function getContents(): string
    {
        if ($this->closed) {
            throw new RuntimeException('An MCP event stream is closed.');
        }

        $contents = '';
        while (!$this->eof()) {
            $contents .= $this->read(8192);
        }

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }

    /**
     * Runs deferred protected work and returns faults to the execution's single safe reporting/closure owner
     *
     * @phpstan-param callable(string): void $emit
     */
    private function sendDelivery(callable $emit, int $length): void
    {
        $this->delivering = true;
        try {
            if ($this->delivery->send($emit, $length)) {
                $this->delivery = null;
            }
        } catch (Throwable $throwable) {
            $this->delivery = null;
            $this->producer->throw($throwable);
        } finally {
            $this->delivering = false;
            if ($this->closed && $this->producer->isSuspended()) {
                $this->producer->resume();
            }
        }
    }

    /**
     * Runs producer callbacks that may reenter and close this body before returning
     */
    private function produce(): void
    {
        if ($this->producer->isStarted()) {
            $this->producer->resume();
        } else {
            $this->producer->start();
        }
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        try {
            return $this->getContents();
        } catch (Throwable) {
            // PSR-7 forbids throwing from string conversion; emitters use read() instead.
            return '';
        }
    }
}
