<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Session\Service;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Value\ObservationFields;

/**
 * Class ExecutionAccess
 *
 * Trusted invocation-local scope, never reconstructed from Query input
 */
final readonly class ExecutionAccess
{
    /**
     * Constructs ExecutionAccess
     *
     * @param array<string, list<string>|null> $projects
     */
    private function __construct(private array $projects)
    {
    }

    /**
     * Creates current whole-project or explicit session grants
     *
     * @param array<string, mixed> $projects
     */
    public static function create(array $projects): self
    {
        if (count($projects) > 16) {
            throw new DomainException('Invalid execution access.');
        }

        $safe = [];
        foreach ($projects as $project => $sessions) {
            $project = ObservationFields::reference((string) $project);
            if ($sessions === null) {
                $safe[$project] = null;
                continue;
            }

            if (!is_array($sessions) || !array_is_list($sessions) || count($sessions) > 100) {
                throw new DomainException('Invalid execution access.');
            }

            $ids = [];
            foreach ($sessions as $session) {
                $ids[] = ObservationFields::reference($session);
            }

            $safe[$project] = array_values(array_unique($ids));
        }

        return new self($safe);
    }

    /**
     * Returns the trusted project and session constraints
     *
     * @return array<string, list<string>|null>
     */
    public function projects(): array
    {
        return $this->projects;
    }
}
