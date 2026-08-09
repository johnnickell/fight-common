<?php

declare(strict_types=1);

namespace Fight\Common\Application\FileTransfer\Resource;

use DateTimeImmutable;
use Stringable;

/**
 * Class Resource
 */
final readonly class Resource implements Stringable
{
    private string $path;

    private int $mode;

    /**
     * Constructs Resource
     */
    public function __construct(
        string $path,
        private int $size,
        private int $userId,
        private int $groupId,
        int $mode,
        private DateTimeImmutable $accessTime,
        private DateTimeImmutable $modifyTime,
        private ResourceType $type
    ) {
        $this->path = trim($path);
        $this->mode = (int) octdec(substr(sprintf('%04o', $mode), -4));
    }

    /**
     * Retrieves the resource path
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Retrieves the size in bytes
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Retrieves the user ID
     */
    public function userId(): int
    {
        return $this->userId;
    }

    /**
     * Retrieves the group ID
     */
    public function groupId(): int
    {
        return $this->groupId;
    }

    /**
     * Retrieves the mode
     */
    public function mode(): int
    {
        return $this->mode;
    }

    /**
     * Retrieves the permissions as an octal string
     */
    public function permissions(): string
    {
        return substr(sprintf('%04o', $this->mode), -4);
    }

    /**
     * Retrieves the access time
     */
    public function accessTime(): DateTimeImmutable
    {
        return $this->accessTime;
    }

    /**
     * Retrieves the modify time
     */
    public function modifyTime(): DateTimeImmutable
    {
        return $this->modifyTime;
    }

    /**
     * Retrieves the resource type
     */
    public function type(): ResourceType
    {
        return $this->type;
    }

    /**
     * Handles casting to a string
     */
    public function __toString(): string
    {
        return $this->path;
    }
}
