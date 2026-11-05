<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice;

/**
 * Interface NoticeRepository
 *
 * Retained aggregate persistence inside the separately composed serialization boundary
 * This contract grants neither recording nor removal permission
 */
interface NoticeRepository
{
    /**
     * Returns retained identity evidence inside the owned transaction
     */
    public function getByIdentity(NoticeId $identity): ?RecordedNotice;

    /**
     * Adds related content, comparison and position inside the owned transaction
     */
    public function append(Notice $notice, string $recordedAt): RecordedNotice;

    /**
     * Removes exact selected incarnations while atomically advancing bounded loss evidence
     *
     * Requires the owned transaction and prior complete removal admission
     * Missing, replaced or denied targets and mismatched namespaces share fixed rejection
     */
    public function remove(NoticeRemovalSelection $selection): void;
}
