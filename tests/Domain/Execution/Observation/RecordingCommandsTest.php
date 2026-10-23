<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Observation;

use Fight\Common\Application\Execution\Observation\CommandHandler\RecordPhaseObservationHandler;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordSessionObservationHandler;
use Fight\Common\Application\Execution\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Execution\ObservationAdmission;
use Fight\Common\Application\Execution\RecordingTransaction;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Execution\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Execution\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Execution\Observation\ObservationRepository;
use Fight\Common\Domain\Execution\Phase\PhaseRepository;
use Fight\Common\Domain\Execution\Session\SessionRepository;
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
        $transaction = $this->mock(RecordingTransaction::class);
        $transaction->shouldReceive('commitTransactional')->twice()->andReturnNull();
        $recorder = $this->recorder($transaction);
        foreach ([
            [new RecordSessionObservation(ObservationFixture::session()), new RecordSessionObservationHandler($recorder)],
            [new RecordPhaseObservation(ObservationFixture::phase()), new RecordPhaseObservationHandler($recorder)],
        ] as [$command, $handler]) {
            self::assertSame($command::class, $handler::commandRegistration());
            self::assertSame($command->toArray(), $command::fromArray($command->toArray())->toArray());
            $message = CommandMessage::create($command);
            $expectedType = $command instanceof RecordSessionObservation
                ? 'Fight.Common.Domain.Execution.Observation.Command.RecordSessionObservation'
                : 'Fight.Common.Domain.Execution.Observation.Command.RecordPhaseObservation';
            self::assertSame($expectedType, $message->toArray()['payload_type']);
            self::assertSame($command::class, CommandMessage::arrayDeserialize($message->toArray())->payload()::class);
            self::assertSame($message->toArray(), CommandMessage::arrayDeserialize($message->toArray())->toArray());
            $handler->handle($message);
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

    public function test_that_phase_command_exposes_its_observation_only_through_an_accessor(): void
    {
        $observation = ObservationFixture::phase();
        $command = new RecordPhaseObservation($observation);

        self::assertSame([], get_object_vars($command));
        self::assertSame($observation, $command->observation());
        self::assertSame($observation->toArray(), $command->toArray());
        self::assertSame($observation->toArray(), RecordPhaseObservation::fromArray($command->toArray())->observation()->toArray());
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
        $recorder = $this->recorder($this->mock(RecordingTransaction::class));
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

    private function recorder(RecordingTransaction $transaction): ObservationRecorder
    {
        return new ObservationRecorder(
            $transaction,
            $this->mock(SessionRepository::class),
            $this->mock(PhaseRepository::class),
            $this->mock(ObservationRepository::class),
            $this->mock(ObservationAdmission::class),
            $this->mock(EventDispatcher::class),
            $this->mock(ProtectedContentRepository::class)
        );
    }
}
