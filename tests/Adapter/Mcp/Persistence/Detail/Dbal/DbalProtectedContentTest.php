<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Detail\Dbal;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Detail\Dbal\DbalProtectedContentRepository;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Mcp\Persistence\Observation\Dbal\DbalObservationRepository;
use Fight\Common\Application\Mcp\Observation\CommandHandler\RecordProtectedDetailHandler;
use Fight\Common\Application\Mcp\Observation\Exception\InvalidObservationRelationship;
use Fight\Common\Application\Mcp\Observation\Exception\ObservationConflict;
use Fight\Common\Application\Mcp\Observation\Exception\ProtectedContentRemoved;
use Fight\Common\Application\Mcp\Observation\Service\ObservationAdmission;
use Fight\Common\Application\Mcp\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Mcp\Observation\Command\RecordProtectedDetail;
use Fight\Common\Domain\Mcp\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Mcp\Observation\ObservationRepository;
use Fight\Common\Domain\Mcp\Phase\PhaseRepository;
use Fight\Common\Domain\Mcp\Session\SessionRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\Mcp\Execution\RecordingFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalProtectedContentRepository::class)]
#[CoversClass(RecordProtectedDetailHandler::class)]
#[CoversClass(ObservationRecorder::class)]
#[CoversClass(ProtectedContentRemoved::class)]
final class DbalProtectedContentTest extends UnitTestCase
{
    public function test_that_shared_recording_atomically_links_separate_content_and_retains_no_safe_copy_after_removal(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalObservationSchema())->install($connection);
        $recordingTransaction = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($recordingTransaction);
        foreach ([ObservationFixture::session(), ObservationFixture::phase(), ObservationFixture::activity()] as $observation) { $fixture->record($observation); }
        $detail = ObservationFixture::detail();
        $content = ProtectedContent::create($detail->detail(), 'private-body');
        $protectedContentRepository = new DbalProtectedContentRepository($recordingTransaction);
        self::assertNull($protectedContentRepository->getFor($detail));
        $fixture->record($detail, $content);
        self::assertSame('private-body', $protectedContentRepository->getFor($detail)->body());
        self::assertSame(RecordProtectedDetail::class, RecordProtectedDetailHandler::commandRegistration());
        $saved = (new DbalObservationRepository($recordingTransaction))->getById($detail->producerId(), $detail->id());
        $fixture->record($detail, $content);
        self::assertEquals($saved->recordedAt(), (new DbalObservationRepository($recordingTransaction))->getById($detail->producerId(), $detail->id())->recordedAt());
        try { $fixture->record($detail, ProtectedContent::create($detail->detail(), 'changed-private-body')); self::fail('Expected body conflict.'); }
        catch (ObservationConflict $exception) { self::assertStringNotContainsString('private-body', $exception->getMessage()); }
        $recordingTransaction->commitTransactional(static fn () => $protectedContentRepository->removeFor($detail));
        $recordingTransaction->commitTransactional(static fn () => $protectedContentRepository->removeFor($detail));
        try { $fixture->record($detail, $content); self::fail('Retained identity must not restore deleted content.'); }
        catch (ProtectedContentRemoved $exception) { self::assertSame('Protected comparison content is no longer retained.', $exception->getMessage()); }
        self::assertNull($protectedContentRepository->getFor($detail));
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_observations'));
        foreach ($connection->fetchFirstColumn('SELECT payload FROM fight_execution_observations') as $payload) { self::assertStringNotContainsString('private-body', $payload); }
        $connection->delete('fight_execution_observations', ['producer_id' => $detail->producerId(), 'observation_id' => $detail->id()->toString()]);
        $fixture->record($detail, $content);
        self::assertSame('private-body', $protectedContentRepository->getFor($detail)->body());
        // Deleting the safe identity cascades the separately stored comparison/body too.
        $connection->delete('fight_execution_observations', ['producer_id' => $detail->producerId(), 'observation_id' => $detail->id()->toString()]);
        self::assertNull($protectedContentRepository->getFor($detail));
    }

    public function test_that_detail_recording_requires_existing_matching_activity_and_never_creates_a_phase(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalObservationSchema())->install($connection);
        $recordingTransaction = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($recordingTransaction);
        $fixture->record(ObservationFixture::session());
        $detail = ObservationFixture::detail();
        $content = ProtectedContent::create($detail->detail(), 'private');
        try { $fixture->record($detail, $content); self::fail('Detail cannot create phase.'); } catch (InvalidObservationRelationship) {}
        $fixture->record(ObservationFixture::phase());
        try { $fixture->record($detail, $content); self::fail('Expected existing activity.'); } catch (InvalidObservationRelationship) {}
        // An existing non-activity ID is not an activity reference.
        $metadata = $detail->detail()->toArray();
        $metadata['activity_id'] = ObservationFixture::phase()->id()->toString();
        $invalid = ObservationFixture::detail(['detail' => $metadata]);
        try { $fixture->record($invalid, $content); self::fail('Expected typed target relationship.'); } catch (InvalidObservationRelationship) {}
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_observations'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_contents'));
    }

    public function test_that_content_repository_rejects_unowned_writes_wrong_subjects_and_corrupt_protected_rows(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalObservationSchema())->install($connection);
        $recordingTransaction = new DbalRecordingTransaction($connection);
        $protectedContentRepository = new DbalProtectedContentRepository($recordingTransaction);
        $detail = ObservationFixture::detail();
        $content = ProtectedContent::create($detail->detail(), 'private');
        try { $protectedContentRepository->add($detail, $content); self::fail('Expected owned transaction.'); } catch (LogicException) {}
        try { $protectedContentRepository->getFor(ObservationFixture::phase()); self::fail('Expected detail subject.'); } catch (DomainException) {}
        try { $protectedContentRepository->removeFor($detail); self::fail('Expected owned removal transaction.'); } catch (LogicException) {}
        try { $recordingTransaction->commitTransactional(static fn () => $protectedContentRepository->removeFor(ObservationFixture::phase())); self::fail('Expected detail subject.'); } catch (DomainException) {}
        try { $recordingTransaction->commitTransactional(static fn () => $protectedContentRepository->add(ObservationFixture::phase(), $content)); self::fail('Expected detail subject.'); } catch (DomainException) {}
        $fixture = new RecordingFixture($recordingTransaction);
        foreach ([ObservationFixture::session(), ObservationFixture::phase(), ObservationFixture::activity()] as $observation) { $fixture->record($observation); }
        $fixture->record($detail, $content);
        foreach (['null', '{"version":2,"body":"private"}'] as $payload) {
            $connection->update('fight_execution_contents', ['payload' => $payload], ['producer_id' => 'instrumentation']);
            try { $protectedContentRepository->getFor($detail); self::fail('Expected protected structure/version rejection.'); }
            catch (DomainException $exception) { self::assertStringNotContainsString('private', $exception->getMessage()); }
        }
    }

    public function test_that_pairing_is_enforced_after_admission_and_wrong_handler_routing_rejects(): void
    {
        $recordingTransaction = $this->mock(TransactionalUnitOfWork::class);
        $recordingTransaction->shouldReceive('commitTransactional')->twice()->andReturnUsing(static fn (callable $operation) => $operation());
        $admission = $this->mock(ObservationAdmission::class);
        $admission->shouldReceive('admit')->twice();
        $recorder = new ObservationRecorder($recordingTransaction, $this->mock(SessionRepository::class), $this->mock(PhaseRepository::class), $this->mock(ObservationRepository::class), $admission, $this->mock(EventDispatcher::class), $this->mock(ProtectedContentRepository::class));
        $detail = ObservationFixture::detail();
        $content = ProtectedContent::create($detail->detail(), 'private');
        foreach ([[$detail, null], [ObservationFixture::phase(), $content]] as [$observation, $bytes]) {
            try { $recorder->record($observation, $bytes); self::fail('Expected paired detail content.'); }
            catch (DomainException $exception) { self::assertSame('Detail recording requires paired capture and protected content.', $exception->getMessage()); }
        }
        $handler = new RecordProtectedDetailHandler($recorder);
        $this->expectException(InvalidArgumentException::class);
        $handler->handle(CommandMessage::create(new RecordSessionObservation(ObservationFixture::session())));
    }
}
