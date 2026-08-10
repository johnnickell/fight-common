<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\Common\Application\EventSourcing\ProjectionCheckpointStore;
use InvalidArgumentException;

/**
 * Doctrine DBAL adapter for durable projection checkpoints
 */
final readonly class DbalProjectionCheckpointStore implements ProjectionCheckpointStore
{
    /**
     * Constructs DbalProjectionCheckpointStore
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Loads a projector checkpoint, defaulting to the start of history
     */
    public function load(string $projectorName): int
    {
        $checkpoint = $this->connection->fetchOne(
            'SELECT global_position FROM projection_checkpoints WHERE projector_name = ?',
            [$projectorName],
        );

        return false === $checkpoint ? 0 : (int) $checkpoint;
    }

    /**
     * Saves a projector checkpoint without moving backward
     */
    public function save(string $projectorName, int $globalPosition): void
    {
        if (0 > $globalPosition) {
            $checkpoint = $this->load($projectorName);

            throw new InvalidArgumentException(sprintf(
                'Projection checkpoint %s cannot move backward from %d to %d.',
                $projectorName,
                $checkpoint,
                $globalPosition,
            ));
        }

        if (0 !== $this->advance($projectorName, $globalPosition)) {
            return;
        }

        try {
            $this->connection->insert('projection_checkpoints', [
                'projector_name'  => $projectorName,
                'global_position' => $globalPosition
            ]);

            return;
        } catch (UniqueConstraintViolationException) {
            $this->advance($projectorName, $globalPosition);
        }

        $checkpoint = $this->load($projectorName);

        if ($globalPosition < $checkpoint) {
            throw new InvalidArgumentException(sprintf(
                'Projection checkpoint %s cannot move backward from %d to %d.',
                $projectorName,
                $checkpoint,
                $globalPosition,
            ));
        }
    }

    /**
     * Resets one projector checkpoint to the start of history
     */
    public function reset(string $projectorName): void
    {
        if (
            0 === $this->connection->update(
                'projection_checkpoints',
                ['global_position' => 0],
                ['projector_name' => $projectorName],
            )
        ) {
            try {
                $this->connection->insert('projection_checkpoints', [
                    'projector_name'  => $projectorName,
                    'global_position' => 0
                ]);
            } catch (UniqueConstraintViolationException) {
                $this->connection->update(
                    'projection_checkpoints',
                    ['global_position' => 0],
                    ['projector_name' => $projectorName],
                );
            }
        }
    }

    /**
     * Advances an existing checkpoint when the stored position permits it
     *
     * @phpstan-impure
     */
    private function advance(string $projectorName, int $globalPosition): int
    {
        return $this->connection->executeStatement(
            <<<'SQL'
                UPDATE projection_checkpoints
                SET global_position = ?
                WHERE projector_name = ? AND global_position <= ?
                SQL,
            [$globalPosition, $projectorName, $globalPosition],
        );
    }
}
