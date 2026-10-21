<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Session;

use Fight\Common\Domain\Execution\Session\Session;
use Fight\Common\Domain\Execution\Session\SessionReference;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Session::class)]
final class SessionTest extends UnitTestCase
{
    public function test_that_recorded_session_owns_its_project_identity_and_stable_consumer_relationship(): void
    {
        $reference = SessionReference::fromStrings('project', 'session', 'consumer:147');
        $session = new Session($reference);
        self::assertSame([], get_object_vars($session));
        self::assertSame('project', $session->projectId());
        self::assertSame('session', $session->id());
        self::assertSame('consumer:147', $session->consumerReference());
        self::assertSame($reference, $session->reference());
        self::assertTrue($session->matches(SessionReference::fromArray($reference->toArray())));
        foreach ([['other', 'session', 'consumer:147'], ['project', 'other', 'consumer:147'], ['project', 'session', 'other']] as $fields) {
            self::assertFalse($session->matches(SessionReference::fromStrings(...$fields)));
        }
    }
}
