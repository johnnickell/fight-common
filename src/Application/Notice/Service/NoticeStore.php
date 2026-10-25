<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Service;

use Fight\Common\Application\Notice\Data\NoticeRecoveryWindow;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;
use Fight\Common\Domain\Notice\RecordedNotice;

/**
 * Interface NoticeStore
 *
 * Trusted internal storage, not an authorized public read or removal use case
 */
interface NoticeStore extends TransactionalUnitOfWork
{
    /**
     * Returns retained identity evidence inside the store-owned transaction
     */
    public function getByIdentity(string $scope, string $producer, string $id): ?RecordedNotice;

    /**
     * Adds related content, comparison and position inside the store-owned transaction
     */
    public function append(Notice $notice, string $recordedAt): RecordedNotice;

    /**
     * Removes exact selected incarnations and their comparisons while atomically advancing bounded loss evidence
     *
     * Requires the owned transaction and prior complete removal admission
     * Missing, replaced or denied targets and mismatched namespaces share fixed rejection
     */
    public function remove(NoticeRemovalSelection $selection): void;

    /**
     * Returns bounded rows and global loss evidence under one coherent serialization boundary
     *
     * Requires an idle store and owns actual commit; unavailable evidence fails closed
     * Neither raw namespace counters nor global loss changes may be publicly disclosed across concealed scopes
     *
     * @param string|list<string> $scope
     */
    public function readWindow(string|array $scope, int $position, int $limit): NoticeRecoveryWindow;

    /**
     * Returns at most 100 retained notices after an exclusive position in ascending committed order
     *
     * Internal reconstruction does not authorize disclosure or establish complete history
     *
     * @return list<RecordedNotice>
     */
    public function readAfter(string $scope, int $position, int $limit): array;
}
