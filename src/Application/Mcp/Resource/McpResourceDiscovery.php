<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Domain\Exception\DomainException;
use Generator;

/**
 * Class McpResourceDiscovery
 */
final readonly class McpResourceDiscovery implements McpCapability
{
    /**
     * @var list<McpResourceProvider>
     */
    private array $providers;

    /**
     * Constructs McpResourceDiscovery
     *
     * Supply a stable secret shared across serving processes and a distinct catalog scope per endpoint.
     * Neither is a credential or principal. Public caching is an explicit consumer assertion that the complete
     * catalog is caller-independent. Providers are inspected only when a guarded request dispatches discovery.
     *
     * @param array<array-key, mixed> $providers
     */
    public function __construct(
        array $providers,
        private McpResourceAvailability $availability,
        private string $cursorKey,
        private string $cursorScope,
        private McpResourceLimits $limits = new McpResourceLimits(),
        private int $ttlMs = 0,
        private string $cacheScope = 'private'
    ) {
        if (strlen($cursorKey) < 32 || strlen($cursorKey) > 4096 || $cursorScope === '' || strlen($cursorScope) > 128) {
            throw new DomainException('Resource cursors require a bounded secret and a non-empty catalog scope.');
        }

        if ($ttlMs < 0 || $ttlMs > 9007199254740991 || !in_array($cacheScope, ['private', 'public'], true)) {
            throw new DomainException('Resource cache hints require a safe integer TTL and private/public scope.');
        }

        if (!array_is_list($providers) || count($providers) > $limits->maxProviders) {
            throw new DomainException('Resource discovery requires a bounded list of explicit providers.');
        }

        $seen = [];
        $registered = [];
        foreach ($providers as $provider) {
            if (!$provider instanceof McpResourceProvider || isset($seen[spl_object_id($provider)])) {
                throw new DomainException('Resource providers must be distinct McpResourceProvider instances.');
            }

            $seen[spl_object_id($provider)] = true;
            $registered[] = $provider;
        }

        $this->providers = $registered;
    }

    /**
     * @inheritDoc
     */
    public function methods(): array
    {
        return ['resources/list', 'resources/templates/list'];
    }

    /**
     * @inheritDoc
     */
    public function capabilities(): array
    {
        return ['resources' => ['subscribe' => false, 'listChanged' => false]];
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
            !in_array($request->method(), $this->methods(), true)
            || array_diff(array_keys($parameters), ['cursor']) !== []
            || (array_key_exists('cursor', $parameters)
                && (!is_string($parameters['cursor'])
                    || ($parameters['cursor'] !== ''
                        && ($request->method() === 'resources/templates/list'
                            || preg_match('/\A[A-Za-z0-9_-]{48}\z/D', $parameters['cursor']) !== 1))))
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
        if ($request->method() === 'resources/templates/list') {
            return McpResult::boundedComplete(
                ['resourceTemplates' => [], 'ttlMs' => $this->ttlMs, 'cacheScope' => $this->cacheScope],
                $this->limits->maxResultBytes
            );
        }

        $cursor = $request->parameters()['cursor'] ?? '';
        $raw = $cursor === '' ? '' : base64_decode(strtr($cursor, '-_', '+/'), true);
        $offset = $raw === '' ? 0 : unpack('Noffset', substr($raw, 0, 4))['offset'];
        if ($raw !== '' && ($offset < 1 || $offset % $this->limits->pageSize !== 0)) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        $hash = hash_init('sha256');
        $visible = 0;
        $page = [];
        $pageBytes = 0;
        try {
            foreach ($this->resources() as $info) {
                if (!$this->availability->isAvailable($info)) {
                    continue;
                }

                $definition = $info->toArray();
                $encoded = json_encode($definition, JSON_THROW_ON_ERROR);
                // Length framing prevents ambiguous boundaries without retaining the entire catalog.
                hash_update($hash, strlen($encoded).':'.$encoded);
                if ($visible >= $offset && count($page) < $this->limits->pageSize) {
                    $pageBytes += strlen($encoded) + 1;
                    if ($pageBytes > $this->limits->maxResultBytes) {
                        throw new DomainException('Resource discovery exceeds its encoded result budget.');
                    }

                    $page[] = $definition;
                }

                ++$visible;
            }
        } catch (McpProtocolException $mcpProtocolException) {
            // Providers and policy are not protocol-error authorities; their failures remain internal.
            throw new DomainException('Resource enumeration failed.', previous: $mcpProtocolException);
        }

        $snapshot = hash_final($hash);
        if (
            $raw !== ''
            && ($offset >= $visible || !hash_equals($this->signature($offset, $snapshot), substr($raw, 4)))
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        $result = ['resources' => $page, 'ttlMs' => $this->ttlMs, 'cacheScope' => $this->cacheScope];
        $next = $offset + count($page);
        if ($next < $visible) {
            $result['nextCursor'] = strtr(
                base64_encode(pack('N', $next).$this->signature($next, $snapshot)),
                '+/',
                '-_'
            );
        }

        return McpResult::boundedComplete($result, $this->limits->maxResultBytes);
    }

    /**
     * Returns a globally ordered stream while rejecting ambiguous ownership before any result is released
     *
     * Memory retains at most one descriptor per provider plus the requested page. All providers are exhausted
     * within the scan budget, so an invalid or duplicate descriptor beyond the page cannot hide in a success.
     *
     * @return Generator<int, McpResourceInfo>
     */
    private function resources(): Generator
    {
        $streams = array_map($this->providerResources(...), $this->providers);
        $previous = null;
        $count = 0;
        while ($streams !== []) {
            $selected = null;
            $info = null;
            foreach ($streams as $index => $stream) {
                if (!$stream->valid()) {
                    unset($streams[$index]);

                    continue;
                }

                if ($info === null || strcmp($stream->current()->uri(), $info->uri()) < 0) {
                    $selected = $index;
                    $info = $stream->current();
                }
            }

            if ($info === null) {
                break;
            }

            if ($info->uri() === $previous || ++$count > $this->limits->maxDescriptors) {
                throw new DomainException('Resource URI ownership is ambiguous or enumeration exceeds its budget.');
            }

            $previous = $info->uri();
            yield $info;
            $streams[$selected]->next();
        }
    }

    /**
     * Returns one provider's validated ordered metadata without materializing its corpus
     *
     * @return Generator<int, McpResourceInfo>
     */
    private function providerResources(McpResourceProvider $provider): Generator
    {
        $previous = null;
        /**
         * @var iterable<mixed> $resources
         */
        $resources = $provider->resources();
        foreach ($resources as $info) {
            if (!$info instanceof McpResourceInfo || ($previous !== null && strcmp($previous, $info->uri()) >= 0)) {
                throw new DomainException('Resource providers must yield unique descriptors in bytewise URI order.');
            }

            $info->validateLimits($this->limits);
            $previous = $info->uri();
            yield $info;
        }
    }

    /**
     * Creates a purpose-bound authenticator containing no concealed Resource metadata
     */
    private function signature(int $offset, string $snapshot): string
    {
        return hash_hmac(
            'sha256',
            implode(':', [
                'mcp-resources-v1', strlen($this->cursorScope), $this->cursorScope,
                $this->limits->pageSize, $offset, $snapshot
            ]),
            $this->cursorKey,
            true
        );
    }
}
