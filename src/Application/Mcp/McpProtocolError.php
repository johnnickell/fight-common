<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Type\Arrayable;

/**
 * Class McpProtocolError
 */
final readonly class McpProtocolError implements Arrayable
{
    public const int PARSE_ERROR = -32700;
    public const int INVALID_REQUEST = -32600;
    public const int METHOD_NOT_FOUND = -32601;
    public const int INVALID_PARAMS = -32602;
    public const int INTERNAL_ERROR = -32603;
    public const int UNSUPPORTED_PROTOCOL_VERSION = -32022;

    /**
     * Constructs McpProtocolError
     *
     * @param integer                   $code
     * @param string                    $message
     * @param array<string, mixed>|null $data
     */
    private function __construct(private int $code, private string $message, private ?array $data = null)
    {
    }

    /**
     * Creates a malformed JSON error
     */
    public static function parseError(): self
    {
        return new self(self::PARSE_ERROR, 'Parse error.');
    }

    /**
     * Creates an invalid JSON-RPC request error
     */
    public static function invalidRequest(): self
    {
        return new self(self::INVALID_REQUEST, 'Invalid request.');
    }

    /**
     * Creates an unknown method error
     */
    public static function methodNotFound(): self
    {
        return new self(self::METHOD_NOT_FOUND, 'Method not found.');
    }

    /**
     * Creates an invalid outer parameter error
     */
    public static function invalidParams(): self
    {
        return new self(self::INVALID_PARAMS, 'Invalid params.');
    }

    /**
     * Creates a generic internal error
     */
    public static function internalError(): self
    {
        return new self(self::INTERNAL_ERROR, 'Internal error.');
    }

    /**
     * Creates an unsupported protocol version error
     *
     * @param array  $supportedVersions
     *
     * @phpstan-param list<string> $supportedVersions
     */
    public static function unsupportedProtocolVersion(array $supportedVersions, string $requestedVersion): self
    {
        return new self(
            self::UNSUPPORTED_PROTOCOL_VERSION,
            'Unsupported protocol version.',
            ['supported' => $supportedVersions, 'requested' => $requestedVersion]
        );
    }

    /**
     * Returns the protocol error representation
     *
     * @return array{code: int, message: string, data?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $error = ['code' => $this->code, 'message' => $this->message];
        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return $error;
    }
}
