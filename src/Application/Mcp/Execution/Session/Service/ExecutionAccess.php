<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Session\Service;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationFields;

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
     * @param array<string, list<string>|null> $activityGrants
     * @param array<string, list<string>|null> $detailGrants
     */
    private function __construct(
        private array $projects,
        private array $phaseGrants,
        private array $activityGrants,
        private array $detailGrants
    ) {
    }

    /**
     * Creates current whole-project or explicit session grants
     *
     * @param array<string, mixed> $projects
     * @param array<string, mixed> $phaseGrants
     * @param array<string, mixed> $activityGrants
     * @param array<string, mixed> $detailGrants
     */
    public static function create(
        array $projects,
        array $phaseGrants = [],
        array $activityGrants = [],
        array $detailGrants = []
    ): self {
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

        return new self(
            $safe,
            $phases,
            self::observationGrants($safe, $activityGrants),
            self::observationGrants($safe, $detailGrants)
        );
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

    /**
     * Returns activity-browsing restrictions, with omitted projects unrestricted
     *
     * @return array<string, list<string>|null>
     */
    public function activityGrants(): array
    {
        return $this->activityGrants;
    }

    /**
     * Returns explicit reference-disclosure grants, with omitted projects denied
     *
     * Null grants all references within otherwise permitted activity, never bodies
     *
     * @return array<string, list<string>|null>
     */
    public function detailGrants(): array
    {
        return $this->detailGrants;
    }

    /**
     * Returns finite project-scoped producer/full-UUID reference lists
     *
     * @param array<string, list<string>|null> $projects
     * @param array<string, mixed> $grants
     *
     * @return array<string, list<string>|null>
     */
    private static function observationGrants(array $projects, array $grants): array
    {
        $safe = [];
        foreach ($grants as $project => $ids) {
            if (!array_key_exists($project, $projects)) {
                throw new DomainException('Invalid execution observation access.');
            }

            if ($ids === null) {
                $safe[$project] = null;
                continue;
            }

            if (!is_array($ids) || !array_is_list($ids) || count($ids) > 100) {
                throw new DomainException('Invalid execution observation access.');
            }

            $validated = [];
            foreach ($ids as $id) {
                if (
                    !is_string($id)
                    || preg_match(
                        '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\/[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D',
                        $id
                    ) !== 1
                ) {
                    throw new DomainException('Invalid execution observation access.');
                }

                $validated[] = $id;
            }

            $safe[$project] = array_values(array_unique($validated));
        }

        return $safe;
    }
}
