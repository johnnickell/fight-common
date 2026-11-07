<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ActivityResult
 *
 * Observed boundary result, never business success or durable delivery
 */
final readonly class ActivityResult extends ValueObject
{
    /**
     * Constructs ActivityResult
     */
    private function __construct(private string $kind, private ?string $failureKind, private ?int $exitCode)
    {
    }

    /**
     * Creates an explicitly unknown result
     */
    public static function unknown(): self
    {
        return new self('unknown', null, null);
    }

    /**
     * Creates evidence that a call returned without asserting business commit
     */
    public static function returned(): self
    {
        return new self('returned', null, null);
    }

    /**
     * Creates evidence that a call threw a safe bounded failure classification
     */
    public static function threw(string $failureKind): self
    {
        return new self('threw', ObservationReference::fromString($failureKind)->toString(), null);
    }

    /**
     * Creates evidence of the actual native integer process exit code
     */
    public static function exited(int $exitCode): self
    {
        return new self('exited', null, $exitCode);
    }

    /**
     * Creates evidence of observed cancellation, not merely a request
     */
    public static function cancelled(): self
    {
        return new self('cancelled', null, null);
    }

    /**
     * Creates a result from its exact kind-specific representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['exit_code', 'failure_kind', 'kind'] || !is_string($data['kind'])) {
            throw new DomainException('Invalid activity result structure.');
        }

        if ($data['kind'] === 'threw' && is_string($data['failure_kind']) && $data['exit_code'] === null) {
            return self::threw($data['failure_kind']);
        }

        if ($data['kind'] === 'exited' && $data['failure_kind'] === null && is_int($data['exit_code'])) {
            return self::exited($data['exit_code']);
        }

        if ($data['failure_kind'] !== null || $data['exit_code'] !== null) {
            throw new DomainException('Invalid activity result fields.');
        }

        return match ($data['kind']) {
            'unknown'   => self::unknown(),
            'returned'  => self::returned(),
            'cancelled' => self::cancelled(),
            default     => throw new DomainException('Invalid activity result kind.')
        };
    }

    /**
     * Creates a result from structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid activity result text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns the observed result kind
     */
    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * Returns the safe classification only for a thrown result
     */
    public function failureKind(): ?string
    {
        return $this->failureKind;
    }

    /**
     * Returns the supplied native exit code only for an exited result
     */
    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Returns stable structured result text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the closed result representation with incompatible fields null
     *
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'failure_kind' => $this->failureKind, 'exit_code' => $this->exitCode];
    }

    /**
     * Returns deliberate structured JSON
     *
     * @return array<string, string|int|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
