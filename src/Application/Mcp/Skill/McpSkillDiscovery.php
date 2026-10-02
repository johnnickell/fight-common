<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Skill;

use Fight\Common\Application\Mcp\McpMetadataAwareCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Uri\Rfc3986\Uri;

/**
 * Class McpSkillDiscovery
 */
final readonly class McpSkillDiscovery implements McpMetadataAwareCapability
{
    public const string EXTENSION = 'io.modelcontextprotocol/skills';

    /**
     * Constructs McpSkillDiscovery
     *
     * Register this capability and the same read-enabled Resources capability together. Construction validates
     * supplied immutable entries; dynamic Resource catalogs are checked on Skills dispatch, not construction.
     * The provider owns immutable entries and the whole-Skill decision used by file reads. Public cache scope
     * asserts caller-independent visibility; neither cursor scope nor key is a principal or access credential.
     */
    public function __construct(
        private McpSkillResources $provider,
        private McpResourceDiscovery $resources,
        private string $cursorKey,
        private string $cursorScope,
        private McpSkillDiscoveryLimits $limits = new McpSkillDiscoveryLimits(),
        private int $ttlMs = 0,
        private string $cacheScope = 'private'
    ) {
        if (!$resources->serves($provider)) {
            throw new DomainException(
                'Skills requires its exact provider in the shared readable Resources capability.'
            );
        }

        if (strlen($cursorKey) < 32 || strlen($cursorKey) > 4096 || $cursorScope === '' || strlen($cursorScope) > 128) {
            throw new DomainException('Skill cursors require a bounded secret and non-empty catalog scope.');
        }

        if ($ttlMs < 0 || $ttlMs > 9007199254740991 || !in_array($cacheScope, ['private', 'public'], true)) {
            throw new DomainException('Skill cache hints require a safe integer TTL and private/public scope.');
        }

        if (count($provider->entries()) > $limits->maxEntries) {
            throw new DomainException('Skill discovery exceeds its complete catalog budget.');
        }

        $this->validateEntries([]);
    }

    /**
     * Returns the required shared Resources capability for registry identity validation
     *
     * @internal
     */
    public function resourceDiscovery(): McpResourceDiscovery
    {
        return $this->resources;
    }

    /**
     * @inheritDoc
     */
    public function methods(): array
    {
        return ['skills/list', 'skills/get'];
    }

    /**
     * @inheritDoc
     */
    public function capabilities(): array
    {
        return ['extensions' => [self::EXTENSION => StrictJson::fromObject([])]];
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
        if ($request->method() === 'skills/get') {
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
            $request->method() !== 'skills/list'
            || array_diff(array_keys($parameters), ['cursor']) !== []
            || (array_key_exists('cursor', $parameters)
                && (!is_string($parameters['cursor'])
                    || ($parameters['cursor'] !== ''
                        && preg_match('/\A[A-Za-z0-9_-]{48}\z/D', $parameters['cursor']) !== 1)))
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
     * Handles complete entries using the actual responder metadata for atomic serving budgets
     *
     * @phpstan-param array<string, mixed> $metadata
     *
     * @inheritDoc
     */
    public function handleWithMetadata(McpRequest $request, array $metadata): McpResult
    {
        $this->validate($request);
        try {
            $this->validateCatalog($metadata);
        } catch (McpProtocolException $mcpProtocolException) {
            // Consumer providers/policies cannot choose public protocol errors or leak diagnostic data.
            throw new DomainException('Skill catalog processing failed.', previous: $mcpProtocolException);
        }

        if ($request->method() === 'skills/list') {
            return $this->listing($request, $metadata);
        }

        $entry = $this->provider->findEntry($request->parameters()['uri']);
        if ($entry !== null && $this->available($entry)) {
            return $this->result(['skill' => $entry]);
        }

        throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
    }

    /**
     * Validates all entries and shared file budgets before any advertisement, including concealed revisions
     *
     * @phpstan-param array<string, mixed> $metadata
     */
    private function validateCatalog(array $metadata): void
    {
        $this->resources->validateCatalog($metadata);
        $this->validateEntries($metadata);
    }

    /**
     * Validates locally supplied immutable entries without querying dynamic Resource providers
     *
     * @phpstan-param array<string, mixed> $metadata
     */
    private function validateEntries(array $metadata): void
    {
        foreach ($this->provider->entries() as $uri => $entry) {
            if (
                strlen($uri) > $this->limits->maxUriBytes
                || strlen($entry->toString()) > $this->limits->maxEntryBytes
            ) {
                throw new DomainException('Skill entry exceeds its URI or complete metadata budget.');
            }

            $this->result(['skill' => $entry])->withMetadata($metadata);
            $this->result(['skills' => [$entry], 'nextCursor' => str_repeat('x', 48)])->withMetadata($metadata);
        }
    }

    /**
     * Returns the provider's current whole-Skill decision without retaining authority between requests
     */
    private function available(StrictJson $entry): bool
    {
        try {
            if (!$this->provider->isAvailable($this->provider->find($entry->get('uri')))) {
                return false;
            }

            // An entry cannot redact a member that the general Resource policy conceals.
            return array_all(
                $entry->get('resources'),
                fn(StrictJson $member): bool => $this->resources->permits($this->provider->find($member->get('uri')))
            );
        } catch (McpProtocolException $mcpProtocolException) {
            throw new DomainException('Skill availability failed.', previous: $mcpProtocolException);
        }
    }

    /**
     * Returns one atomic page while binding continuation only to the current visible catalog
     *
     * @phpstan-param array<string, mixed> $metadata
     */
    private function listing(McpRequest $request, array $metadata): McpResult
    {
        $cursor = $request->parameters()['cursor'] ?? '';
        $raw = $cursor === '' ? '' : base64_decode(strtr($cursor, '-_', '+/'), true);
        $offset = $raw === '' ? 0 : unpack('Noffset', substr($raw, 0, 4))['offset'];
        $hash = hash_init('sha256');
        $visible = 0;
        $page = [];
        $pageBytes = strlen(json_encode(
            $this->result(['skills' => [], 'nextCursor' => str_repeat('x', 48)])->withMetadata($metadata)->toArray(),
            JSON_THROW_ON_ERROR
        ));
        $full = false;
        foreach ($this->provider->entries() as $entry) {
            if (!$this->available($entry)) {
                continue;
            }

            $encoded = $entry->toString();
            hash_update($hash, strlen($encoded).':'.$encoded);
            if ($visible >= $offset && !$full) {
                // Match McpResult's encoder, not StrictJson's fraction-preserving catalog fingerprint.
                // Catalog validation guarantees one entry plus this metadata/cursor wrapper always fits.
                $bytes = strlen(json_encode($entry, JSON_THROW_ON_ERROR)) + (count($page) === 0 ? 0 : 1);
                if (count($page) >= $this->limits->pageSize || $pageBytes + $bytes > $this->limits->maxResultBytes) {
                    $full = true;
                } else {
                    $page[] = $entry;
                    $pageBytes += $bytes;
                }
            }

            ++$visible;
        }

        $snapshot = hash_final($hash);
        if (
            $raw !== ''
            && ($offset < 1 || $offset >= $visible
                || !hash_equals($this->signature($offset, $snapshot), substr($raw, 4)))
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        $result = ['skills' => $page];
        $next = $offset + count($page);
        if ($next < $visible) {
            $result['nextCursor'] = strtr(
                base64_encode(pack('N', $next).$this->signature($next, $snapshot)),
                '+/',
                '-_'
            );
        }

        return $this->result($result);
    }

    /**
     * Creates a complete independently bounded result with explicit cache semantics
     *
     * @phpstan-param array<string, mixed> $data
     */
    private function result(array $data): McpResult
    {
        return McpResult::boundedComplete(
            [...$data, 'ttlMs' => $this->ttlMs, 'cacheScope' => $this->cacheScope],
            $this->limits->maxResultBytes
        );
    }

    /**
     * Creates a purpose-bound authenticator containing no concealed metadata or authority
     */
    private function signature(int $offset, string $snapshot): string
    {
        return hash_hmac('sha256', implode(':', [
            'mcp-skills-v1', strlen($this->cursorScope), $this->cursorScope,
            $this->limits->pageSize, $this->limits->maxResultBytes, $offset, $snapshot
        ]), $this->cursorKey, true);
    }
}
