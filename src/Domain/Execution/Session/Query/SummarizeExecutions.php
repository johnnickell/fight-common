<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Session\Query;

use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class SummarizeExecutions
 */
final readonly class SummarizeExecutions implements Query
{
    /**
     * Constructs SummarizeExecutions
     */
    public function __construct(private ExecutionSelection $selection)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(ExecutionSelection::fromArray($data));
    }

    /**
     * Returns the independently supplied selection
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
        return $this->selection->toArray();
    }
}
