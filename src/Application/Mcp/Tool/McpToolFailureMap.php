<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Domain\Exception\DomainException;
use Throwable;

/**
 * Class McpToolFailureMap
 */
final readonly class McpToolFailureMap
{
    /**
     * Constructs McpToolFailureMap
     *
     * Only exact registered failure classes are public-safe. Messages are consumer-authored constants,
     * never exception messages, arguments or diagnostic detail. Subclasses require their own registration.
     *
     * @param array<string, string> $messages
     */
    public function __construct(private array $messages = [])
    {
        foreach ($messages as $class => $message) {
            if (
                !is_a($class, Throwable::class, true)
                || trim($message) === ''
                || strlen($message) > 4096
                || preg_match('/[\x00-\x1f\x7f]/u', $message) !== 0
            ) {
                throw new DomainException('Tool failure mappings require a Throwable class and bounded safe text.');
            }
        }
    }

    /**
     * Returns an explicitly authored public message for an exact failure type
     */
    public function messageFor(Throwable $failure): ?string
    {
        return $this->messages[$failure::class] ?? null;
    }
}
