<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Value;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeId;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Observation\ObservationIdentity;
use Fight\Common\Domain\Mcp\Phase\Phase;
use Fight\Common\Domain\Mcp\Phase\PhaseId;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Session\Session;
use Fight\Common\Domain\Mcp\Session\SessionId;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SessionId::class)]
#[CoversClass(PhaseId::class)]
#[CoversClass(NoticeId::class)]
#[CoversClass(ObservationIdentity::class)]
#[CoversClass(Session::class)]
#[CoversClass(Phase::class)]
#[CoversClass(Notice::class)]
#[CoversClass(Observation::class)]
final class EvidenceIdentitiesTest extends UnitTestCase
{
    public function test_that_scoped_identity_values_preserve_roles_scope_case_and_delimiter_bytes(): void
    {
        $session = SessionId::fromStrings('P:1', 'same');
        $phase = PhaseId::fromStrings('P:1', 'same');
        self::assertSame('P:1', $session->projectId());
        self::assertSame('same', $session->sessionId());
        self::assertSame('P:1', $phase->projectId());
        self::assertSame('same', $phase->phaseId());
        self::assertFalse($session->equals($phase));
        self::assertFalse($session->equals(SessionId::fromStrings('P:2', 'same')));
        self::assertFalse($phase->equals(PhaseId::fromStrings('p:1', 'same')));
        self::assertFalse(SessionId::fromStrings('a:b', 'c')->equals(SessionId::fromStrings('a', 'b:c')));
        $notice = NoticeId::fromStrings('scope/a', 'producer:1', 'same');
        self::assertSame('scope/a', $notice->scope());
        self::assertSame('producer:1', $notice->producer());
        self::assertSame('same', $notice->id());
        self::assertFalse($notice->equals(NoticeId::fromStrings('scope/b', 'producer:1', 'same')));
        self::assertFalse($notice->equals(NoticeId::fromStrings('scope/a', 'producer:2', 'same')));
        $uuid = ObservationId::fromString('019a1234-5678-4abc-8def-123456789abc');
        $observation = ObservationIdentity::fromParts('producer:1', $uuid);
        self::assertSame($uuid, $observation->id());
        self::assertSame('producer:1', $observation->producerId());
        self::assertFalse($observation->equals(ObservationIdentity::fromParts('producer:2', $uuid)));
        self::assertFalse($observation->equals($uuid));
        foreach ([$session, $phase, $notice, $observation] as $identity) {
            $class = $identity::class;
            $copy = $class::fromString($identity->toString());
            self::assertTrue($identity->equals($copy));
            self::assertSame($identity->hashValue(), $copy->hashValue());
            self::assertSame($identity->toString(), (string) $copy);
            self::assertSame($identity->toString(), json_encode($copy, JSON_THROW_ON_ERROR));
        }

        self::assertSame('["P:1","same"]', $session->toString());
        self::assertSame('["scope\\/a","producer:1","same"]', $notice->toString());
    }

    public function test_that_roots_own_scoped_identities_without_confusing_identity_and_relationships(): void
    {
        $session = new Session(SessionReference::fromStrings('project', 'session', 'consumer'));
        $otherRelationship = SessionReference::fromStrings('project', 'session', 'other-consumer');
        self::assertTrue($session->identity()->equals(new Session($otherRelationship)->identity()));
        self::assertFalse($session->matches($otherRelationship));
        $phase = new Phase('project', PhaseReference::fromStrings('session', 'phase', null, 'agent', 'review', 1));
        self::assertTrue($phase->identity()->equals(PhaseId::fromStrings('project', 'phase')));
        self::assertTrue($phase->belongsTo($session));
        self::assertFalse($phase->belongsTo(new Session(SessionReference::fromStrings('other-project', 'session', 'consumer'))));
        $observation = ObservationFixture::session();
        self::assertTrue($observation->identity()->equals(ObservationIdentity::fromParts($observation->producerId(), $observation->id())));
        $notice = Notice::fromArray(NoticeFixture::data());
        self::assertTrue($notice->identity()->equals(NoticeId::fromStrings($notice->scope(), $notice->producer(), $notice->id())));
    }

    #[DataProvider('invalidIdentities')]
    public function test_that_identity_reconstruction_rejects_invalid_tuples_without_normalization(string $class, string $text): void
    {
        $this->expectException(DomainException::class);
        $class::fromString($text);
    }

    public static function invalidIdentities(): iterable
    {
        foreach ([SessionId::class, PhaseId::class, NoticeId::class, ObservationIdentity::class] as $class) {
            foreach (['broken', 'null', '{}', '[]', '["a"]', '["a","b","c","d"]', '[" a","b"]', '[1,"b"]'] as $text) {
                yield [$class, $text];
            }
        }

        yield [SessionId::class, '["scope/a","b"]'];
        yield [PhaseId::class, '["a","scope/b"]'];
        yield [NoticeId::class, '["a","b",null]'];
        yield [ObservationIdentity::class, '["producer",1]'];
        yield [ObservationIdentity::class, '["producer","019A1234-5678-4ABC-8DEF-123456789ABC"]'];
        yield [ObservationIdentity::class, '["bad/producer","019a1234-5678-4abc-8def-123456789abc"]'];
    }
}
