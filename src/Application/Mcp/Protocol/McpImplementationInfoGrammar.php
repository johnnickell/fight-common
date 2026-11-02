<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpImplementationInfoGrammar
 *
 * @internal Implementation descriptor syntax, including website and icon URI/MIME/encoding rules
 */
final class McpImplementationInfoGrammar
{
    /**
     * Returns whether a client implementation identity has valid defined properties
     */
    public static function isValid(StrictJson $clientInfo): bool
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
}
