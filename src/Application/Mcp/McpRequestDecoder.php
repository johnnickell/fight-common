<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use JsonException;

/**
 * Class McpRequestDecoder
 */
final readonly class McpRequestDecoder
{
    /**
     * Constructs McpRequestDecoder
     */
    public function __construct(private McpRequestLimits $limits = new McpRequestLimits())
    {
    }

    /**
     * Returns the shared ingress and decoding limits
     */
    public function limits(): McpRequestLimits
    {
        return $this->limits;
    }

    /**
     * Decodes and validates one MCP JSON-RPC request within finite byte and parsing budgets
     */
    public function decode(string $json): McpRequest
    {
        if (strlen($json) > $this->limits->maxBytes) {
            throw new McpProtocolException(McpProtocolError::invalidRequest(), null);
        }

        try {
            $decoded = json_decode($json, false, $this->limits->maxDepth, JSON_THROW_ON_ERROR);
            $data = StrictJson::fromData($decoded, maxDepth: 511);
        } catch (DomainException | JsonException) {
            throw new McpProtocolException(McpProtocolError::parseError(), null);
        }

        if (!$data->isObject()) {
            throw new McpProtocolException(McpProtocolError::invalidRequest(), null);
        }

        $data = $data->properties();
        $requestId = $this->usableRequestId($data);
        if (($data['jsonrpc'] ?? null) !== '2.0') {
            throw new McpProtocolException(McpProtocolError::invalidRequest(), $requestId);
        }

        if ($requestId === null) {
            throw new McpProtocolException(McpProtocolError::invalidRequest(), null);
        }

        $method = $data['method'] ?? null;
        if (!is_string($method)) {
            throw new McpProtocolException(McpProtocolError::invalidRequest(), $requestId);
        }

        $params = $data['params'] ?? null;
        if (!$params instanceof StrictJson) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $requestId);
        }

        $metadata = $params->get('_meta');
        if (!$metadata instanceof StrictJson) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $requestId);
        }

        try {
            $requestMetadata = McpRequestMetadata::fromObject($metadata);
        } catch (McpProtocolException $mcpProtocolException) {
            throw new McpProtocolException($mcpProtocolException->protocolError(), $requestId);
        }

        $parameters = $params->properties();
        unset($parameters['_meta']);

        return new McpRequest($requestId, $method, $parameters, $requestMetadata);
    }

    /**
     * Returns the request identifier only when it is usable for an error response
     *
     * @param array<string, mixed> $data
     */
    private function usableRequestId(array $data): int|string|null
    {
        $id = $data['id'] ?? null;

        return is_int($id) || is_string($id) ? $id : null;
    }
}
