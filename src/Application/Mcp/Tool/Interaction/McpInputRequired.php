<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpInputRequired
 */
final readonly class McpInputRequired
{
    /**
     * Constructs McpInputRequired
     *
     * @param array<string, McpInputRequest> $requests
     */
    private function __construct(private array $requests, private bool $confirmation = false)
    {
    }

    /**
     * Creates an ordinary request for one or more keyed forms without dispatching a use case
     *
     * @param array<mixed> $requests
     */
    public static function fromRequests(array $requests): self
    {
        if ($requests === []) {
            throw new DomainException('An interaction must request at least one form.');
        }

        foreach ($requests as $key => $request) {
            if (!is_string($key) || $key === '' || !$request instanceof McpInputRequest) {
                throw new DomainException('Input request keys must be nonempty non-numeric strings with form values.');
            }
        }

        StrictJson::fromObject(array_fill_keys(array_keys($requests), null));

        return new self($requests);
    }

    /**
     * Creates consumer-designated confirmation forms requiring atomic single-use state
     *
     * Common does not decide destructiveness or interpret confirmation content as business approval.
     * All keyed responses must accept before resume; any decline or cancel retires the whole interaction.
     *
     * @param array<mixed> $requests
     */
    public static function confirmation(array $requests): self
    {
        return new self(self::fromRequests($requests)->requests, true);
    }

    /**
     * Returns whether these forms require consumer-provided atomic confirmation storage
     */
    public function requiresConfirmation(): bool
    {
        return $this->confirmation;
    }

    /**
     * Returns the typed form contracts keyed by response identifier
     *
     * @return array<string, McpInputRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }
}
