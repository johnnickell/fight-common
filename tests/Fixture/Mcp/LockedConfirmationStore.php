<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\Tool\Interaction\McpConfirmationOutcome;
use Fight\Common\Application\Mcp\Tool\Interaction\McpConfirmationStore;
use RuntimeException;

/** Package-owned consumer fixture, not a supported production persistence adapter. */
final readonly class LockedConfirmationStore implements McpConfirmationStore
{
    public function __construct(private string $path) {}

    public function issue(string $id, string $binding, int $expires): void
    {
        $confirmationStream = fopen($this->path, 'c+');
        if (!flock($confirmationStream, LOCK_EX)) { throw new RuntimeException('Fixture lock failed'); }
        try {
            $records = $this->read($confirmationStream);
            if (isset($records[$id])) { throw new RuntimeException('Confirmation identity exists'); }
            $records[$id] = ['binding' => $binding, 'expires' => $expires, 'consumed' => false];
            $this->write($confirmationStream, $records);
        } finally {
            flock($confirmationStream, LOCK_UN);
            fclose($confirmationStream);
        }
    }

    public function consume(string $id, string $binding, int $expires): McpConfirmationOutcome
    {
        $confirmationStream = fopen($this->path, 'c+');
        if (!flock($confirmationStream, LOCK_EX | LOCK_NB)) {
            fclose($confirmationStream);
            return McpConfirmationOutcome::CONTENDED;
        }
        try {
            // The same exclusive lock covers read, comparison, transition and durable write across processes.
            $records = $this->read($confirmationStream);
            $record = $records[$id] ?? null;
            if ($record === null) { return McpConfirmationOutcome::ABSENT; }
            if ($record['binding'] !== $binding || $record['expires'] !== $expires) { return McpConfirmationOutcome::MISMATCHED; }
            if ($record['expires'] <= time()) { return McpConfirmationOutcome::EXPIRED; }
            if ($record['consumed']) { return McpConfirmationOutcome::ALREADY_CONSUMED; }
            $records[$id]['consumed'] = true;
            $this->write($confirmationStream, $records);
            return McpConfirmationOutcome::CONSUMED;
        } finally {
            flock($confirmationStream, LOCK_UN);
            fclose($confirmationStream);
        }
    }

    private function read(mixed $confirmationStream): array
    {
        $contents = stream_get_contents($confirmationStream);
        return $contents === '' ? [] : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }

    private function write(mixed $confirmationStream, array $records): void
    {
        $encodedRecords = json_encode($records, JSON_THROW_ON_ERROR);
        rewind($confirmationStream);
        if (fwrite($confirmationStream, $encodedRecords) !== strlen($encodedRecords) || !ftruncate($confirmationStream, strlen($encodedRecords)) || !fflush($confirmationStream) || !fsync($confirmationStream)) {
            throw new RuntimeException('Fixture persistence failed');
        }
    }
}
