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
     * @param array<string, list<string>> $phaseGrants
     */
    private function __construct(private array $projects, private array $phaseGrants)
    {
    }

    /**
     * Creates current whole-project or explicit session grants
     *
     * @param array<string, mixed> $projects
     * @param array<string, mixed> $phaseGrants
     */
    public static function create(array $projects, array $phaseGrants = []): self
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

        $phases = [];
        foreach ($phaseGrants as $project => $ids) {
            if (!array_key_exists($project, $safe) || !is_array($ids) || !array_is_list($ids) || count($ids) > 100) {
                throw new DomainException('Invalid execution phase access.');
            }

            $validated = [];
            foreach ($ids as $id) {
                $validated[] = ObservationFields::reference($id);
            }

            $phases[$project] = array_values(array_unique($validated));
        }

        return new self($safe, $phases);
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

    /**
     * Returns optional project-scoped phase restrictions, never additional session authority
     *
     * @return array<string, list<string>>
     */
    public function phaseGrants(): array
    {
        return $this->phaseGrants;
    }
}
