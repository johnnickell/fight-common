<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Detail\Service;

use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionAccess;

/**
 * Class DetailAccess
 *
 * Trusted current protected grants, never inherited from timeline admission
 */
final readonly class DetailAccess
{
    /**
     * Constructs DetailAccess
     */
    private function __construct(private ExecutionAccess $scope, private ExecutionAccess $bodies)
    {
    }

    /**
     * Creates separate metadata and body grants within current project, session and phase scope
     *
     * @param array<string, mixed> $projects
     * @param array<string, mixed> $phaseGrants
     * @param array<string, mixed> $metadataGrants
     * @param array<string, mixed> $bodyGrants
     */
    public static function create(
        array $projects,
        array $phaseGrants = [],
        array $metadataGrants = [],
        array $bodyGrants = []
    ): self {
        return new self(
            ExecutionAccess::create($projects, $phaseGrants, detailGrants: $metadataGrants),
            ExecutionAccess::create($projects, $phaseGrants, detailGrants: $bodyGrants)
        );
    }

    /**
     * Returns structural scope and explicit metadata grants for SQL intersection
     */
    public function scope(): ExecutionAccess
    {
        return $this->scope;
    }

    /**
     * Returns whether the selected metadata identity has a separate current body grant
     */
    public function permitsBody(string $project, string $producer, string $id): bool
    {
        $grants = $this->bodies->detailGrants();
        if (!array_key_exists($project, $grants)) {
            return false;
        }

        return $grants[$project] === null || in_array($producer.'/'.$id, $grants[$project], true);
    }
}
