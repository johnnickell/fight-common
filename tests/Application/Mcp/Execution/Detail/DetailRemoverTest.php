<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Execution\Detail;

use Fight\Common\Application\Mcp\Execution\Detail\CommandHandler\RemoveCapturedDetailHandler;
use Fight\Common\Application\Mcp\Execution\Detail\Exception\DetailRemovalRejected;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailRemovalAdmission;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailRemover;
use Fight\Common\Application\Mcp\Execution\RecordingTransaction;
use Fight\Common\Domain\Mcp\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\DetailKind;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Mcp\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Mcp\Observation\ObservationRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DetailRemover::class)]
#[CoversClass(RemoveCapturedDetailHandler::class)]
#[CoversClass(DetailRemovalRejected::class)]
final class DetailRemoverTest extends UnitTestCase
{
    public function test_that_handler_admits_before_lookup_and_removes_only_inside_its_real_commit_boundary(): void
    {
        $command = $this->command(); $detail = ObservationFixture::detail();
        $transaction = $this->mock(RecordingTransaction::class);
        $active = false; $committed = false;
        $transaction->shouldReceive('commitTransactional')->once()->andReturnUsing(static function (callable $operation) use (&$active, &$committed): void {
            $active = true; $operation(); $active = false; $committed = true;
        });
        $admission = $this->mock(DetailRemovalAdmission::class);
        $admission->shouldReceive('admit')->once()->with($command)->globally()->ordered()->andReturnUsing(static function () use (&$active): void { self::assertTrue($active); });
        $observations = $this->mock(ObservationRepository::class);
        $observations->shouldReceive('getById')->once()->with('instrumentation', $command->detailId())->globally()->ordered()->andReturn($detail);
        $observations->shouldReceive('getById')->once()->with('instrumentation', $command->selection()->activityId())->globally()->ordered()->andReturn(ObservationFixture::activity());
        $contents = $this->mock(ProtectedContentRepository::class);
        $contents->shouldReceive('removeFor')->once()->with($detail)->globally()->ordered()->andReturnUsing(static function () use (&$active, &$committed): void { self::assertTrue($active); self::assertFalse($committed); });
        $handler = new RemoveCapturedDetailHandler(new DetailRemover($transaction, $observations, $contents, $admission));
        self::assertSame(RemoveCapturedDetail::class, $handler::commandRegistration());
        $handler->handle(CommandMessage::create($command));
        self::assertTrue($committed);
    }

    #[DataProvider('rejections')]
    public function test_that_denied_unknown_and_inconsistent_selections_share_safe_rejection_without_deletion(string $case): void
    {
        $transaction = $this->mock(RecordingTransaction::class);
        $transaction->shouldReceive('commitTransactional')->once()->andReturnUsing(static fn (callable $operation) => $operation());
        $admission = $this->mock(DetailRemovalAdmission::class);
        $permit = $admission->shouldReceive('admit')->once()->globally()->ordered();
        $observations = $this->mock(ObservationRepository::class);
        if ($case === 'denied') { $permit->andThrow(new DetailRemovalRejected()); }
        else {
            $observations->shouldReceive('getById')->once()->globally()->ordered()->andReturn(match ($case) {
                'unknown' => null,
                'wrong-detail' => ObservationFixture::phase(),
                default => ObservationFixture::detail()
            });
            if (in_array($case, ['missing-activity', 'wrong-activity'], true)) {
                $observations->shouldReceive('getById')->once()->globally()->ordered()->andReturn($case === 'missing-activity' ? null : ObservationFixture::phase());
            }
        }
        $contents = $this->mock(ProtectedContentRepository::class);
        $contents->shouldNotReceive('removeFor');
        $remover = new DetailRemover($transaction, $observations, $contents, $admission);
        $this->expectException(DetailRemovalRejected::class);
        $this->expectExceptionMessage('Captured detail removal was not admitted.');
        $remover->remove($this->command());
    }

    public static function rejections(): iterable
    {
        foreach (['denied', 'unknown', 'wrong-detail', 'missing-activity', 'wrong-activity'] as $case) { yield [$case]; }
    }

    public function test_that_wrong_handler_routing_never_enters_the_removal_transaction(): void
    {
        $handler = new RemoveCapturedDetailHandler(new DetailRemover($this->mock(RecordingTransaction::class), $this->mock(ObservationRepository::class), $this->mock(ProtectedContentRepository::class), $this->mock(DetailRemovalAdmission::class)));
        $this->expectException(InvalidArgumentException::class);
        $handler->handle(CommandMessage::create(new RecordSessionObservation(ObservationFixture::session())));
    }

    public function test_that_transaction_failure_is_not_acknowledged_as_success(): void
    {
        $transaction = $this->mock(RecordingTransaction::class);
        $failure = new RuntimeException('commit did not acknowledge');
        $transaction->shouldReceive('commitTransactional')->once()->andThrow($failure);
        $remover = new DetailRemover($transaction, $this->mock(ObservationRepository::class), $this->mock(ProtectedContentRepository::class), $this->mock(DetailRemovalAdmission::class));
        try { $remover->remove($this->command()); self::fail('Expected original failure.'); }
        catch (RuntimeException $actual) { self::assertSame($failure, $actual); }
    }

    private function command(): RemoveCapturedDetail
    {
        return new RemoveCapturedDetail(DetailSelection::create('project', 'session', 'phase', 'instrumentation', ObservationFixture::activity()->id()->toString()), ObservationFixture::detail()->id(), DetailKind::MESSAGE);
    }
}
