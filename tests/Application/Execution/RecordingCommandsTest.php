<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Execution;

use Fight\Common\Application\Execution\ObservationAdmission;
use Fight\Common\Application\Execution\ObservationRecorder;
use Fight\Common\Application\Execution\ObservationStore;
use Fight\Common\Application\Execution\RecordPhaseObservation;
use Fight\Common\Application\Execution\RecordPhaseObservationHandler;
use Fight\Common\Application\Execution\RecordSessionObservation;
use Fight\Common\Application\Execution\RecordSessionObservationHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RecordSessionObservation::class)]
#[CoversClass(RecordPhaseObservation::class)]
#[CoversClass(RecordSessionObservationHandler::class)]
#[CoversClass(RecordPhaseObservationHandler::class)]
final class RecordingCommandsTest extends UnitTestCase
{
    public function test_that_commands_roundtrip_and_handlers_use_the_recording_coordinator(): void
    {
        $store = $this->mock(ObservationStore::class);
        $store->shouldReceive('commitTransactional')->twice()->andReturnNull();
        $recorder = new ObservationRecorder($store, $this->mock(ObservationAdmission::class), $this->mock(EventDispatcher::class));
        foreach ([
            [new RecordSessionObservation(ObservationFixture::session()), new RecordSessionObservationHandler($recorder)],
            [new RecordPhaseObservation(ObservationFixture::phase()), new RecordPhaseObservationHandler($recorder)],
        ] as [$command, $handler]) {
            self::assertSame($command::class, $handler::commandRegistration());
            self::assertSame($command->toArray(), $command::fromArray($command->toArray())->toArray());
            $handler->handle(CommandMessage::create($command));
        }
    }

    public function test_that_session_command_exposes_its_observation_only_through_an_accessor(): void
    {
        $observation = ObservationFixture::session();
        $command = new RecordSessionObservation($observation);

        self::assertSame([], get_object_vars($command));
        self::assertSame($observation, $command->observation());
        self::assertSame($observation->toArray(), $command->toArray());
        self::assertSame($observation->toArray(), RecordSessionObservation::fromArray($command->toArray())->observation()->toArray());
    }

    public function test_that_session_command_rejects_phase_evidence(): void
    {
        $this->expectException(DomainException::class);
        new RecordSessionObservation(ObservationFixture::phase());
    }

    public function test_that_phase_command_rejects_session_evidence(): void
    {
        $this->expectException(DomainException::class);
        new RecordPhaseObservation(ObservationFixture::session());
    }

    public function test_that_handlers_reject_incorrectly_routed_command_types(): void
    {
        $recorder = new ObservationRecorder($this->mock(ObservationStore::class), $this->mock(ObservationAdmission::class), $this->mock(EventDispatcher::class));
        foreach ([
            [new RecordSessionObservationHandler($recorder), new RecordPhaseObservation(ObservationFixture::phase())],
            [new RecordPhaseObservationHandler($recorder), new RecordSessionObservation(ObservationFixture::session())],
        ] as [$handler, $command]) {
            try {
                $handler->handle(CommandMessage::create($command));
                self::fail('Expected routing rejection.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Unexpected recording command.', $exception->getMessage());
            }
        }
    }
}
