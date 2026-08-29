<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Messaging\Laravel;

use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SyncQueue;

/**
 * Class RecordingSyncQueue
 *
 * Records the exact Laravel payload and worker job created by the synchronous queue.
 */
final class RecordingSyncQueue extends SyncQueue
{
    private SyncJob $lastJob;
    private string $lastPayload;

    /**
     * Returns the most recently created worker job
     */
    public function lastJob(): SyncJob
    {
        return $this->lastJob;
    }

    /**
     * Returns the exact most recently serialized queue payload
     */
    public function lastPayload(): string
    {
        return $this->lastPayload;
    }

    /**
     * Records Laravel's serialized payload boundary before worker execution
     */
    protected function resolveJob($payload, $queue): SyncJob
    {
        $this->lastPayload = $payload;
        $this->lastJob = parent::resolveJob($payload, $queue);

        return $this->lastJob;
    }
}
