<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Detail\Service;

use Fight\Common\Application\Mcp\Execution\Detail\Exception\DetailRemovalRejected;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Mcp\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Mcp\Observation\ObservationRepository;

/**
 * Class DetailRemover
 */
final readonly class DetailRemover
{
    /**
     * Constructs DetailRemover
     *
     * Both repositories must share the recording transaction and serialization boundary
     */
    public function __construct(
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private ObservationRepository $observationRepository,
        private ProtectedContentRepository $protectedContentRepository,
        private DetailRemovalAdmission $detailRemovalAdmission
    ) {
    }

    /**
     * Removes one admitted protected row before acknowledging the real commit
     */
    public function remove(RemoveCapturedDetail $command): void
    {
        $this->transactionalUnitOfWork->commitTransactional(function () use ($command): void {
            $this->detailRemovalAdmission->admit($command);
            $selection = $command->selection();
            $detail = $this->observationRepository->getById($selection->producerId(), $command->detailId());
            if ($detail === null || !$selection->acceptsDetail($detail, $command->kind())) {
                throw new DetailRemovalRejected();
            }

            $activity = $this->observationRepository->getById($selection->producerId(), $selection->activityId());
            $capture = $detail->detail();
            if ($activity === null || $capture === null || !$capture->acceptsActivity($detail, $activity)) {
                throw new DetailRemovalRejected();
            }

            // Retaining identity prevents retries from silently restoring removed bodies.
            $this->protectedContentRepository->removeFor($detail);
        });
    }
}
