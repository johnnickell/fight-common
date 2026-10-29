<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Task\Command;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Task\TaskDefinition;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class SupplyTaskInput
 */
final readonly class SupplyTaskInput implements Command
{
    public const int MAX_BYTES = 32768;

    /**
     * Constructs SupplyTaskInput
     */
    public function __construct(
        public string $scope,
        public string $id,
        public string $caller,
        public StrictJson $responses
    ) {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
        TaskDefinition::reference($caller);
        $responses = StrictJson::fromData($responses, 32);
        if (
            !$responses->isObject() || count($responses->properties()) > 64
            || strlen($responses->toString()) > self::MAX_BYTES
        ) {
            throw new DomainException('Invalid Task input response envelope.');
        }

        foreach (array_keys($responses->properties()) as $key) {
            if (!is_string($key) || preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D', $key) !== 1) {
                throw new DomainException('Invalid Task input response key.');
            }
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['scope'], $data['id'], $data['caller'], StrictJson::fromData($data['responses']));
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->scope, 'id' => $this->id, 'caller' => $this->caller, 'responses' => $this->responses];
    }
}
