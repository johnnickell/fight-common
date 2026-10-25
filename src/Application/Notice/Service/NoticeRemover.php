<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Service;

use Fight\Common\Application\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;

/**
 * Class NoticeRemover
 */
final readonly class NoticeRemover
{
    /**
     * Constructs NoticeRemover
     */
    public function __construct(private NoticeStore $store, private NoticeRemovalAdmission $admission)
    {
    }

    /**
     * Removes admitted exact incarnations atomically without returning existence or affected counts
     */
    public function remove(NoticeRemovalSelection $selection): void
    {
        $this->store->commitTransactional(function () use ($selection): void {
            $this->admission->admit($selection);
            foreach ($selection->targets() as $target) {
                $retained = $this->store->getByIdentity($target['scope'], $target['producer'], $target['id']);
                if ($retained === null || $retained->position() !== $target['position']) {
                    throw new NoticeRemovalRejected();
                }

                $this->admission->admitRetained($retained->notice());
            }

            $this->store->remove($selection);
        });
    }
}
