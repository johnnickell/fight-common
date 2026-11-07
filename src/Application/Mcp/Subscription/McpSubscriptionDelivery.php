<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription;

use Closure;
use RuntimeException;

/**
 * Class McpSubscriptionDelivery
 *
 * @internal Defers guarded work, never authorized bytes, across the transport scheduling pause
 */
final class McpSubscriptionDelivery
{
    private int $offset = 0;
    private ?string $digest = null;
    private bool $complete = false;

    /**
     * Constructs McpSubscriptionDelivery
     *
     * @phpstan-param Closure(callable(string): bool): void $produce
     */
    public function __construct(private readonly Closure $produce)
    {
    }

    /**
     * Sends one freshly guarded complete frame or fragment at the actual consumer byte handoff
     *
     * Small PSR reads re-source and re-admit every fragment; a changed frame aborts instead of splicing versions.
     * Native emitters pass the full bounded frame directly to write/flush, with no Fiber yield under the guard.
     * Only an offset/digest survive a fragment, never a payload or permission. False means another fragment remains.
     *
     * @phpstan-param callable(string): void $emit
     */
    public function send(callable $emit, int $length = 65536): bool
    {
        if ($this->complete) {
            throw new RuntimeException('Task disclosure already completed.');
        }

        $emitted = false;
        ($this->produce)(function (string $frame) use ($emit, $length, &$emitted): bool {
            if ($emitted || $length < 1) {
                throw new RuntimeException('Invalid Task disclosure output.');
            }

            $emitted = true;
            $digest = hash('sha256', $frame);
            if ($this->digest !== null && $this->digest !== $digest) {
                throw new RuntimeException('Task disclosure changed during a fragmented read.');
            }

            $this->digest = $digest;
            $chunk = substr($frame, $this->offset, $length);
            $emit($chunk);
            $this->offset += strlen($chunk);
            $this->complete = $this->offset === strlen($frame);

            return $this->complete;
        });
        if (!$emitted) {
            if ($this->offset !== 0) {
                throw new RuntimeException('Task disclosure interrupted during a fragmented read.');
            }

            $this->complete = true;
        }

        return $this->complete;
    }
}
