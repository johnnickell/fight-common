<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestMirrors;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpToolInvocation
 */
final readonly class McpToolInvocation implements McpCapability, McpRequestMirrors
{
    /**
     * Constructs McpToolInvocation
     *
     * The optional execution is a package-internal request binding used by progressive HTTP delivery.
     */
    public function __construct(private McpToolInvoker $invoker, private ?McpToolExecution $execution = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function methods(): array
    {
        return ['tools/call'];
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
    public function mirrorsFor(McpRequest $request): array
    {
        $this->validateParameters($request);

        return $this->invoker->mirrorsFor($request->parameters()['name']);
    }

    /**
     * @inheritDoc
     */
    public function validate(McpRequest $request): void
    {
        $this->validateParameters($request);
        if (
            ($this->execution !== null && !$this->execution->accepts($request))
            || ($request->metadata()->progressToken() !== null && $this->execution === null)
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }
    }

    /**
     * Creates an isolated invocation binding without mutating the registered capability
     *
     * @internal Used only by the request execution and semantic responder
     */
    public function withExecution(McpToolExecution $execution): self
    {
        return new self($this->invoker, $this->execution ?? $execution);
    }

    /**
     * @inheritDoc
     */
    public function handle(McpRequest $request): McpResult
    {
        $this->validate($request);
        $parameters = $request->parameters();
        $arguments = array_key_exists('arguments', $parameters) ? $parameters['arguments'] : StrictJson::fromObject();

        return $this->invoker->invoke($parameters['name'], $arguments, $this->execution?->reporterFor($request));
    }

    /**
     * Validates outer parameters independently of the later delivery binding
     */
    private function validateParameters(McpRequest $request): void
    {
        $parameters = $request->parameters();
        if (
            $request->method() !== 'tools/call'
            || array_diff(array_keys($parameters), ['name', 'arguments']) !== []
            || !is_string($parameters['name'] ?? null)
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }
    }
}
