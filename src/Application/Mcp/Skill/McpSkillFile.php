<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Skill;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpSkillFile
 */
final readonly class McpSkillFile
{
    /**
     * Constructs McpSkillFile
     */
    private function __construct(
        /**
         * @deprecated Use path() instead; retained until a future major after at least one released minor
         */
        public string $path,
        /**
         * @deprecated Use bytes() instead; retained until a future major after at least one released minor
         */
        public string $bytes,
        /**
         * @deprecated Use mimeType() instead; retained until a future major after at least one released minor
         */
        public string $mimeType,
        /**
         * @deprecated Use text() instead; retained until a future major after at least one released minor
         */
        public bool $text,
        /**
         * @deprecated Use digest() instead; retained until a future major after at least one released minor
         */
        public string $digest
    ) {
    }

    /**
     * Creates one explicit immutable member and verifies optional publisher size and digest assertions
     *
     * Paths are decoded relative names, not URLs or filesystem instructions. Percent signs, separators with
     * ambiguous meanings and dot segments are rejected; ordinary Unicode names are encoded once for the URI.
     * The consumer must bound acquisition before supplying strings. No stream, path or URL is followed here.
     */
    public static function fromBytes(
        string $path,
        string $bytes,
        string $mimeType = 'text/plain',
        bool $text = true,
        ?int $expectedSize = null,
        ?string $expectedDigest = null
    ): self {
        self::validatePath($path);
        if (
            strlen($bytes) > 16777216 || strlen($mimeType) > 256 || $mimeType === ''
            || preg_match('/[\x00-\x20\x7f]/', $mimeType) !== 0 || !mb_check_encoding($mimeType, 'UTF-8')
            || ($text && !mb_check_encoding($bytes, 'UTF-8'))
        ) {
            throw new DomainException('Skill file bytes or media type are invalid.');
        }

        $digest = 'sha256:'.hash('sha256', $bytes);
        if (
            ($expectedSize !== null && $expectedSize !== strlen($bytes))
            || ($expectedDigest !== null && !hash_equals($digest, $expectedDigest))
        ) {
            throw new DomainException('Skill file publisher integrity assertions do not match its bytes.');
        }

        return new self($path, $bytes, $mimeType, $text, $digest);
    }

    /**
     * Validates a contained relative member path without interpreting it as a storage target
     *
     * @internal
     */
    public static function validatePath(string $path): void
    {
        if (
            $path === '' || strlen($path) > 2048 || !mb_check_encoding($path, 'UTF-8')
            || preg_match('/[\\\\%:?#\x00-\x1f\x7f]/', $path) !== 0
            || array_any(explode('/', $path), fn($segment): bool => in_array($segment, ['', '.', '..'], true))
        ) {
            throw new DomainException('Skill members require unambiguous contained relative paths.');
        }
    }

    /**
     * Returns the exact decoded relative member path
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Returns the original member bytes
     */
    public function bytes(): string
    {
        return $this->bytes;
    }

    /**
     * Returns the declared media type
     */
    public function mimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Returns whether the member contains validated UTF-8 text
     */
    public function text(): bool
    {
        return $this->text;
    }

    /**
     * Returns the captured raw-byte digest
     */
    public function digest(): string
    {
        return $this->digest;
    }

    /**
     * Returns the canonical encoded relative URI path without changing the source path
     */
    public function uriPath(): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $this->path)));
    }
}
