<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Uri\Rfc3986\Uri;

/**
 * Class McpResourceInfo
 */
final readonly class McpResourceInfo implements Arrayable
{
    /**
     * Constructs McpResourceInfo
     */
    private function __construct(private StrictJson $metadata)
    {
    }

    /**
     * Creates validated immutable Resource metadata without resolving its URI or opening content
     *
     * Empty protocol strings remain strings. Optional objects use StrictJson or associative arrays; use
     * StrictJson::fromObject([]) for an empty nested object. Unknown fields are rejected, not silently dropped.
     *
     * @param array<string, mixed> $metadata
     */
    public static function fromArray(array $metadata, McpResourceLimits $limits = new McpResourceLimits()): self
    {
        $data = StrictJson::fromObject($metadata, maxDepth: 32);
        if (
            array_diff(array_keys($metadata), [
                'uri', 'name', 'title', 'description', 'mimeType', 'size', 'icons', 'annotations', '_meta'
            ]) !== []
            || !is_string($data->get('uri')) || !self::isUri($data->get('uri'))
            || !is_string($data->get('name'))
        ) {
            throw new DomainException('Resource metadata requires an absolute URI and a string name.');
        }

        foreach (['title', 'description', 'mimeType'] as $field) {
            if ($data->has($field) && !is_string($data->get($field))) {
                throw new DomainException('Optional Resource text fields must be strings.');
            }
        }

        if (
            $data->has('size')
            && (!is_int($data->get('size')) || $data->get('size') < 0 || $data->get('size') > 9007199254740991)
        ) {
            throw new DomainException('Resource size must be a non-negative safe integer.');
        }

        if ($data->has('_meta') && !$data->get('_meta') instanceof StrictJson) {
            throw new DomainException('Resource metadata extensions must be an object.');
        }

        if ($data->has('annotations') && !self::validAnnotations($data->get('annotations'))) {
            throw new DomainException('Resource annotations must have valid defined values.');
        }

        if (
            $data->has('icons')
            && (!is_array($data->get('icons'))
                || !array_all($data->get('icons'), self::validIcon(...)))
        ) {
            throw new DomainException('Resource icons must have valid defined values.');
        }

        $info = new self($data);
        $info->validateLimits($limits);

        return $info;
    }

    /**
     * Returns the exact registered URI without normalization or decoding
     */
    public function uri(): string
    {
        return $this->metadata->get('uri');
    }

    /**
     * Returns immutable plain-data metadata with typed nested JSON objects
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->metadata->properties();
    }

    /**
     * Validates metadata against the serving capability's current bounds
     */
    public function validateLimits(McpResourceLimits $limits): void
    {
        if (
            strlen($this->uri()) > $limits->maxUriBytes
            || strlen(json_encode($this->metadata, JSON_THROW_ON_ERROR)) > $limits->maxDescriptorBytes
        ) {
            throw new DomainException('Resource metadata exceeds its configured byte limits.');
        }
    }

    /**
     * Returns whether a value is an absolute RFC 3986 URI
     */
    private static function isUri(string $value): bool
    {
        return Uri::parse($value)?->getScheme() !== null;
    }

    /**
     * Returns whether optional annotations retain their protocol types
     */
    private static function validAnnotations(mixed $value): bool
    {
        if (!$value instanceof StrictJson) {
            return false;
        }

        if (
            $value->has('audience')
            && (!is_array($value->get('audience'))
                || !array_all($value->get('audience'), fn($role): bool => in_array($role, ['user', 'assistant'], true)))
        ) {
            return false;
        }

        if (
            $value->has('priority')
            && ((!is_int($value->get('priority')) && !is_float($value->get('priority')))
                || $value->get('priority') < 0 || $value->get('priority') > 1)
        ) {
            return false;
        }

        return !$value->has('lastModified') || is_string($value->get('lastModified'));
    }

    /**
     * Returns whether an icon has a supported URI and valid optional fields
     */
    private static function validIcon(mixed $icon): bool
    {
        if (!$icon instanceof StrictJson || !is_string($icon->get('src'))) {
            return false;
        }

        $source = $icon->get('src');
        $uri = Uri::parse($source);
        if ($uri === null) {
            return false;
        }

        $scheme = strtolower($uri->getScheme() ?? '');
        if ($scheme === 'data') {
            if (!self::validImageData($source)) {
                return false;
            }
        } elseif (!in_array($scheme, ['https', 'http'], true) || ($uri->getHost() ?? '') === '') {
            return false;
        }

        return (!$icon->has('mimeType') || is_string($icon->get('mimeType')))
            && (!$icon->has('theme') || in_array($icon->get('theme'), ['light', 'dark'], true))
            && (!$icon->has('sizes') || (is_array($icon->get('sizes'))
                && array_all($icon->get('sizes'), fn($size): bool => is_string($size))));
    }

    /**
     * Returns whether an image data URI contains canonical Base64 and valid MIME parameters
     */
    private static function validImageData(string $source): bool
    {
        $parts = explode(',', substr($source, 5), 2);
        $header = explode(';', $parts[0]);
        if (count($parts) !== 2 || strtolower(array_pop($header)) !== 'base64') {
            return false;
        }

        $token = "[!#$%&'*+.^_`{|}~0-9A-Za-z-]+";
        $mediaType = rawurldecode(array_shift($header) ?? '');
        if (preg_match('~^image/('.str_replace('~', '\\~', $token).')$~iD', $mediaType) !== 1) {
            return false;
        }

        foreach ($header as $parameter) {
            $pair = explode('=', $parameter, 2);
            if (
                count($pair) !== 2
                || preg_match('/^'.str_replace('/', '\\/', $token).'$/D', rawurldecode($pair[0])) !== 1
                || !self::validImageParameter($pair[1], $token)
            ) {
                return false;
            }
        }

        $encoded = rawurldecode($parts[1]);
        $bytes = base64_decode($encoded, true);

        return $encoded !== '' && $bytes !== false && base64_encode($bytes) === $encoded;
    }

    /**
     * Returns whether a MIME value uses a token or URL-escaped MIME special characters
     */
    private static function validImageParameter(string $raw, string $token): bool
    {
        $decoded = rawurldecode($raw);
        if (preg_match('/^'.$token.'$/D', $decoded) === 1) {
            return true;
        }

        // RFC 2397 uses URL escapes instead of literal MIME tspecials, including quote delimiters.
        if ($decoded === '' || strpbrk($raw, '()<>@,;:\\"/[]?=') !== false) {
            return false;
        }

        if (str_starts_with($decoded, '"') && str_ends_with($decoded, '"')) {
            return preg_match('/^"(?:[^"\\\\\x00-\x08\x0a-\x1f\x7f-\xff]|\\\\[\x09\x20-\x7e])*"$/D', $decoded) === 1;
        }

        return preg_match('/^[\x21-\x7e]+$/D', $decoded) === 1;
    }
}
