<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use stdClass;

/**
 * Class McpRequestMetadata
 *
 * Bounded protocol metadata, intentionally excluding credentials and principals
 */
final readonly class McpRequestMetadata
{
    public const string PROTOCOL_VERSION_KEY = 'io.modelcontextprotocol/protocolVersion';
    public const string CLIENT_CAPABILITIES_KEY = 'io.modelcontextprotocol/clientCapabilities';
    public const string CLIENT_INFO_KEY = 'io.modelcontextprotocol/clientInfo';
    public const string PROGRESS_TOKEN_KEY = 'progressToken';

    /**
     * Constructs McpRequestMetadata
     *
     * @param string        $protocolVersion
     * @param stdClass      $clientCapabilities
     * @param stdClass|null $clientInfo
     */
    private function __construct(
        private string $protocolVersion,
        private stdClass $clientCapabilities,
        private ?stdClass $clientInfo,
        private int|float|string|null $progressToken
    ) {
    }

    /**
     * Creates bounded metadata from a request _meta object
     *
     * JSON objects remain stdClass so an empty object is not conflated with a JSON list.
     *
     * @param stdClass $metadata
     */
    public static function fromObject(stdClass $metadata): self
    {
        $protocolVersion = $metadata->{self::PROTOCOL_VERSION_KEY} ?? null;
        $clientCapabilities = $metadata->{self::CLIENT_CAPABILITIES_KEY} ?? null;
        if (!is_string($protocolVersion) || $protocolVersion === '' || !$clientCapabilities instanceof stdClass) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        $clientInfo = $metadata->{self::CLIENT_INFO_KEY} ?? null;
        if ($clientInfo !== null && !$clientInfo instanceof stdClass) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        if (
            $clientInfo instanceof stdClass
            && (
                !isset($clientInfo->name)
                || !is_string($clientInfo->name)
                || $clientInfo->name === ''
                || !isset($clientInfo->version)
                || !is_string($clientInfo->version)
                || $clientInfo->version === ''
            )
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        $progressToken = $metadata->{self::PROGRESS_TOKEN_KEY} ?? null;
        if (
            $progressToken !== null
            && !is_int($progressToken)
            && !is_float($progressToken)
            && !is_string($progressToken)
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        return new self($protocolVersion, $clientCapabilities, $clientInfo, $progressToken);
    }

    /**
     * Returns the negotiated protocol version for this request
     */
    public function protocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * Returns client-declared capabilities
     *
     * @return stdClass
     */
    public function clientCapabilities(): stdClass
    {
        return $this->clientCapabilities;
    }

    /**
     * Returns the optional client implementation identity
     *
     * @return stdClass|null
     */
    public function clientInfo(): ?stdClass
    {
        return $this->clientInfo;
    }

    /**
     * Returns the optional progress token
     */
    public function progressToken(): int|float|string|null
    {
        return $this->progressToken;
    }
}
