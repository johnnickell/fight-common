<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use InvalidArgumentException;

/**
 * Class CanonicalRunsDirectory
 *
 * Carries the canonical runs root and one canonical output directory resolved below it.
 */
final readonly class CanonicalRunsDirectory
{
    /**
     * Constructs CanonicalRunsDirectory
     */
    public function __construct(public string $path, public string $runsRoot)
    {
        if (
            !$this->isCanonicalAbsolutePath($path)
            || !$this->isCanonicalAbsolutePath($runsRoot)
            || ($path !== $runsRoot && !str_starts_with($path, $runsRoot.DIRECTORY_SEPARATOR))
        ) {
            throw new InvalidArgumentException('A canonical runs directory must stay below its absolute runs root.');
        }
    }

    /**
     * Reports whether this authority is exactly the pair of descriptor-verified literals requested by a caller
     */
    public function matches(string $path, string $runsRoot): bool
    {
        return $this->isCanonicalAbsolutePath($this->path)
            && $this->isCanonicalAbsolutePath($this->runsRoot)
            && ($this->path === $this->runsRoot
                || str_starts_with($this->path, $this->runsRoot.DIRECTORY_SEPARATOR))
            && $this->path === $path
            && $this->runsRoot === $runsRoot;
    }

    /**
     * Returns one artifact path below the resolved canonical output directory
     */
    public function artifactPath(string $filename): string
    {
        if (
            $filename === ''
            || str_contains($filename, "\0")
            || preg_match('//u', $filename) !== 1
            || basename($filename) !== $filename
            || $filename === '.'
            || $filename === '..'
        ) {
            throw new InvalidArgumentException('An artifact filename must be one non-empty path segment.');
        }

        return $this->path.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * Reports whether one absolute path is already in its unambiguous lexical canonical form
     */
    private function isCanonicalAbsolutePath(string $path): bool
    {
        if (
            $path === ''
            || $path === DIRECTORY_SEPARATOR
            || $path[0] !== DIRECTORY_SEPARATOR
            || str_ends_with($path, DIRECTORY_SEPARATOR)
            || str_contains($path, "\0")
            || preg_match('//u', $path) !== 1
        ) {
            return false;
        }

        return array_all(
            explode(DIRECTORY_SEPARATOR, substr($path, 1)),
            fn (string $component): bool => !in_array($component, ['', '.', '..'], true)
        );
    }
}
