<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Service;

use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Notice\Exception\CommittedNoticeDispatchFailed;
use Fight\Common\Application\Notice\Exception\NoticeConflict;
use Fight\Common\Domain\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\RecordedNotice;
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
        private NoticeStore $store,
        private NoticeAdmission $admission,
        private EventDispatcher $events
    ) {
    }

    /**
     * Records admitted notices and attempts safe fact dispatch only after actual commit
     */
    public function record(Notice $notice): void
    {
        $recorded = $this->store->commitTransactional(function () use ($notice): ?RecordedNotice {
            $this->admission->admit($notice);
            $existing = $this->store->getByIdentity($notice->scope(), $notice->producer(), $notice->id());
            if ($existing !== null) {
                if ($existing->notice()->comparison() !== $notice->comparison()) {
                    throw new NoticeConflict();
                }

                return null;
            }

            $time = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            return $this->store->append($notice, $time->format('Y-m-d\TH:i:s.u\Z'));
        });
        if ($recorded === null) {
            return;
        }

        $event = NoticeRecorded::fromRecorded($recorded);
        try {
            $this->events->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedNoticeDispatchFailed($event, $throwable);
        }
    }
}
