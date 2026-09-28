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
            if (!$clientInfo instanceof StrictJson || !self::hasValidClientInfo($clientInfo)) {
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
     * Validates the optional client implementation identity
     */
    private static function hasValidClientInfo(StrictJson $clientInfo): bool
    {
        if (!is_string($clientInfo->get('name')) || !is_string($clientInfo->get('version'))) {
            return false;
        }

        foreach (['title', 'description'] as $property) {
            if ($clientInfo->has($property) && !is_string($clientInfo->get($property))) {
                return false;
            }
        }

        if (
            $clientInfo->has('websiteUrl')
            && (!is_string($clientInfo->get('websiteUrl')) || !self::isUri($clientInfo->get('websiteUrl')))
        ) {
            return false;
        }

        return !$clientInfo->has('icons') || self::hasValidIcons($clientInfo->get('icons'));
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
                if (!self::hasValidTraceContextValue($name, $value)) {
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
     * Returns whether a reserved trace context value has its W3C-defined format
     */
    private static function hasValidTraceContextValue(string $name, mixed $value): bool
    {
        return match ($name) {
            'traceparent' => self::hasValidTraceparent($value),
            'tracestate' => self::hasValidTracestate($value),
            'baggage' => self::hasValidBaggage($value),
            default => false,
        };
    }

    /**
     * Returns whether a traceparent has a valid W3C trace-context representation
     */
    private static function hasValidTraceparent(mixed $value): bool
    {
        if (
            !is_string($value)
            || preg_match(
                '/^([0-9a-f]{2})-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})(?:-([\\x21-\\x7e]+))?$/',
                $value,
                $matches
            ) !== 1
        ) {
            return false;
        }

        return $matches[1] !== 'ff'
            && $matches[2] !== str_repeat('0', 32)
            && $matches[3] !== str_repeat('0', 16)
            && ($matches[1] !== '00' || !isset($matches[5]));
    }

    /**
     * Returns whether a tracestate has valid W3C list members
     */
    private static function hasValidTracestate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $members = explode(',', $value);
        if (count($members) > 32) {
            return false;
        }

        $standardKey = '[a-z][a-z0-9_\\-*\\/]{0,255}';
        $tenantKey = '[a-z0-9][a-z0-9_\\-*\\/]{0,240}@[a-z][a-z0-9_\\-*\\/]{0,13}';
        $keys = [];
        foreach ($members as $member) {
            $member = trim($member, " \t");
            if ($member === '') {
                continue;
            }

            if (
                preg_match(
                    sprintf('/^((?:%s)|(?:%s))=(.*)$/', $standardKey, $tenantKey),
                    $member,
                    $matches
                ) !== 1
                || strlen($matches[2]) > 256
                || preg_match(
                    '/^[\\x20-\\x2b\\x2d-\\x3c\\x3e-\\x7e]*[\\x21-\\x2b\\x2d-\\x3c\\x3e-\\x7e]$/',
                    $matches[2]
                ) !== 1
                || isset($keys[$matches[1]])
            ) {
                return false;
            }

            $keys[$matches[1]] = true;
        }

        return true;
    }

    /**
     * Returns whether baggage has valid W3C members and optional properties
     */
    private static function hasValidBaggage(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $members = explode(',', $value);
        if (count($members) > 180) {
            return false;
        }

        return array_all($members, static function (string $member): bool {
            $parts = explode(';', $member);
            $entry = array_shift($parts);
            if (!self::hasValidBaggagePair($entry)) {
                return false;
            }

            return array_all($parts, static fn(string $property): bool => self::hasValidBaggageProperty($property));
        });
    }

    /**
     * Returns whether one baggage member is a valid key/value pair
     */
    private static function hasValidBaggagePair(string $pair): bool
    {
        $separator = strpos($pair, '=');
        if ($separator === false) {
            return false;
        }

        $key = trim(substr($pair, 0, $separator), " \t");
        $value = trim(substr($pair, $separator + 1), " \t");

        return self::isHttpToken($key) && self::hasValidBaggageValue($value);
    }

    /**
     * Returns whether an optional baggage property is valid
     */
    private static function hasValidBaggageProperty(string $property): bool
    {
        if (!str_contains($property, '=')) {
            return self::isHttpToken(trim($property, " \t"));
        }

        return self::hasValidBaggagePair($property);
    }

    /**
     * Returns whether a baggage value contains only permitted octets
     */
    private static function hasValidBaggageValue(string $value): bool
    {
        return preg_match('/^[\\x21\\x23-\\x5b\\x5d-\\x7e]*$/', $value) === 1
            && preg_match('/%(?![0-9A-Fa-f]{2})/', $value) !== 1;
    }

    /**
     * Returns whether a value is an HTTP token
     */
    private static function isHttpToken(string $value): bool
    {
        return preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/", $value) === 1;
    }

    /**
     * Returns whether an implementation icon collection is well formed
     */
    private static function hasValidIcons(mixed $icons): bool
    {
        if (!is_array($icons) || !array_is_list($icons)) {
            return false;
        }

        return array_all($icons, fn($icon): bool => $icon instanceof StrictJson && self::hasValidIcon($icon));
    }

    /**
     * Returns whether one implementation icon has valid defined properties
     */
    private static function hasValidIcon(StrictJson $icon): bool
    {
        if (!is_string($icon->get('src')) || !self::isSafeIconUri($icon->get('src'))) {
            return false;
        }

        if ($icon->has('mimeType') && !is_string($icon->get('mimeType'))) {
            return false;
        }

        if ($icon->has('theme') && !in_array($icon->get('theme'), ['light', 'dark'], true)) {
            return false;
        }

        if (!$icon->has('sizes')) {
            return true;
        }

        return is_array($icon->get('sizes'))
            && array_all($icon->get('sizes'), static fn (mixed $size): bool => is_string($size));
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

        if (
            in_array(strtolower($scheme), ['http', 'https'], true)
            && (!isset($components['host']) || $components['host'] === '')
        ) {
            return false;
        }

        if ((str_contains($value, '[') || str_contains($value, ']')) && !self::hasValidIpLiteralHost($components)) {
            return false;
        }

        return true;
    }

    /**
     * Returns whether an icon URI uses a safe MCP-supported source scheme
     */
    private static function isSafeIconUri(string $value): bool
    {
        if (preg_match('/^https?:/i', $value) === 1) {
            return self::isUri($value);
        }

        return self::hasSafeImageDataUri($value);
    }

    /**
     * Returns whether a data URI contains a safe Base64-encoded image
     */
    private static function hasSafeImageDataUri(string $value): bool
    {
        $separator = strpos($value, ',');
        if ($separator === false || strncasecmp($value, 'data:', 5) !== 0) {
            return false;
        }

        $segments = explode(';', substr($value, 5, $separator - 5));
        $mediaType = array_shift($segments);
        $encoding = array_pop($segments);
        if (
            !self::isImageMediaType($mediaType)
            || $encoding === null
            || strcasecmp($encoding, 'base64') !== 0
            || !array_all($segments, static fn(string $parameter): bool => self::hasValidDataUriParameter($parameter))
        ) {
            return false;
        }

        $payload = self::decodeDataUriComponent(substr($value, $separator + 1));
        if ($payload === null || preg_match('/\A[A-Za-z0-9+\/]+={0,2}\z/', $payload) !== 1) {
            return false;
        }

        $decoded = base64_decode($payload, true);

        return $decoded !== false && base64_encode($decoded) === $payload;
    }

    /**
     * Returns whether a media type is an image type with a valid MIME subtype
     */
    private static function isImageMediaType(string $mediaType): bool
    {
        $parts = explode('/', $mediaType);
        $type = $parts[0];

        return count($parts) === 2
            && self::hasValidMimeToken($type)
            && strcasecmp(self::decodeDataUriComponent($type) ?? '', 'image') === 0
            && self::hasValidMimeToken($parts[1]);
    }

    /**
     * Returns whether a data URI parameter has a valid MIME attribute and value
     */
    private static function hasValidDataUriParameter(string $parameter): bool
    {
        $separator = strpos($parameter, '=');
        if ($separator === false) {
            return false;
        }

        return self::hasValidMimeToken(substr($parameter, 0, $separator))
            && self::hasValidDataUriParameterValue(substr($parameter, $separator + 1));
    }

    /**
     * Returns whether a data URI parameter value uses an RFC 2045-safe representation
     */
    private static function hasValidDataUriParameterValue(string $value): bool
    {
        $decoded = self::decodeDataUriComponent($value);
        if ($decoded === null) {
            return false;
        }

        if (self::hasValidDecodedMimeToken($decoded)) {
            return true;
        }

        if (self::hasValidQuotedMimeString($decoded)) {
            return self::hasOnlyUrlEscapedMimeTspecials($value);
        }

        return self::hasValidUrlEscapedMimeTspecialValue($value);
    }

    /**
     * Returns whether a quoted MIME value has valid safe quoted-string content
     */
    private static function hasValidQuotedMimeString(string $value): bool
    {
        $length = strlen($value);
        if ($length < 2 || $value[0] !== '"' || $value[$length - 1] !== '"') {
            return false;
        }

        for ($index = 1; $index < $length - 1; ++$index) {
            $character = $value[$index];
            if ((ord($character) < 0x20 && ord($character) !== 0x09) || ord($character) > 0x7E) {
                return false;
            }

            if ($character === '\\') {
                ++$index;

                if (
                    $index >= $length - 1
                    || (ord($value[$index]) < 0x20 && ord($value[$index]) !== 0x09)
                    || ord($value[$index]) > 0x7E
                ) {
                    return false;
                }

                continue;
            }

            if ($character === '"') {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether MIME tspecials appear only as URL-escaped data URI bytes
     */
    private static function hasOnlyUrlEscapedMimeTspecials(string $value): bool
    {
        return array_all(str_split($value), fn(string $character): bool => !self::isMimeTspecial($character));
    }

    /**
     * Returns whether a non-token MIME value has URL-escaped tspecials only
     */
    private static function hasValidUrlEscapedMimeTspecialValue(string $value): bool
    {
        $length = strlen($value);
        $hasEscapedTspecial = false;
        for ($index = 0; $index < $length; ++$index) {
            if ($value[$index] === '%') {
                $character = chr(hexdec(substr($value, $index + 1, 2)));
                if (self::hasValidDecodedMimeToken($character)) {
                    $index += 2;

                    continue;
                }

                if (!self::isMimeTspecial($character)) {
                    return false;
                }

                $hasEscapedTspecial = true;
                $index += 2;

                continue;
            }

            if (!self::hasValidDecodedMimeToken($value[$index])) {
                return false;
            }
        }

        return $hasEscapedTspecial;
    }

    /**
     * Returns whether a character is an RFC 2045 MIME tspecial
     */
    private static function isMimeTspecial(string $value): bool
    {
        return str_contains('()<>@,;:\\"/[]?=', $value);
    }

    /**
     * Returns whether a data URI component decodes to a valid MIME token
     */
    private static function hasValidMimeToken(string $value): bool
    {
        $decoded = self::decodeDataUriComponent($value);

        return $decoded !== null && self::hasValidDecodedMimeToken($decoded);
    }

    /**
     * Returns whether a decoded MIME token has valid RFC 2045 characters
     */
    private static function hasValidDecodedMimeToken(string $value): bool
    {
        return preg_match('/\A[!#$%&\'*+\-.0-9A-Z^_`a-z{|}~]+\z/', $value) === 1;
    }

    /**
     * Decodes one RFC 2397 component with valid URL representation
     */
    private static function decodeDataUriComponent(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $length = strlen($value);
        $decoded = '';
        for ($index = 0; $index < $length; ++$index) {
            if ($value[$index] === '%') {
                if (
                    $index + 2 >= $length
                    || !ctype_xdigit($value[$index + 1])
                    || !ctype_xdigit($value[$index + 2])
                ) {
                    return null;
                }

                $decoded .= chr(hexdec(substr($value, $index + 1, 2)));
                $index += 2;

                continue;
            }

            if (
                !ctype_alnum($value[$index])
                && !str_contains('!$&\'()*+-._~;/?:@=,', $value[$index])
            ) {
                return null;
            }

            $decoded .= $value[$index];
        }

        return $decoded;
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
                '/^\\[v[0-9A-Fa-f]+\\.[A-Za-z0-9\\-._~!$&\'()*+,;=:]+\\]$/i',
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
    private static function propertyIsObjectWhenPresent(StrictJson $object, string $property): bool
    {
        return !$object->has($property) || $object->get($property) instanceof StrictJson;
    }
}
