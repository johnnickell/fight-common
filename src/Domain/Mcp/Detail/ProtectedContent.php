<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Detail;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ProtectedContent
 *
 * Protected representation must never be persisted in the safe ledger
 */
final readonly class ProtectedContent extends ValueObject
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

        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['body', 'version']) {
            throw new DomainException('Invalid protected content structure.');
        }

        if ($data['body'] !== null && !is_string($data['body'])) {
            throw new DomainException('Invalid protected content.');
        }

        return self::create($capture, $data['body']);
    }

    /**
     * Creates bounded protected bytes without granting capture or storage permission
     *
     * Attaching content still validates the selected capture's tighter constraints
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid protected content text.');
        }

        $keys = array_keys($data);
        sort($keys);
        if (
            $keys !== ['body', 'version'] || $data['version'] !== 1
            || ($data['body'] !== null && (!is_string($data['body'])
                || strlen($data['body']) > 65536 || preg_match('//u', $data['body']) !== 1))
        ) {
            throw new DomainException('Invalid protected content.');
        }

        return new self($data['body']);
    }

    /**
     * Returns protected text for trusted transport only, never safe diagnostics
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns protected structured data for trusted transport only
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
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
