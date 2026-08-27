<?php

declare(strict_types=1);

namespace Symfony\Component\Mercure;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

/**
 * Interface HubInterface
 */
interface HubInterface
{
    /**
     * Sends an update
     */
    public function publish(Update $update): string;
}

/**
 * Class Update
 */
final readonly class Update
{
    /**
     * Constructs Update
     */
    public function __construct(
        private array|string $topics,
        private string $data,
        private bool $private = false,
        private ?string $id = null,
        private ?string $type = null,
        private ?int $retry = null
    ) {
    }

    /**
     * Returns the update topics.
     */
    public function getTopics(): array
    {
        return (array) $this->topics;
    }

    /**
     * Returns the update data
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Reports whether the update is private
     */
    public function isPrivate(): bool
    {
        return $this->private;
    }

    /**
     * Returns the update identifier.
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Returns the update type.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Returns the update retry delay.
     */
    public function getRetry(): ?int
    {
        return $this->retry;
    }
}
