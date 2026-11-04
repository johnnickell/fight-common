<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Service;

use Fight\Common\Application\Mcp\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Mcp\Notice\NoticeId;
use Fight\Common\Domain\Mcp\Notice\NoticeRemovalSelection;
use Fight\Common\Domain\Mcp\Notice\NoticeRepository;

/**
 * Class NoticeRemover
 */
final readonly class NoticeRemover
{
    /**
     * Constructs NoticeRemover
     */
    public function __construct(
        private NoticeRepository $noticeRepository,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private NoticeRemovalAdmission $noticeRemovalAdmission
    ) {
    }

    /**
     * Removes admitted exact incarnations atomically without returning existence or affected counts
     */
    public function remove(NoticeRemovalSelection $selection): void
    {
        $this->transactionalUnitOfWork->commitTransactional(function () use ($selection): void {
            $this->noticeRemovalAdmission->admit($selection);
            foreach ($selection->targets() as $target) {
                $retained = $this->noticeRepository->getByIdentity(NoticeId::fromStrings(
                    $target['scope'],
                    $target['producer'],
                    $target['id']
                ));
                if ($retained === null || $retained->position() !== $target['position']) {
                    throw new NoticeRemovalRejected();
                }

                $this->noticeRemovalAdmission->admitRetained($retained->notice());
            }

            $this->noticeRepository->remove($selection);
        });
    }
}
