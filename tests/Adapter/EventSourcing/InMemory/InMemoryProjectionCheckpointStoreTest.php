<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\InMemory;

use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryProjectionCheckpointStore;
use Fight\Common\Application\EventSourcing\ProjectionCheckpointStore;
use Fight\Test\Common\TestCase\EventSourcing\ProjectionCheckpointStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryProjectionCheckpointStore::class)]
final class InMemoryProjectionCheckpointStoreTest extends ProjectionCheckpointStoreConformanceTestCase
{
    protected function createProjectionCheckpointStore(): ProjectionCheckpointStore
    {
        return new InMemoryProjectionCheckpointStore();
    }
}
