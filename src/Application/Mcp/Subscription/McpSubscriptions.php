<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription;

use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\NoticeSelection;

/**
 * Class McpSubscriptions
 */
final readonly class McpSubscriptions
{
    /**
     * Constructs McpSubscriptions
     *
     * Selection and admission are trusted request-scoped composition, never request arguments
     */
    public function __construct(
        private QueryBus $queries,
        private McpSubscriptionStore $store,
        private McpSubscriptionAdmission $admission,
        private NoticeSelection $selection,
        private bool $tools = true,
        private bool $resources = true,
        private int $lifetimeSeconds = 30,
        private int $waitMicroseconds = 200000,
        private int $maxFrameBytes = 16384
    ) {
        if (
            (!$tools && !$resources) || $lifetimeSeconds < 1 || $lifetimeSeconds > 120
            || $waitMicroseconds < 10000 || $waitMicroseconds > 1000000
            || $maxFrameBytes < 256 || $maxFrameBytes > 65536
        ) {
            throw new DomainException('Invalid MCP subscription limits.');
        }
    }

    /**
     * Returns only actually implemented notification families
     *
     * @return list<'toolsListChanged'|'resourcesListChanged'|'resourceSubscriptions'>
     */
    public function supported(): array
    {
        return [
            ...($this->tools ? ['toolsListChanged'] : []),
            ...($this->resources ? ['resourcesListChanged', 'resourceSubscriptions'] : [])
        ];
    }

    /**
     * Creates one independent lazy execution after structural validation
     */
    public function execution(McpRequest $request): McpSubscriptionExecution
    {
        return new McpSubscriptionExecution(
            $request->id(),
            McpSubscriptionFilter::fromRequest($request),
            $this->supported(),
            $this->queries,
            $this->store,
            $this->admission,
            $this->selection,
            $this->lifetimeSeconds,
            $this->waitMicroseconds,
            $this->maxFrameBytes
        );
    }
}
