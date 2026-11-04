<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Service;

use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Application\Mcp\Notice\Exception\CommittedNoticeDispatchFailed;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Mcp\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeRepository;
use Fight\Common\Domain\Mcp\Notice\RecordedNotice;
use Throwable;

/**
 * Class NoticeRecorder
 */
final readonly class NoticeRecorder
{
    /**
     * Constructs NoticeRecorder
     */
    public function __construct(
        private NoticeRepository $noticeRepository,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private NoticeAdmission $noticeAdmission,
        private EventDispatcher $eventDispatcher
    ) {
    }

    /**
     * Records admitted notices and attempts safe fact dispatch only after actual commit
     */
    public function record(Notice $notice): void
    {
        $recorded = $this->transactionalUnitOfWork->commitTransactional(function () use ($notice): ?RecordedNotice {
            $this->noticeAdmission->admit($notice);
            $existing = $this->noticeRepository->getByIdentity($notice->identity());
            if ($existing !== null) {
                if ($existing->notice()->comparison() !== $notice->comparison()) {
                    throw new NoticeConflict();
                }

                return null;
            }

            $time = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            return $this->noticeRepository->append($notice, $time->format('Y-m-d\TH:i:s.u\Z'));
        });
        if ($recorded === null) {
            return;
        }

        $event = NoticeRecorded::fromRecorded($recorded);
        try {
            $this->eventDispatcher->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedNoticeDispatchFailed($event, $throwable);
        }
    }
}
