<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\Capability\McpCapability;
use Fight\Common\Application\Mcp\Http\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\Protocol\Exception\McpProtocolException;
use Fight\Common\Application\Mcp\Protocol\McpProtocolError;
use Fight\Common\Application\Mcp\Protocol\McpRequest;
use Fight\Common\Application\Mcp\Protocol\McpResult;

final class FixtureCapability implements McpCapability
{
    public int $validateCalls = 0;
    public int $handleCalls = 0;

    /**
     * @param array<string, mixed>           $definition
     * @param list<McpMirrorDeclaration>     $mirrors
     */
    public function __construct(
        private readonly string $method = 'example/echo',
        private readonly array $definition = ['enabled' => true],
        private readonly array $mirrors = [],
        private readonly bool $throwOnHandle = false,
        private readonly ?array $methods = null,
        private readonly ?array $capabilities = null,
        private readonly bool $throwProtocolException = false,
        private readonly int|string|null $exceptionRequestId = null,
        private readonly ?McpResult $result = null,
    ) {
    }

    public function methods(): array
    {
        return $this->methods ?? [$this->method];
    }

    public function capabilities(): array
    {
        return $this->capabilities ?? ['example' => $this->definition];
    }

    public function mirrorDeclarations(): array
    {
        return $this->mirrors;
    }

    public function validate(McpRequest $request): void
    {
        ++$this->validateCalls;
        if ($this->throwProtocolException) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $this->exceptionRequestId);
        }

        if (!isset($request->parameters()['message']) || !is_string($request->parameters()['message'])) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }
    }

    public function handle(McpRequest $request): McpResult
    {
        ++$this->handleCalls;
        if ($this->throwOnHandle) {
            throw new \RuntimeException('Unexpected fixture failure.');
        }

        return $this->result ?? McpResult::complete(['message' => $request->parameters()['message']]);
    }
}
