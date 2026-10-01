<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

use Fight\Common\Application\Mcp\McpMetadataAwareCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Domain\Exception\DomainException;
use Generator;
use Uri\Rfc3986\Uri;

/**
 * Class McpResourceDiscovery
 */
final readonly class McpResourceDiscovery implements McpMetadataAwareCapability
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
     * catalog and any readable content are caller-independent. Providers are inspected only after dispatch.
     * Supplying readLimits opts in to exact reads and requires the readable subtype on every provider;
     * omitting it preserves the original discovery-only composition and provider contract.
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
        private string $cacheScope = 'private',
        private ?McpResourceReadLimits $readLimits = null
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

            if ($readLimits !== null && !$provider instanceof McpReadableResourceProvider) {
                throw new DomainException('Resource reads require every provider to support exact lookup and opening.');
            }

            $seen[spl_object_id($provider)] = true;
            $registered[] = $provider;
        }

        $this->providers = $registered;
    }

    /**
     * Returns whether this read-enabled capability serves the exact provider instance
     *
     * @internal
     */
    public function serves(McpReadableResourceProvider $provider): bool
    {
        return $this->readLimits !== null && in_array($provider, $this->providers, true);
    }

    /**
     * Returns the current general Resource decision for an already validated descriptor
     *
     * Skills combines this with its provider-owned whole-Skill decision before disclosing a complete manifest.
     *
     * @internal
     */
    public function permits(McpResourceInfo $resource): bool
    {
        return $this->availability->isAvailable($resource);
    }

    /**
     * Validates complete catalog ownership and serving budgets without opening content or caching authority
     *
     * Skills uses this before advertising complete entries, including files absent from the current Resource page.
     *
     * @phpstan-param array<string, mixed> $metadata
     *
     * @internal
     */
    public function validateCatalog(array $metadata): void
    {
        foreach ($this->resources($metadata) as $resource) {
            // Exhaust validation, including concealed descriptors and collisions beyond the current page.
        }
    }

    /**
     * @inheritDoc
     */
    public function methods(): array
    {
        $methods = ['resources/list', 'resources/templates/list'];
        if ($this->readLimits !== null) {
            $methods[] = 'resources/read';
        }

        return $methods;
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
        if ($request->method() === 'resources/read' && $this->readLimits !== null) {
            if (
                array_diff(array_keys($parameters), ['uri']) !== []
                || !is_string($parameters['uri'] ?? null)
                || strlen($parameters['uri']) > $this->limits->maxUriBytes
                || Uri::parse($parameters['uri'])?->getScheme() === null
            ) {
                throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
            }

            return;
        }

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
        return $this->handleWithMetadata($request, []);
    }

    /**
     * Handles a request with the responder's actual central metadata for read-budget validation
     *
     * Metadata is request-local so sharing this capability between responders cannot retain another identity.
     *
     * @phpstan-param array<string, mixed> $metadata
     *
     * @inheritDoc
     */
    public function handleWithMetadata(McpRequest $request, array $metadata): McpResult
    {
        $this->validate($request);
        if ($request->method() === 'resources/read' && $this->readLimits !== null) {
            return $this->read($request, $this->readLimits, $metadata);
        }

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
            foreach ($this->resources($metadata) as [$provider, $info]) {
                if (
                    ($provider instanceof McpProtectedResourceProvider && !$provider->isAvailable($info))
                    || !$this->availability->isAvailable($info)
                ) {
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
     * @param array<string, mixed> $metadata
     *
     * @return Generator<int, array{McpResourceProvider, McpResourceInfo}>
     */
    private function resources(array $metadata): Generator
    {
        $streams = array_map(
            fn($provider): Generator => $this->providerResources($provider, $metadata),
            $this->providers
        );
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
            yield [$this->providers[$selected], $info];
            $streams[$selected]->next();
        }
    }

    /**
     * Returns one provider's validated ordered metadata without materializing its corpus
     *
     * @phpstan-param array<string, mixed> $metadata
     *
     * @return Generator<int, McpResourceInfo>
     */
    private function providerResources(McpResourceProvider $provider, array $metadata): Generator
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
            if ($this->readLimits !== null) {
                $this->validateReadBudget($info, $this->readLimits, $metadata);
            }

            $previous = $info->uri();
            yield $info;
        }
    }

    /**
     * Returns exact authorized content without using listing membership or cached authority
     *
     * @phpstan-param array<string, mixed> $metadata
     */
    private function read(McpRequest $request, McpResourceReadLimits $limits, array $metadata): McpResult
    {
        $selected = null;
        $resource = null;
        try {
            foreach ($this->providers as $provider) {
                // Construction guarantees the subtype when reads are enabled; old discovery ports remain valid.
                if ($provider instanceof McpReadableResourceProvider) {
                    $found = $provider->find($request->parameters()['uri']);
                    if ($found === null) {
                        continue;
                    }

                    $found->validateLimits($this->limits);
                    $this->validateReadBudget($found, $limits, $metadata);
                    if ($resource !== null || $found->uri() !== $request->parameters()['uri']) {
                        throw new DomainException('Resource lookup must have exactly one matching URI owner.');
                    }

                    $selected = $provider;
                    $resource = $found;
                }
            }

            if (
                $resource !== null
                && (!$selected instanceof McpProtectedResourceProvider || $selected->isAvailable($resource))
                && $this->availability->isAvailable($resource)
            ) {
                $content = $selected->open($resource)->consume($resource, $limits);

                return McpResult::boundedComplete(
                    ['contents' => [$content], 'ttlMs' => $this->ttlMs, 'cacheScope' => $this->cacheScope],
                    $limits->maxResultBytes
                );
            }
        } catch (McpProtocolException $mcpProtocolException) {
            throw new DomainException('Resource reading failed.', previous: $mcpProtocolException);
        }

        throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
    }

    /**
     * Validates the complete worst-case read representation before advertisement or content opening
     *
     * Empty text supplies the exact wrapper size; six encoded bytes per raw byte also bounds Base64 blobs.
     * The actual final result remains independently bounded by McpResult after content and metadata are added.
     *
     * @phpstan-param array<string, mixed> $metadata
     */
    private function validateReadBudget(
        McpResourceInfo $resource,
        McpResourceReadLimits $limits,
        array $metadata
    ): void {
        $limits->validate($resource);
        $content = array_intersect_key($resource->toArray(), array_flip(['uri', 'mimeType']));
        $result = McpResult::boundedComplete(
            ['contents' => [[...$content, 'text' => '']], 'ttlMs' => $this->ttlMs, 'cacheScope' => $this->cacheScope],
            $limits->maxResultBytes
        )->withMetadata($metadata);
        $encodedBound = strlen(json_encode($result->toArray(), JSON_THROW_ON_ERROR)) + 6 * $limits->maxContentBytes;
        if ($encodedBound > $limits->maxResultBytes) {
            throw new DomainException('Resource content cannot fit its complete encoded result budget.');
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
