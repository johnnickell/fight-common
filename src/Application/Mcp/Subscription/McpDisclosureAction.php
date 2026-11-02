<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription;

use Closure;
use Fiber;
use RuntimeException;

/**
 * Class McpDisclosureAction
 *
 * @internal Enforces synchronous single invocation without granting or implementing consumer authority
 */
final class McpDisclosureAction
{
    private bool $active = true;
    private bool $invoked = false;
    /**
     * @var Fiber<mixed, mixed, mixed, mixed>|null
     */
    private readonly ?Fiber $fiber;

    /**
     * Constructs McpDisclosureAction
     *
     * @phpstan-param Closure(): void $action
     */
    public function __construct(private readonly Closure $action)
    {
        $this->fiber = Fiber::getCurrent();
    }

    /**
     * Executes only the first synchronous action on its original calling Fiber
     */
    public function invoke(): void
    {
        if (!$this->active || $this->invoked || Fiber::getCurrent() !== $this->fiber) {
            throw new RuntimeException('Invalid Task disclosure invocation.');
        }

        $this->invoked = true;
        ($this->action)();
    }

    /**
     * Sets saved callbacks inactive on every exit and rejects omitted invocation
     */
    public function finish(): void
    {
        $this->active = false;
        if (!$this->invoked) {
            throw new RuntimeException('Task disclosure was not invoked.');
        }
    }
}
