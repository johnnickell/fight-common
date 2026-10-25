<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Prompt;

use Fight\Common\Application\Mcp\McpMetadataAwareCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpPromptDiscovery
 */
final readonly class McpPromptDiscovery implements McpMetadataAwareCapability
{
    /**
     * @var array<string, array{McpPromptInfo, McpPrompt}>
     */
    private array $prompts;

    /**
     * Constructs McpPromptDiscovery
     *
     * Registration snapshots metadata once, never content. Supply a stable dedicated secret shared across
     * serving processes and a distinct endpoint catalog scope. Neither is a principal or credential.
     *
     * @param array<array-key, mixed> $prompts
     */
    public function __construct(
        array $prompts,
        private McpPromptAvailability $availability,
        private string $cursorKey,
        private string $cursorScope,
        private McpPromptLimits $limits = new McpPromptLimits()
    ) {
        if (strlen($cursorKey) < 32 || strlen($cursorKey) > 4096 || $cursorScope === '' || strlen($cursorScope) > 128) {
            throw new DomainException('Prompt cursors require a bounded secret and non-empty catalog scope.');
        }

        if (!array_is_list($prompts) || count($prompts) > $limits->maxPrompts) {
            throw new DomainException('Prompts require a bounded list of explicit providers.');
        }

        $registered = [];
        $bytes = 0;
        foreach ($prompts as $prompt) {
            if (!$prompt instanceof McpPrompt) {
                throw new DomainException('Prompt providers must implement McpPrompt.');
            }

            $info = $prompt->info();
            $bytes += strlen(json_encode($info->toArray(), JSON_THROW_ON_ERROR));
            if (isset($registered[$info->name()]) || $bytes > $limits->maxCatalogBytes) {
                throw new DomainException('Prompt ownership is ambiguous or catalog exceeds its byte budget.');
            }

            $registered[$info->name()] = [$info, $prompt];
        }

        ksort($registered, SORT_STRING);
        $this->prompts = $registered;
    }

    /**
     * @inheritDoc
     */
    public function methods(): array
    {
        return ['prompts/list', 'prompts/get'];
    }

    /**
     * @inheritDoc
     */
    public function capabilities(): array
    {
        return ['prompts' => ['listChanged' => false]];
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
        if ($request->method() === 'prompts/get') {
            if (
                array_diff(array_keys($parameters), ['name', 'arguments']) !== []
                || !is_string($parameters['name'] ?? null) || strlen($parameters['name']) > 128
                || (array_key_exists('arguments', $parameters)
                    && (!$parameters['arguments'] instanceof StrictJson || !$parameters['arguments']->isObject()))
            ) {
                throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
            }

            return;
        }

        if (
            $request->method() !== 'prompts/list'
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
     * @inheritDoc
     */
    public function handleWithMetadata(McpRequest $request, array $metadata): McpResult
    {
        $this->validate($request);
        if ($request->method() === 'prompts/get') {
            return $this->get($request, $metadata);
        }

        $definitions = [];
        foreach ($this->prompts as [$info]) {
            if ($this->permits($info)) {
                $definitions[] = $info->toArray();
            }
        }

        $snapshot = hash('sha256', json_encode($definitions, JSON_THROW_ON_ERROR));
        $offset = $this->offset($request, $snapshot, count($definitions));
        $result = [
            'prompts'    => array_slice($definitions, $offset, $this->limits->pageSize),
            'ttlMs'      => 0,
            'cacheScope' => 'private'
        ];
        $next = $offset + $this->limits->pageSize;
        if ($next < count($definitions)) {
            $result['nextCursor'] = strtr(
                base64_encode(pack('N', $next).$this->signature($next, $snapshot)),
                '+/',
                '-_'
            );
        }

        return $this->result($result, $metadata);
    }

    /**
     * Returns complete content only after a fresh decision and declared argument validation
     *
     * @phpstan-param array<string, mixed> $metadata
     */
    private function get(McpRequest $request, array $metadata): McpResult
    {
        $parameters = $request->parameters();
        $selected = $this->prompts[$parameters['name']] ?? null;
        if ($selected === null || !$this->permits($selected[0])) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        [$info, $provider] = $selected;
        $arguments = $parameters['arguments'] ?? StrictJson::fromObject([]);
        if (!$info->accepts($arguments)) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        try {
            $output = $provider->get($arguments->properties());
        } catch (McpProtocolException $mcpProtocolException) {
            // Consumer code cannot turn private provider diagnostics into public protocol errors.
            throw new DomainException('Prompt content provider failed.', previous: $mcpProtocolException);
        }

        return $this->result([...$output->toArray(), 'ttlMs' => 0, 'cacheScope' => 'private'], $metadata);
    }

    /**
     * Returns a current decision while reserving protocol error authority to Common
     */
    private function permits(McpPromptInfo $info): bool
    {
        try {
            return $this->availability->isAvailable($info);
        } catch (McpProtocolException $mcpProtocolException) {
            throw new DomainException('Prompt availability failed.', previous: $mcpProtocolException);
        }
    }

    /**
     * Returns one atomic bounded result retaining the complete encoded-response guard
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     */
    private function result(array $data, array $metadata): McpResult
    {
        return McpResult::boundedComplete($data, $this->limits->maxResultBytes)
            ->withMetadata($metadata)
            ->withResponseLimit($this->limits->maxResponseBytes);
    }

    /**
     * Returns an authenticated offset bound only to the visible catalog and serving parameters
     */
    private function offset(McpRequest $request, string $snapshot, int $count): int
    {
        $cursor = $request->parameters()['cursor'] ?? '';
        if ($cursor === '') {
            return 0;
        }

        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        $offset = unpack('Noffset', substr($raw, 0, 4))['offset'];
        if (
            $offset < 1 || $offset >= $count || $offset % $this->limits->pageSize !== 0
            || !hash_equals($this->signature($offset, $snapshot), substr($raw, 4))
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        return $offset;
    }

    /**
     * Creates a purpose-bound authenticator without encoding concealed metadata or authority
     */
    private function signature(int $offset, string $snapshot): string
    {
        return hash_hmac(
            'sha256',
            'mcp-prompts-v1:'.hash('sha256', $this->cursorScope).':'.json_encode([
                $this->limits->pageSize, $this->limits->maxResultBytes,
                $this->limits->maxResponseBytes, $offset, $snapshot
            ], JSON_THROW_ON_ERROR),
            $this->cursorKey,
            true
        );
    }
}
