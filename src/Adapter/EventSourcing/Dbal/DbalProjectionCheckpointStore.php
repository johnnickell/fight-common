<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Common\Application\EventSourcing\ProjectionCheckpointStore;
use InvalidArgumentException;

/**
 * Class DbalProjectionCheckpointStore
 *
 * Doctrine DBAL adapter for durable projection checkpoints
 */
final readonly class DbalProjectionCheckpointStore implements ProjectionCheckpointStore
{
    /**
     * Constructs DbalProjectionCheckpointStore
     */
    public function __construct(
        private Connection $connection,
        private DbalTableName $table = new DbalTableName('projection_checkpoints')
    ) {
    }

    /**
     * Loads a projector checkpoint, defaulting to the start of history
     */
    public function load(string $projectorName): int
    {
        $checkpoint = $this->connection->fetchOne(
            sprintf('SELECT global_position FROM %s WHERE projector_name = ?', $this->tableName()),
            [$projectorName]
        );

        return false === $checkpoint ? 0 : (int) $checkpoint;
    }

    /**
     * Advances a projector checkpoint without moving backward
     */
    public function save(string $projectorName, int $globalPosition): void
    {
        if (0 > $globalPosition) {
            $checkpoint = $this->load($projectorName);

            throw new InvalidArgumentException(sprintf(
                'Projection checkpoint %s cannot move backward from %d to %d.',
                $projectorName,
                $checkpoint,
                $globalPosition
            ));
        }

        if (0 !== $this->advance($projectorName, $globalPosition)) {
            return;
        }

        try {
            $this->connection->insert($this->tableName(), [
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
                $globalPosition
            ));
        }
    }

    /**
     * Clears one projector checkpoint to the start of history
     */
    public function reset(string $projectorName): void
    {
        if (
            0 === $this->connection->update(
                $this->tableName(),
                ['global_position' => 0],
                ['projector_name' => $projectorName]
            )
        ) {
            try {
                $this->connection->insert($this->tableName(), [
                    'projector_name'  => $projectorName,
                    'global_position' => 0
                ]);
            } catch (UniqueConstraintViolationException) {
                $this->connection->update(
                    $this->tableName(),
                    ['global_position' => 0],
                    ['projector_name' => $projectorName]
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
            sprintf(
                'UPDATE %s SET global_position = ? WHERE projector_name = ? AND global_position <= ?',
                $this->tableName()
            ),
            [$globalPosition, $projectorName, $globalPosition]
        );
    }

    /**
     * Returns the selected platform-quoted checkpoint table
     */
    private function tableName(): string
    {
        return $this->table->quoted($this->connection->getDatabasePlatform());
    }
}
