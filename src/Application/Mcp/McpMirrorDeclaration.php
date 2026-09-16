<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpMirrorDeclaration
 */
final readonly class McpMirrorDeclaration
{
    /**
     * Constructs McpMirrorDeclaration
     *
     * @param string       $method
     * @param array        $parameterPath
     * @param string       $headerName
     *
     * @phpstan-param array<array-key, mixed> $parameterPath
     */
    public function __construct(
        private string $method,
        private array $parameterPath,
        private string $headerName
    ) {
        if (trim($this->method) === '') {
            throw new DomainException('An MCP mirror declaration requires a method.');
        }

        $hasInvalidPathSegment = !array_is_list($this->parameterPath) || array_filter(
            $this->parameterPath,
            static fn (mixed $segment): bool => !is_string($segment) || $segment === ''
        ) !== [];
        if ($this->parameterPath === [] || $hasInvalidPathSegment) {
            throw new DomainException('An MCP mirror declaration requires a non-empty property path.');
        }

        if (preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/", $this->headerName) !== 1) {
            throw new DomainException('An MCP mirrored header name must be an HTTP field-name token.');
        }
    }

    /**
     * Returns the owning MCP method
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Returns the property path mirrored by a future HTTP adapter
     *
     * @return list<string>
     */
    public function parameterPath(): array
    {
        return $this->parameterPath;
    }

    /**
     * Returns the x-mcp-header name portion
     */
    public function headerName(): string
    {
        return $this->headerName;
    }
}
