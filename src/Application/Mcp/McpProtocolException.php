<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use RuntimeException;

/**
 * Class McpProtocolException
 *
 * Internal signal used to preserve a usable JSON-RPC identifier during rejection
 */
final class McpProtocolException extends RuntimeException
{
    /**
     * Constructs McpProtocolException
     */
    public function __construct(
        private readonly McpProtocolError $protocolError,
        private readonly int|string|null $requestId
    ) {
        parent::__construct($this->protocolError->toArray()['message']);
    }

    /**
     * Returns the semantic protocol error
     */
    public function protocolError(): McpProtocolError
    {
        return $this->protocolError;
    }

    /**
     * Returns the usable request identifier when one was decoded
     */
    public function requestId(): int|string|null
    {
        return $this->requestId;
    }
}
