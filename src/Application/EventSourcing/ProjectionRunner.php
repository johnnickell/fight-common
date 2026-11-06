<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

use Fight\Common\Domain\EventSourcing\EventStore;

/**
 * Class ProjectionRunner
 *
 * Runs one bounded projection batch from the projector's saved checkpoint
 */
final readonly class ProjectionRunner
{
    private ProjectionCheckpointStore $projectionCheckpointStore;

    /**
     * Constructs ProjectionRunner
     */
    public function __construct(
        private EventStore $eventStore,
        ProjectionCheckpointStore $checkpointStore
    ) {
        $this->projectionCheckpointStore = $checkpointStore;
    }

    /**
     * Projects one bounded batch of stored events
     */
    public function run(Projector $projector, int $limit): void
    {
        $projectorName = $projector->name();
        $eventClasses = [...$projector->eventClasses()];
        $checkpoint = $this->projectionCheckpointStore->load($projectorName);

        foreach ($this->eventStore->readAllAfter($checkpoint, $limit) as $event) {
            if (in_array($event->message()->payload()::class, $eventClasses, true)) {
                $projector->project($event);
            }

            $this->projectionCheckpointStore->save($projectorName, $event->globalPosition());
        }
    }
}
