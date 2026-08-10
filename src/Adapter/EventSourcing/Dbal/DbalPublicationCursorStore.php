<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\Common\Application\EventSourcing\PublicationCursorStore;
use InvalidArgumentException;

/**
 * Class DbalPublicationCursorStore
 *
 * Doctrine DBAL adapter for durable event-publication cursors
 */
final readonly class DbalPublicationCursorStore implements PublicationCursorStore
{
    /**
     * Constructs DbalPublicationCursorStore
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Loads a publication cursor, defaulting to the start of history
     */
    public function load(string $publicationName): int
    {
        $cursor = $this->connection->fetchOne(
            'SELECT global_position FROM publication_cursors WHERE publication_name = ?',
            [$publicationName],
        );

        return false === $cursor ? 0 : (int) $cursor;
    }

    /**
     * Advances a completed fan-out position without moving backward
     */
    public function save(string $publicationName, int $globalPosition): void
    {
        if (0 > $globalPosition) {
            $cursor = $this->load($publicationName);

            throw new InvalidArgumentException(sprintf(
                'Publication cursor %s cannot move backward from %d to %d.',
                $publicationName,
                $cursor,
                $globalPosition,
            ));
        }

        if (0 !== $this->advance($publicationName, $globalPosition)) {
            return;
        }

        try {
            $this->connection->insert('publication_cursors', [
                'publication_name' => $publicationName,
                'global_position'  => $globalPosition
            ]);

            return;
        } catch (UniqueConstraintViolationException) {
            $this->advance($publicationName, $globalPosition);
        }

        $cursor = $this->load($publicationName);

        if ($globalPosition < $cursor) {
            throw new InvalidArgumentException(sprintf(
                'Publication cursor %s cannot move backward from %d to %d.',
                $publicationName,
                $cursor,
                $globalPosition,
            ));
        }
    }

    /**
     * Advances an existing cursor when the stored position permits it
     *
     * @phpstan-impure
     */
    private function advance(string $publicationName, int $globalPosition): int
    {
        return $this->connection->executeStatement(
            <<<'SQL'
                UPDATE publication_cursors
                SET global_position = ?
                WHERE publication_name = ? AND global_position <= ?
                SQL,
            [$globalPosition, $publicationName, $globalPosition],
        );
    }
}
