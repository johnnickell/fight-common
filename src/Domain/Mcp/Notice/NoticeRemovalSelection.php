<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class NoticeRemovalSelection
 */
final readonly class NoticeRemovalSelection extends ValueObject
{
    public const int MAX_TARGETS = 100;

    /**
     * Constructs NoticeRemovalSelection
     *
     * @phpstan-param list<array{scope: string, producer: string, id: string, position: int}> $targets
     */
    private function __construct(private string $namespace, private array $targets)
    {
    }

    /**
     * Creates an explicit finite selection pinned to a storage incarnation and retained positions
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if (
            $keys !== ['namespace', 'targets', 'version'] || $data['version'] !== 1
            || !is_string($data['namespace']) || preg_match('/\A[0-9a-f]{32}\z/D', $data['namespace']) !== 1
            || !is_array($data['targets']) || !array_is_list($data['targets'])
            || count($data['targets']) < 1 || count($data['targets']) > self::MAX_TARGETS
        ) {
            throw new DomainException('Invalid notice removal selection.');
        }

        $targets = [];
        $positions = [];
        $identities = [];
        foreach ($data['targets'] as $target) {
            if (!is_array($target)) {
                throw new DomainException('Invalid notice removal target.');
            }

            $keys = array_keys($target);
            sort($keys);
            if (
                $keys !== ['id', 'position', 'producer', 'scope'] || !is_int($target['position'])
                || $target['position'] < 1 || $target['position'] > RecordedNotice::MAX_POSITION
            ) {
                throw new DomainException('Invalid notice removal target.');
            }

            $copy = [
                'scope'    => Notice::reference($target['scope']),
                'producer' => Notice::reference($target['producer']),
                'id'       => Notice::reference($target['id']),
                'position' => $target['position']
            ];
            $identity = json_encode([$copy['scope'], $copy['producer'], $copy['id']], JSON_THROW_ON_ERROR);
            if (isset($positions[$copy['position']]) || isset($identities[$identity])) {
                throw new DomainException('Duplicate notice removal target.');
            }

            $positions[$copy['position']] = true;
            $identities[$identity] = true;
            $targets[] = $copy;
        }

        return new self($data['namespace'], $targets);
    }

    /**
     * Creates a finite incarnation-pinned selection from stable text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid notice removal selection text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns stable selection text retaining target order and positions
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the structured finite selection without granting removal
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the selected storage incarnation without assigning authority
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * Returns isolated exact targets rather than an unbounded predicate
     *
     * @return list<array{scope: string, producer: string, id: string, position: int}>
     */
    public function targets(): array
    {
        return $this->targets;
    }

    /**
     * Returns the versioned finite selection
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['version' => 1, 'namespace' => $this->namespace, 'targets' => $this->targets];
    }
}
