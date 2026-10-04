<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpInputResponse
 */
final readonly class McpInputResponse
{
    /**
     * Constructs McpInputResponse
     */
    private function __construct(private string $action, private ?ApplicationData $content)
    {
    }

    /**
     * Creates a response after envelope and accept-only validation
     *
     * @internal
     */
    public static function validated(string $action, ?ApplicationData $content): self
    {
        if (
            !in_array($action, ['accept', 'decline', 'cancel'], true)
            || ($action === 'accept') !== ($content !== null)
        ) {
            throw new DomainException('Invalid interaction response.');
        }

        return new self($action, $content);
    }

    /**
     * Returns accept, decline or cancel without conflating ordinary input with confirmation
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Returns validated form content only for accept
     */
    public function content(): ?ApplicationData
    {
        return $this->content;
    }
}
