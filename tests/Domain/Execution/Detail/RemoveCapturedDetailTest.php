<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Detail;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(RemoveCapturedDetail::class)]
#[CoversClass(DetailSelection::class)]
final class RemoveCapturedDetailTest extends UnitTestCase
{
    public function test_that_removal_envelopes_select_exactly_one_detail_and_do_not_carry_permission_or_bodies(): void
    {
        $detail = ObservationFixture::detail();
        foreach (DetailKind::cases() as $kind) {
            $command = new RemoveCapturedDetail(self::selection(), $detail->id(), $kind);
            $copy = CommandMessage::arrayDeserialize(CommandMessage::create($command)->arraySerialize())->payload();
            self::assertSame($command->toArray(), $copy->toArray());
            self::assertSame(self::selection()->toArray(), $copy->selection()->toArray());
            self::assertTrue($detail->id()->equals($copy->detailId()));
            self::assertSame($kind, $copy->kind());
            self::assertSame(['selection', 'detail_id', 'kind'], array_keys($copy->toArray()));
            self::assertSame([], get_object_vars($copy));
        }
    }

    public function test_that_relationship_selection_checks_every_scope_component_and_detail_kind(): void
    {
        $detail = ObservationFixture::detail();
        self::assertTrue(self::selection()->acceptsDetail($detail, DetailKind::MESSAGE));
        self::assertFalse(self::selection()->acceptsDetail($detail, DetailKind::TOOL_RESULT));
        self::assertFalse(self::selection()->acceptsDetail(ObservationFixture::phase(), DetailKind::MESSAGE));
        foreach (['project_id', 'session_id', 'phase_id', 'producer_id', 'activity_id'] as $field) {
            $data = self::selection()->toArray();
            $data[$field] = $field === 'activity_id' ? ObservationId::generate()->toString() : 'different';
            self::assertFalse(DetailSelection::fromArray($data)->acceptsDetail($detail, DetailKind::MESSAGE));
        }
        self::assertFalse(self::selection()->acceptsDetail(ObservationFixture::session(), DetailKind::MESSAGE));
    }

    #[DataProvider('invalidCommands')]
    public function test_that_unbounded_or_malformed_removal_payloads_reject(array $changes): void
    {
        $command = new RemoveCapturedDetail(self::selection(), ObservationFixture::detail()->id(), DetailKind::MESSAGE);
        $this->expectException(DomainException::class);
        RemoveCapturedDetail::fromArray(array_replace($command->toArray(), $changes));
    }

    public static function invalidCommands(): iterable
    {
        foreach ([
            ['selection' => null], ['detail_id' => []], ['detail_id' => 'not-a-uuid'],
            ['detail_id' => strtoupper(ObservationFixture::detail()->id()->toString())],
            ['kind' => null], ['kind' => 'unsupported'], ['detail_ids' => []], ['permission' => true],
            ['selection' => ['project_id' => 'project']]
        ] as $case) { yield [$case]; }
    }

    public static function selection(): DetailSelection
    {
        return DetailSelection::create('project', 'session', 'phase', 'instrumentation', ObservationFixture::activity()->id()->toString());
    }
}
