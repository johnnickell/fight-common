<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fiber;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResponder;
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
    private readonly McpToolExecution $execution;
    private string $pending = '';
    private int $position = 0;
    private bool $closed = false;

    /**
     * Constructs McpEventStream
     */
    public function __construct(McpRequest $request, McpResponder $responder, McpDiagnostics $diagnostics)
    {
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
        $this->execution->cancel();
        if ($this->producer->isSuspended()) {
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
        return $this->closed || ($this->pending === '' && $this->producer->isTerminated());
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

        if ($this->pending === '') {
            if ($this->producer->isStarted()) {
                $this->producer->resume();
            } else {
                $this->producer->start();
            }
        }

        $chunk = substr($this->pending, 0, $length);
        $this->pending = substr($this->pending, strlen($chunk));
        $this->position += strlen($chunk);

        return $chunk;
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
