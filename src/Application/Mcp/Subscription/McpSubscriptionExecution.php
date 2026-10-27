<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription;

use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\RecoverNotices;
use RuntimeException;
use Throwable;

/**
 * Class McpSubscriptionExecution
 *
 * @internal Single-use cooperative execution, paused at each actual framed write boundary
 */
final class McpSubscriptionExecution
{
    private const string META = 'io.modelcontextprotocol/subscriptionId';
    private const array CHANGES = [
        'mcp.tools.list_changed'     => ['toolsListChanged', 'notifications/tools/list_changed'],
        'mcp.prompts.list_changed'   => ['promptsListChanged', 'notifications/prompts/list_changed'],
        'mcp.resources.list_changed' => ['resourcesListChanged', 'notifications/resources/list_changed'],
        'mcp.resources.updated'      => ['resourceSubscriptions', 'notifications/resources/updated']
    ];

    private bool $cancelled = false;
    private bool $started = false;
    private int $checks = 0;
    private int $expiresAt = 0;

    /**
     * Constructs McpSubscriptionExecution
     *
     * @phpstan-param list<string> $supported
     */
    public function __construct(
        private readonly int|string $requestId,
        private readonly McpSubscriptionFilter $requested,
        private readonly array $supported,
        private readonly QueryBus $queries,
        private readonly McpSubscriptionStore $store,
        private readonly McpSubscriptionAdmission $admission,
        private readonly NoticeSelection $selection,
        private readonly int $lifetimeSeconds,
        private readonly int $waitMicroseconds,
        private readonly int $maxFrameBytes
    ) {
    }

    /**
     * Sets cancellation for subsequent disclosure without interrupting consumer work or retracting sent bytes
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * Runs one bounded live follow without deleting or acknowledging retained notices
     *
     * @param callable(string): void $emit
     */
    public function run(callable $emit, McpDiagnostics $diagnostics): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $id = bin2hex(random_bytes(16));
        $owner = bin2hex(random_bytes(16));
        $opened = false;
        $deadline = hrtime(true) + $this->lifetimeSeconds * 1000000000;
        if ($this->shouldStop($deadline)) {
            return;
        }

