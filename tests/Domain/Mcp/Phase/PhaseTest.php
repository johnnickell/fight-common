<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Phase;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Phase\Phase;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Session\Session;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Phase::class)]
final class PhaseTest extends UnitTestCase
{
    public function test_that_phase_owns_immutable_relationships_without_claiming_workflow_state(): void
    {
        foreach ([null, 'parent'] as $parent) {
            $reference = PhaseReference::fromStrings('session', 'phase', $parent, 'agent', 'review', 3);
            $phase = new Phase('project', $reference);
            self::assertSame([], get_object_vars($phase));
            self::assertSame('project', $phase->projectId());
            self::assertSame('phase', $phase->id());
            self::assertSame('session', $phase->sessionId());
            self::assertSame($parent, $phase->parentPhaseId());
            self::assertSame('agent', $phase->agentId());
            self::assertSame('review', $phase->name());
            self::assertSame(3, $phase->attempt());
            self::assertSame($reference, $phase->reference());
            self::assertTrue($phase->matches('project', PhaseReference::fromArray($reference->toArray())));
            self::assertFalse($phase->matches('other', $reference));
            foreach (['session_id' => 'other', 'phase_id' => 'other', 'parent_phase_id' => 'other', 'agent_id' => 'other', 'name' => 'other', 'attempt' => 4] as $key => $value) {
                self::assertFalse($phase->matches('project', PhaseReference::fromArray([$key => $value] + $reference->toArray())));
            }
            self::assertTrue($phase->belongsTo(new Session(SessionReference::fromStrings('project', 'session', 'consumer'))));
            self::assertFalse($phase->belongsTo(new Session(SessionReference::fromStrings('other', 'session', 'consumer'))));
            self::assertFalse($phase->belongsTo(new Session(SessionReference::fromStrings('project', 'other', 'consumer'))));
            self::assertSame($parent !== null, $phase->acceptsParent(new Phase('project', PhaseReference::fromStrings('session', 'parent', null, 'another-agent', 'work', 1))));
            self::assertFalse($phase->acceptsParent(new Phase('other', PhaseReference::fromStrings('session', 'parent', null, 'agent', 'work', 1))));
            self::assertFalse($phase->acceptsParent(new Phase('project', PhaseReference::fromStrings('other', 'parent', null, 'agent', 'work', 1))));
            self::assertFalse($phase->acceptsParent(new Phase('project', PhaseReference::fromStrings('session', 'other', null, 'agent', 'work', 1))));
        }
    }

    public function test_that_phase_project_identity_is_validated(): void
    {
        $this->expectException(DomainException::class);
        new Phase('invalid project', PhaseReference::fromStrings('session', 'phase', null, 'agent', 'work', 1));
    }
}
