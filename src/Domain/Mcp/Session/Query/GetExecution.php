<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Session\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class GetExecution
 */
final readonly class GetExecution implements Query
{
    private ExecutionSelection $selection;

    /**
     * Constructs GetExecution
     */
    public function __construct(string $projectId, string $sessionId)
    {
        $this->selection = ExecutionSelection::create([$projectId], ['session_id' => $sessionId]);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['project_id', 'session_id']) {
            throw new DomainException('Invalid execution query structure.');
        }

        return new self(
            ObservationReference::fromString($data['project_id'])->toString(),
            ObservationReference::fromString($data['session_id'])->toString()
        );
    }

    /**
     * Returns the exact independently supplied execution selection
     */
    public function selection(): ExecutionSelection
    {
        return $this->selection;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'project_id' => $this->selection->projects()[0],
            'session_id' => $this->selection->filters()['session_id']
        ];
    }
}