        try {
            $subscriber = Notice::reference($this->admission->subscriber());
            if ($this->shouldStop($deadline)) {
                return;
            }

            $accepted = $this->requested->accepted($this->supported, $this->admission, $subscriber);
            if ($this->shouldStop($deadline)) {
                return;
            }

            // Even an unsupported/denied request may acknowledge an empty subset, without revealing why.
            $batch = $this->batch(null, true);
            if ($this->shouldStop($deadline)) {
                return;
            }

            $now = time();
            $this->expiresAt = $now + 5;
            $this->store->open($id, $owner, [
                'subscriber' => $subscriber,
                'selection'  => $this->selection->toArray(),
                'requested'  => $this->requested->toArray(),
                'accepted'   => $accepted
            ], $now, $this->expiresAt);
            $opened = true;
            if ($this->shouldStop($deadline)) {
                return;
            }

            $this->requireFreshness();
            if ($accepted !== $this->requested->accepted($this->supported, $this->admission, $subscriber)) {
                throw new RuntimeException('Subscription permission changed during opening.');
            }

            if ($this->shouldStop($deadline)) {
                return;
            }

            // Admission may outlast tracking or lose its row; opening permission alone is not a live lease.
            $this->renew($id, $owner);
            if ($this->shouldStop($deadline)) {
                return;
            }

            // Tracking can change policy; final admission must have no later consumer callback before disclosure.
            if ($accepted !== $this->requested->accepted($this->supported, $this->admission, $subscriber)) {
                throw new RuntimeException('Subscription permission changed during opening.');
            }

            $this->requireFreshness();
            if ($this->shouldStop($deadline)) {
                return;
            }

            $emit($this->frame([
                'jsonrpc' => '2.0',
                'method'  => 'notifications/subscriptions/acknowledged',
                'params'  => [
                    '_meta'         => [self::META => $this->requestId],
                    'notifications' => (object) $accepted
                ]
            ]));
            $continuation = $batch->continuation();
            while (!$this->shouldStop($deadline)) {
                $this->renew($id, $owner);
                if ($this->shouldStop($deadline)) {
                    break;
                }

                $batch = $this->batch($continuation);
                foreach ($batch->notices() as $view) {
                    if ($this->shouldStop($deadline)) {
                        break;
                    }

                    $this->renew($id, $owner);
                    if ($this->shouldStop($deadline)) {
                        break;
                    }

                    $message = $this->notification($view->notice(), $accepted, $subscriber);
                    if ($this->shouldStop($deadline)) {
                        break;
                    }

                    if ($message !== null) {
                        $this->renew($id, $owner);
                        if ($this->shouldStop($deadline)) {
                            break;
                        }

                        // Tracking callbacks may wait or change current policy; they are not a disclosure lease.
                        $message = $this->notification($view->notice(), $accepted, $subscriber);
                        $this->requireFreshness();
                        if ($this->shouldStop($deadline)) {
                            break;
                        }

                        if ($message !== null) {
                            $emit($this->frame($message));
                        }
                    }
                }

                $continuation = $batch->continuation();
                if (!$this->shouldStop($deadline)) {
                    // A finite transport comment permits idle disconnect observation without a new MCP event.
                    usleep($this->waitMicroseconds);
                    if ($this->shouldStop($deadline)) {
                        break;
                    }

                    $this->renew($id, $owner);
                    if ($this->shouldStop($deadline)) {
                        break;
                    }

                    $emit(": keepalive\n\n");
                }
            }

            if (!$this->cancelled) {
                $this->finish($id, $owner);
                $opened = false;
                // Expiry may complete an acknowledged stream, but callbacks can still cancel that final write.
                if ($this->cancelled) {
                    return;
                }

                $emit($this->frame([
                    'jsonrpc' => '2.0',
                    'id'      => $this->requestId,
                    'result'  => [
                        'resultType' => 'complete',
                        '_meta'      => [self::META => $this->requestId]
                    ]
                ]));
            }
        } catch (Throwable $throwable) {
            $this->diagnose($diagnostics, $throwable);
            // No successful completion on source, tracking, permission or framing failure.
        } finally {
            if ($opened) {
                try {
                    $this->store->close($id, $owner, time());
                } catch (Throwable $failure) {
                    $this->diagnose($diagnostics, $failure);
                }
            }
        }
    }

    /**
     * Completes owned tracking before graceful output without treating callbacks as a permission lease
     */
    private function finish(string $id, string $owner): void
    {
        $this->renew($id, $owner);
        $this->store->close($id, $owner, time());
        $this->requireFreshness();
    }

    /**
     * Validates ownership and freshness across a returned tracking callback without reviving an expired lease
     */
    private function renew(string $id, string $owner): void
    {
        $this->requireFreshness();
        $now = time();
        $expires = $now + 5;
        $this->store->heartbeat($id, $owner, $now, $expires);
        $this->expiresAt = $expires;
        $this->requireFreshness();
    }

    /**
     * Validates the independent technical lease after cooperative callbacks return
     */
    private function requireFreshness(): void
    {
        if (time() >= $this->expiresAt) {
            throw new RuntimeException('Subscription tracking freshness lost.');
        }
    }

    /**
     * Records a bounded work check while observing cancellation and monotonic lifetime
     *
     * @phpstan-impure
     */
    private function shouldStop(int $deadline): bool
    {
        ++$this->checks;

        return $this->cancelled || hrtime(true) >= $deadline || $this->checks > 12000;
    }

    /**
     * Returns a qualified shared Query batch or fails instead of presenting uncertainty as health
     */
    private function batch(?string $continuation, bool $start = false): NoticeRecoveryBatch
    {
        $batch = $this->queries->fetch(new RecoverNotices($this->selection, $continuation, startAtCurrent: $start));
        if (!$batch instanceof NoticeRecoveryBatch || $batch->status() !== 'complete') {
            // Conservative loss/incomplete/uncertain assessment requires polling/reload, never silent dropping.
            throw new RuntimeException('Live notice continuity is unavailable.');
        }

        return $batch;
    }

    /**
     * Creates only supported standard metadata after fresh complete-signal permission
     *
     * @phpstan-param array<string, mixed> $accepted
     *
     * @return array<string, mixed>|null
     */
    private function notification(Notice $notice, array $accepted, string $subscriber): ?array
    {
        $change = self::CHANGES[$notice->toArray()['type']] ?? null;
        if ($change === null || !isset($accepted[$change[0]])) {
            return null;
        }

        [$filter, $method] = $change;
        $uri = null;
        if ($filter === 'resourceSubscriptions') {
            $uri = $notice->metadata()->toArray()['mcp/uri'] ?? null;
            if (!is_string($uri) || !in_array($uri, $accepted[$filter], true)) {
                return null;
            }
        }

        if (!$this->admission->allows($subscriber, $filter, $uri, $notice)) {
            return null;
        }

        $params = ['_meta' => [self::META => $this->requestId]];
        if ($uri !== null) {
            $params['uri'] = $uri;
        }

        return ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params];
    }

    /**
     * Creates one bounded actual JSON and SSE frame including escaped correlation metadata
     *
     * @param array<string, mixed> $message
     */
    private function frame(array $message): string
    {
        $frame = 'data: '.json_encode($message, JSON_THROW_ON_ERROR)."\n\n";
        if (strlen($frame) > $this->maxFrameBytes) {
            throw new RuntimeException('Live MCP frame exceeds bounds.');
        }

        return $frame;
    }

    /**
     * Records private failures only through the consumer redaction-aware sink
     */
    private function diagnose(McpDiagnostics $diagnostics, Throwable $failure): void
    {
        try {
            $diagnostics->record($failure);
        } catch (Throwable) {
            // Diagnostics cannot become wire output or prevent owned closure.
        }
    }
}
