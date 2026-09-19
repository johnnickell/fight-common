<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use JsonException;
use stdClass;

/**
 * Class McpRequestDecoder
 */
final class McpRequestDecoder
{
    /**
     * Decodes and validates one MCP JSON-RPC request
     */
    public function decode(string $json): McpRequest
    {
        try {
            $data = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new McpProtocolException(McpProtocolError::parseError(), null);
        }

        if (!$data instanceof stdClass) {
            throw new McpProtocolException(McpProtocolError::invalidRequest(), null);
        }

        /** @var array<string, mixed> $data */
        $data = get_object_vars($data);
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
        if (!$params instanceof stdClass) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $requestId);
        }

        $metadata = $params->_meta ?? null;
        if (!$metadata instanceof stdClass) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $requestId);
        }

        try {
            $requestMetadata = McpRequestMetadata::fromObject($metadata);
        } catch (McpProtocolException $mcpProtocolException) {
            throw new McpProtocolException($mcpProtocolException->protocolError(), $requestId);
        }

        $parameters = get_object_vars($params);
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
