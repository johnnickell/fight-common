<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

/**
 * Stores independent event-publication progress by stable publication name
 */
interface PublicationCursorStore
{
    /**
     * Loads the last global position whose fan-out was attempted completely
     */
    public function load(string $publicationName): int;

    /**
     * Saves a completed fan-out position without moving backward
     */
    public function save(string $publicationName, int $globalPosition): void;
}
