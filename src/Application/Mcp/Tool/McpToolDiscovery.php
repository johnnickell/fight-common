<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpToolDiscovery
 */
final readonly class McpToolDiscovery implements McpCapability
{
    /**
     * Constructs McpToolDiscovery
     *
     * The consumer supplies a stable secret shared across serving processes, not a principal or access token.
     * A public cache override asserts the consumer's catalog is safe to share across authorization contexts.
     */
    public function __construct(
        private McpToolRegistry $registry,
        private McpToolAvailability $availability,
        private string $cursorKey,
        private int $pageSize = 100,
        private int $ttlMs = 0,
        private string $cacheScope = 'private'
    ) {
        if (strlen($cursorKey) < 32) {
            throw new DomainException('Tool pagination requires a consumer-supplied secret of at least 32 bytes.');
        }

        if ($pageSize < 1 || $pageSize > 1000) {
            throw new DomainException('Tool discovery page size must be between 1 and 1000.');
        }

        if ($ttlMs < 0 || $ttlMs > 9007199254740991 || !in_array($cacheScope, ['private', 'public'], true)) {
            throw new DomainException(
                'Tool cache hints require a non-negative safe integer TTL and private/public scope.'
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function methods(): array
    {
        return ['tools/list'];
    }

    /**
     * @inheritDoc
     */
    public function capabilities(): array
    {
        return ['tools' => ['listChanged' => false]];
    }

    /**
     * @inheritDoc
     */
    public function mirrorDeclarations(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function validate(McpRequest $request): void
    {
        $parameters = $request->parameters();
        if (
            $request->method() !== 'tools/list'
            || array_diff(array_keys($parameters), ['cursor']) !== []
            || (array_key_exists('cursor', $parameters)
                && (!is_string($parameters['cursor'])
                    || preg_match('/\A[A-Za-z0-9_-]{48}\z/D', $parameters['cursor']) !== 1))
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }
    }

    /**
     * @inheritDoc
     */
    public function handle(McpRequest $request): McpResult
    {
        $this->validate($request);
        $definitions = array_map(
            fn(McpToolInfo $info): array => $info->toArray(),
            $this->registry->available($this->availability)
        );
        // Metadata already bounds each schema; catalog wrappers must not consume that schema depth budget.
        $snapshot = hash('sha256', json_encode($definitions, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $offset = $this->offset($request, $snapshot, count($definitions));
        $result = [
            'tools'      => array_slice($definitions, $offset, $this->pageSize),
            'ttlMs'      => $this->ttlMs,
            'cacheScope' => $this->cacheScope
        ];
        $next = $offset + $this->pageSize;
        if ($next < count($definitions)) {
            $result['nextCursor'] = strtr(
                base64_encode(pack('N', $next).$this->signature($next, $snapshot)),
                '+/',
                '-_'
            );
        }

        return McpResult::complete($result);
    }

    /**
     * Returns a continuation offset bound only to the current available catalog
     */
    private function offset(McpRequest $request, string $snapshot, int $count): int
    {
        $cursor = $request->parameters()['cursor'] ?? null;
        if ($cursor === null) {
            return 0;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $offset = unpack('Noffset', substr($decoded, 0, 4))['offset'];
        if (
            $offset < 1
            || $offset >= $count
            || $offset % $this->pageSize !== 0
            || !hash_equals($this->signature($offset, $snapshot), substr($decoded, 4))
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        return $offset;
    }

    /**
     * Creates a purpose-bound authenticator without encoding concealed registry data
     */
    private function signature(int $offset, string $snapshot): string
    {
        return hash_hmac('sha256', 'mcp-tools-v1:'.$this->pageSize.':'.$offset.':'.$snapshot, $this->cursorKey, true);
    }
}
