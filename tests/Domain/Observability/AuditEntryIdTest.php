<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Observability;

use Fight\Common\Domain\Observability\AuditEntryId;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuditEntryId::class)]
class AuditEntryIdTest extends UnitTestCase
{
    public function test_that_generate_creates_unique_ids(): void
    {
        $a = AuditEntryId::generate();
        $b = AuditEntryId::generate();

        self::assertFalse($a->equals($b));
    }

    public function test_that_from_string_round_trips(): void
    {
        $id = AuditEntryId::generate();
        $restored = AuditEntryId::fromString($id->toString());

        self::assertTrue($id->equals($restored));
    }
}
