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

    private const string LOG_LEVEL_KEY = 'io.modelcontextprotocol/logLevel';
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
     *
     * @param string        $protocolVersion
     * @param stdClass      $clientCapabilities
     * @param stdClass|null $clientInfo
     */
    private function __construct(
        private string $protocolVersion,
        private stdClass $clientCapabilities,
        private ?stdClass $clientInfo,
        private int|string|null $progressToken
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
        if (
            !is_string($protocolVersion)
            || $protocolVersion === ''
            || !$clientCapabilities instanceof stdClass
            || !self::hasValidClientCapabilities($clientCapabilities)
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        $clientInfo = null;
        if (property_exists($metadata, self::CLIENT_INFO_KEY)) {
            $clientInfo = $metadata->{self::CLIENT_INFO_KEY};
            if (!$clientInfo instanceof stdClass || !self::hasValidClientInfo($clientInfo)) {
                throw new McpProtocolException(McpProtocolError::invalidParams(), null);
            }
        }

        $progressToken = null;
        if (property_exists($metadata, self::PROGRESS_TOKEN_KEY)) {
            $progressToken = $metadata->{self::PROGRESS_TOKEN_KEY};
            if (!is_int($progressToken) && !is_string($progressToken)) {
                throw new McpProtocolException(McpProtocolError::invalidParams(), null);
            }
        }

        if (property_exists($metadata, self::LOG_LEVEL_KEY)) {
            $logLevel = $metadata->{self::LOG_LEVEL_KEY};
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
    public function progressToken(): int|string|null
    {
        return $this->progressToken;
    }

    /**
     * Validates the known client capability definitions
     */
    private static function hasValidClientCapabilities(stdClass $capabilities): bool
    {
        if (
            property_exists($capabilities, 'roots')
            && (!$capabilities->roots instanceof stdClass || !self::hasValidRoots($capabilities->roots))
        ) {
            return false;
        }

        foreach (['sampling' => ['context', 'tools'], 'elicitation' => ['form', 'url']] as $name => $members) {
            if (!property_exists($capabilities, $name)) {
                continue;
            }

            $capability = $capabilities->{$name};
            if (
                !$capability instanceof stdClass
                || !self::hasObjectValuesForKnownProperties($capability, $members)
            ) {
                return false;
            }
        }

        foreach (['experimental', 'extensions'] as $name) {
            if (!property_exists($capabilities, $name)) {
                continue;
            }

            $capability = $capabilities->{$name};
            if (
                !$capability instanceof stdClass
                || !self::hasOnlyObjectValues($capability)
                || ($name === 'extensions' && !self::hasValidExtensionNames($capability))
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates the optional client implementation identity
     */
    private static function hasValidClientInfo(stdClass $clientInfo): bool
    {
        if (
            !isset($clientInfo->name)
            || !is_string($clientInfo->name)
            || $clientInfo->name === ''
            || !isset($clientInfo->version)
            || !is_string($clientInfo->version)
            || $clientInfo->version === ''
        ) {
            return false;
        }

        foreach (['title', 'description'] as $property) {
            if (property_exists($clientInfo, $property) && !is_string($clientInfo->{$property})) {
                return false;
            }
        }

        if (
            property_exists($clientInfo, 'websiteUrl')
            && (!is_string($clientInfo->websiteUrl) || !self::isUri($clientInfo->websiteUrl))
        ) {
            return false;
        }

        return !property_exists($clientInfo, 'icons') || self::hasValidIcons($clientInfo->icons);
    }

    /**
     * Returns whether known object members have JSON-object values when present
     *
     * @param stdClass           $object
     * @param array<string>      $properties
     */
    private static function hasObjectValuesForKnownProperties(stdClass $object, array $properties): bool
    {
        return array_all($properties, fn($property): bool => self::propertyIsObjectWhenPresent($object, $property));
    }

    /**
     * Returns whether the defined roots capability members are well formed
     */
    private static function hasValidRoots(stdClass $roots): bool
    {
        return !property_exists($roots, 'listChanged') || is_bool($roots->listChanged);
    }

    /**
     * Returns whether every map value is a JSON object
     */
    private static function hasOnlyObjectValues(stdClass $object): bool
    {
        return array_all(get_object_vars($object), fn($value): bool => $value instanceof stdClass);
    }

    /**
     * Returns whether client extension names use a prefixed metadata key
     */
    private static function hasValidExtensionNames(stdClass $extensions): bool
    {
        return array_all(array_keys(get_object_vars($extensions)), fn($name): bool => self::hasValidMetadataKey($name));
    }

    /**
     * Returns whether an extension name follows the MCP metadata-key grammar
     */
    private static function hasValidMetadataKey(string $name): bool
    {
        $pattern = implode('', [
            '/^[A-Za-z](?:[A-Za-z0-9-]*[A-Za-z0-9])?',
            '(?:\\.[A-Za-z](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\\/',
            '(?:[A-Za-z0-9](?:[A-Za-z0-9_.-]*[A-Za-z0-9])?)?$/'
        ]);

        return preg_match($pattern, $name) === 1;
    }

    /**
     * Returns whether an implementation icon collection is well formed
     */
    private static function hasValidIcons(mixed $icons): bool
    {
        if (!is_array($icons) || !array_is_list($icons)) {
            return false;
        }

        return array_all($icons, fn($icon): bool => !(!$icon instanceof stdClass || !self::hasValidIcon($icon)));
    }

    /**
     * Returns whether one implementation icon has valid defined properties
     */
    private static function hasValidIcon(stdClass $icon): bool
    {
        if (!isset($icon->src) || !is_string($icon->src) || !self::isUri($icon->src)) {
            return false;
        }

        if (property_exists($icon, 'mimeType') && !is_string($icon->mimeType)) {
            return false;
        }

        if (property_exists($icon, 'theme') && !in_array($icon->theme, ['light', 'dark'], true)) {
            return false;
        }

        if (!property_exists($icon, 'sizes')) {
            return true;
        }

        return is_array($icon->sizes)
            && array_is_list($icon->sizes)
            && array_all($icon->sizes, static fn (mixed $size): bool => is_string($size));
    }

    /**
     * Returns whether a string is an absolute URI
     */
    private static function isUri(string $value): bool
    {
        if (
            preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) !== 1
            || preg_match('/[\\s\\x00-\\x1F\\x7F]/', $value) === 1
            || preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1
            || substr_count($value, '#') > 1
            || preg_match('/[^A-Za-z0-9\\-._~%!$&\'()*+,;=:@\\/?#\\[\\]]/', $value) === 1
        ) {
            return false;
        }

        $components = (array) parse_url($value);
        $scheme = $components['scheme'] ?? null;
        if (!is_string($scheme)) {
            return false;
        }

        if ((str_contains($value, '[') || str_contains($value, ']')) && !self::hasValidIpLiteralHost($components)) {
            return false;
        }

        return !in_array(strtolower($scheme), ['http', 'https'], true)
            || filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Returns whether bracket characters occur only in a valid IP-literal host
     *
     * @param array<string, mixed> $components
     */
    private static function hasValidIpLiteralHost(array $components): bool
    {
        if (!isset($components['host']) || !is_string($components['host'])) {
            return false;
        }

        $host = $components['host'];
        if (
            preg_match(
                '/^\\[v[0-9A-Fa-f]+\\.[A-Za-z0-9\\-._~!$&\'()*+,;=:]+\\]$/',
                $host
            ) === 1
        ) {
            return true;
        }

        return str_starts_with($host, '[')
            && str_ends_with($host, ']')
            && filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Returns whether an optional member is a JSON object when present
     */
    private static function propertyIsObjectWhenPresent(stdClass $object, string $property): bool
    {
        return !property_exists($object, $property) || $object->{$property} instanceof stdClass;
    }
}
