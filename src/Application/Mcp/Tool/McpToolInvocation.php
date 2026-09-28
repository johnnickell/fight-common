<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestMirrors;
use Fight\Common\Application\Mcp\McpResult;
use stdClass;

/**
 * Class McpToolInvocation
 */
final readonly class McpToolInvocation implements McpCapability, McpRequestMirrors
{
    /**
     * Constructs McpToolInvocation
     */
    public function __construct(private McpToolInvoker $invoker)
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
        $this->validate($request);

        return $this->invoker->mirrorsFor($request->parameters()['name']);
    }

    /**
     * @inheritDoc
     */
    public function validate(McpRequest $request): void
    {
        $parameters = $request->parameters();
        if (
            $request->method() !== 'tools/call'
            || $request->metadata()->progressToken() !== null
            || array_diff(array_keys($parameters), ['name', 'arguments']) !== []
            || !is_string($parameters['name'] ?? null)
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
        $parameters = $request->parameters();
        $arguments = array_key_exists('arguments', $parameters) ? $parameters['arguments'] : new stdClass();

        return $this->invoker->invoke($parameters['name'], $arguments);
    }
}
