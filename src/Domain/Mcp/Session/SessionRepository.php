<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Session;

/**
 * Interface SessionRepository
 */
interface SessionRepository
{
    /**
     * Returns a recorded subject by its project-scoped identity
     */
    public function getById(SessionId $id): ?Session;

    /**
     * Adds an immutable session inside the recording transaction
     */
    public function add(Session $session): void;
}
