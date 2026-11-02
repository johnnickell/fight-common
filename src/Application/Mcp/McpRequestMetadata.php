<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Value\Basic\StrictJson;

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

    private const string LOG_LEVEL_KEY = 'io.modelcontextprotocol/logLevel';
    private const array TRACE_CONTEXT_KEYS = [
        'traceparent',
        'tracestate',
        'baggage'
    ];
    private const array RESERVED_METADATA_KEYS = [
        self::PROTOCOL_VERSION_KEY,
        self::CLIENT_CAPABILITIES_KEY,
        self::CLIENT_INFO_KEY,
        self::PROGRESS_TOKEN_KEY,
        self::LOG_LEVEL_KEY,
        ...self::TRACE_CONTEXT_KEYS
    ];
    private const array LOG_LEVELS = [
        'debug',
        'info',
        'notice',
        'warning',
        'error',
        'critical',
        'alert',
        'emergency'
    ];

    /**
     * Constructs McpRequestMetadata
     */
    private function __construct(
        private string $protocolVersion,
        private StrictJson $clientCapabilities,
        private ?StrictJson $clientInfo,
        private int|string|null $progressToken
    ) {
    }

    /**
     * Creates bounded metadata from a request _meta object
     *
     * JSON objects retain their immutable typed representation throughout validation.
     */
    public static function fromObject(StrictJson $metadata): self
    {
        if (!$metadata->isObject()) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        $protocolVersion = $metadata->get(self::PROTOCOL_VERSION_KEY);
        $clientCapabilities = $metadata->get(self::CLIENT_CAPABILITIES_KEY);
        if (
            !is_string($protocolVersion)
            || !$clientCapabilities instanceof StrictJson
            || !self::hasValidClientCapabilities($clientCapabilities)
            || !self::hasValidMetadataKeys($metadata)
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        $clientInfo = null;
        if ($metadata->has(self::CLIENT_INFO_KEY)) {
            $clientInfo = $metadata->get(self::CLIENT_INFO_KEY);
            if (!$clientInfo instanceof StrictJson || !McpImplementationInfoGrammar::isValid($clientInfo)) {
                throw new McpProtocolException(McpProtocolError::invalidParams(), null);
            }
        }

        $progressToken = null;
        if ($metadata->has(self::PROGRESS_TOKEN_KEY)) {
            $progressToken = $metadata->get(self::PROGRESS_TOKEN_KEY);
            if (!is_int($progressToken) && !is_string($progressToken)) {
                throw new McpProtocolException(McpProtocolError::invalidParams(), null);
            }
        }

        if ($metadata->has(self::LOG_LEVEL_KEY)) {
            $logLevel = $metadata->get(self::LOG_LEVEL_KEY);
            if (!is_string($logLevel) || !in_array($logLevel, self::LOG_LEVELS, true)) {
                throw new McpProtocolException(McpProtocolError::invalidParams(), null);
            }
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
     */
    public function clientCapabilities(): StrictJson
    {
        return $this->clientCapabilities;
    }

    /**
     * Returns the optional client implementation identity
     */
    public function clientInfo(): ?StrictJson
    {
        return $this->clientInfo;
    }

    /**
     * Returns the optional progress token
     */
    public function progressToken(): int|string|null
    {
        return $this->progressToken;
    }

    /**
     * Validates the known client capability definitions
     */
    private static function hasValidClientCapabilities(StrictJson $capabilities): bool
    {
        if ($capabilities->has('roots') && !$capabilities->get('roots') instanceof StrictJson) {
            return false;
        }

        foreach (['sampling' => ['context', 'tools'], 'elicitation' => ['form', 'url']] as $name => $members) {
            if (!$capabilities->has($name)) {
                continue;
            }

            $capability = $capabilities->get($name);
            if (
                !$capability instanceof StrictJson
                || !self::hasObjectValuesForKnownProperties($capability, $members)
            ) {
                return false;
            }
        }

        foreach (['experimental', 'extensions'] as $name) {
            if (!$capabilities->has($name)) {
                continue;
            }

            $capability = $capabilities->get($name);
            if (
                !$capability instanceof StrictJson
                || !self::hasOnlyObjectValues($capability)
                || ($name === 'extensions' && !self::hasValidExtensionNames($capability))
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether known object members have JSON-object values when present
     *
     * @param StrictJson    $object
     * @param array<string> $properties
     */
    private static function hasObjectValuesForKnownProperties(StrictJson $object, array $properties): bool
    {
        return array_all($properties, fn($property): bool => self::propertyIsObjectWhenPresent($object, $property));
    }

    /**
     * Returns whether every map value is a JSON object
     */
    private static function hasOnlyObjectValues(StrictJson $object): bool
    {
        return array_all($object->properties(), fn($value): bool => $value instanceof StrictJson);
    }

    /**
     * Returns whether client extension names use a prefixed metadata key
     */
    private static function hasValidExtensionNames(StrictJson $extensions): bool
    {
        return array_all(
            array_keys($extensions->properties()),
            fn($name): bool => self::hasValidExtensionName($name)
        );
    }

    /**
     * Returns whether every top-level metadata key and trace context value is well formed
     */
    private static function hasValidMetadataKeys(StrictJson $metadata): bool
    {
        foreach ($metadata->properties() as $name => $value) {
            if (in_array($name, self::TRACE_CONTEXT_KEYS, true)) {
                if (!McpTraceContextGrammar::hasValidValue($name, $value)) {
                    return false;
                }

                continue;
            }

            if (!in_array($name, self::RESERVED_METADATA_KEYS, true) && !self::hasValidMetadataKey($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether a metadata key follows the MCP naming grammar
     */
    private static function hasValidMetadataKey(string $name): bool
    {
        $pattern = implode('', [
            '/^(?:[A-Za-z](?:[A-Za-z0-9-]*[A-Za-z0-9])?',
            '(?:\\.[A-Za-z](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\\/)?',
            '(?:[A-Za-z0-9](?:[A-Za-z0-9_.-]*[A-Za-z0-9])?)?$/'
        ]);

        return preg_match($pattern, $name) === 1;
    }

    /**
     * Returns whether an extension identifier uses the required metadata prefix
     */
    private static function hasValidExtensionName(string $name): bool
    {
        return str_contains($name, '/') && self::hasValidMetadataKey($name);
    }

    /**
     * Returns whether an optional member is a JSON object when present
     */
    private static function propertyIsObjectWhenPresent(StrictJson $object, string $property): bool
    {
        return !$object->has($property) || $object->get($property) instanceof StrictJson;
    }
}
