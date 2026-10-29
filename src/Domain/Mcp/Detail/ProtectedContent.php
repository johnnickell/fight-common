<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Detail;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationFields;

/**
 * Class ProtectedContent
 *
 * Protected representation must never be persisted in the safe ledger
 */
final readonly class ProtectedContent
{
    /**
     * Constructs ProtectedContent
     */
    private function __construct(private ?string $body)
    {
    }

    /**
     * Creates bounded protected content for the supplied capture
     */
    public static function create(DetailCapture $capture, ?string $body): self
    {
        $capture->validateBody($body);

        return new self($body);
    }

    /**
     * Creates a supported protected representation
     *
     * @phpstan-param array<string, mixed> $data
     */
    public static function fromArray(DetailCapture $capture, array $data): self
    {
        if (($data['version'] ?? null) !== 1) {
            throw new DomainException('Unsupported protected content version.');
        }

        ObservationFields::structure($data, ['version', 'body']);
        if ($data['body'] !== null && !is_string($data['body'])) {
            throw new DomainException('Invalid protected content.');
        }

        return self::create($capture, $data['body']);
    }

    /**
     * Returns captured bytes or an explicit absent-content value
     */
    public function body(): ?string
    {
        return $this->body;
    }

    /**
     * Returns whether retained protected content matches exactly
     */
    public function matches(self $other): bool
    {
        return $this->body === $other->body;
    }

    /**
     * Returns the protected representation for trusted transport and storage only
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['version' => 1, 'body' => $this->body];
    }
}
