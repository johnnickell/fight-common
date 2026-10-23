<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Detail\Service;

use Fight\Common\Application\Execution\Detail\Exception\DetailRemovalRejected;
use Fight\Common\Application\Execution\RecordingTransaction;
use Fight\Common\Domain\Execution\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Execution\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Execution\Observation\ObservationRepository;

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
        private RecordingTransaction $transaction,
        private ObservationRepository $observations,
        private ProtectedContentRepository $contents,
        private DetailRemovalAdmission $admission
    ) {
    }

    /**
     * Removes one admitted protected row before acknowledging the real commit
     */
    public function remove(RemoveCapturedDetail $command): void
    {
        $this->transaction->commitTransactional(function () use ($command): void {
            $this->admission->admit($command);
            $selection = $command->selection();
            $detail = $this->observations->getById($selection->producerId(), $command->detailId());
            if ($detail === null || !$selection->acceptsDetail($detail, $command->kind())) {
                throw new DetailRemovalRejected();
            }

            $activity = $this->observations->getById($selection->producerId(), $selection->activityId());
            $capture = $detail->detail();
            if ($activity === null || $capture === null || !$capture->acceptsActivity($detail, $activity)) {
                throw new DetailRemovalRejected();
            }

            // Retaining identity prevents retries from silently restoring removed bodies.
            $this->contents->removeFor($detail);
        });
    }
}
