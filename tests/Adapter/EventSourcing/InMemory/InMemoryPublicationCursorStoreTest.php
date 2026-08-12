<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\InMemory;

use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryPublicationCursorStore;
use Fight\Common\Application\EventSourcing\PublicationCursorStore;
use Fight\Test\Common\TestCase\EventSourcing\PublicationCursorStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class InMemoryPublicationCursorStoreTest
 *
 * In-memory publication cursor conformance tests
 */
#[CoversClass(InMemoryPublicationCursorStore::class)]
final class InMemoryPublicationCursorStoreTest extends PublicationCursorStoreConformanceTestCase
{
    /**
     * Creates the in-memory publication cursor store under test
     */
    protected function createPublicationCursorStore(): PublicationCursorStore
    {
        return new InMemoryPublicationCursorStore();
    }
}
