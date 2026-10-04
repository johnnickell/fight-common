<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Throwable;

final class EndpointCapability implements McpCapability
{
    public int $validateCalls = 0;
    public int $handleCalls = 0;
    public ?Throwable $validationFailure = null;
    public ?Throwable $handlingFailure = null;
    public array $result = ['message' => 'accepted'];

    public function __construct(private array $mirrors = [])
    {
    }

    public function methods(): array
    {
        return ['example/echo', 'tools/list', 'tools/call', 'resources/list', 'resources/read', 'prompts/list', 'prompts/get'];
    }

    public function capabilities(): array
    {
        return ['example' => [], 'tools' => [], 'resources' => [], 'prompts' => []];
    }

    public function mirrorDeclarations(): array
    {
        return $this->mirrors;
    }

    public function validate(McpRequest $request): void
    {
        ++$this->validateCalls;
        if ($this->validationFailure !== null) {
            throw $this->validationFailure;
        }
    }

    public function handle(McpRequest $request): McpResult
    {
        ++$this->handleCalls;
        if ($this->handlingFailure !== null) {
            throw $this->handlingFailure;
        }

        return McpResult::complete($this->result);
    }
}
