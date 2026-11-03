<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use stdClass;

final class ProtocolRequest
{
    /**
     * @param integer|float|string             $id
     * @param array<string, mixed>             $parameters
     * @param array<string, mixed>|null        $metadata
     */
    public static function json(
        string $method,
        int|float|string $id,
        array $parameters = [],
        string $protocolVersion = '2026-07-28',
        ?array $metadata = null,
    ): string {
        $metadata = array_replace([
            'io.modelcontextprotocol/protocolVersion'    => $protocolVersion,
            'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
        ], $metadata ?? []);

        return json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'method'  => $method,
            'params'  => [...$parameters, '_meta' => $metadata],
        ], JSON_THROW_ON_ERROR);
    }
}
