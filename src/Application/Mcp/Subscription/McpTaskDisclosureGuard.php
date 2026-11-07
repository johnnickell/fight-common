<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription;

use Closure;

/**
 * Interface McpTaskDisclosureGuard
 *
 * Consumer-owned synchronized admission for one complete protected Task disclosure, not a permission lease.
 */
interface McpTaskDisclosureGuard
{
    /**
     * Executes one bounded disclosure synchronously under current complete consumer authority
     *
     * States map exact Task IDs to the complete proposed DetailedTask projections, including protected fields and
     * revision metadata. Revalidate independent signal, Task, whole-state and every field grant for all supplied
     * states, original identity/credential and current relationship scope. Coordinate all relevant authority writers
     * from those checks through the action, including reentrant writers; unsupported synchronization must throw
     * before invoking it. Never infer a grant from the projections, subscriber, earlier Query or acknowledgment.
     * Invoke exactly once on the calling Fiber, propagate faults and release protection in finally. Do not save,
     * retry, defer or yield the action. Empty states disclose no Task identity. No auth types or policy live here.
     * The action includes Common's final technical readiness and bounded byte handoff, not stream lifetime/waits.
     * Qualify finite lock/I/O timeouts and writer participation; bytes handed off cannot be retracted. A failure
     * after action invocation proves neither absence of output nor rollback. See docs/mcp-task-live.md.
     *
     * @phpstan-param array<string, array<string, mixed>> $states
     * @phpstan-param Closure(): void $disclose
     */
    public function run(string $subscriber, array $states, Closure $disclose): void;
}
