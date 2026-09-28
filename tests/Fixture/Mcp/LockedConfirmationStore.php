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
        $file = fopen($this->path, 'c+');
        if (!flock($file, LOCK_EX)) { throw new RuntimeException('Fixture lock failed'); }
        try {
            $records = $this->read($file);
            if (isset($records[$id])) { throw new RuntimeException('Confirmation identity exists'); }
            $records[$id] = ['binding' => $binding, 'expires' => $expires, 'consumed' => false];
            $this->write($file, $records);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    public function consume(string $id, string $binding, int $expires): McpConfirmationOutcome
    {
        $file = fopen($this->path, 'c+');
        if (!flock($file, LOCK_EX | LOCK_NB)) {
            fclose($file);
            return McpConfirmationOutcome::CONTENDED;
        }
        try {
            // The same exclusive lock covers read, comparison, transition and durable write across processes.
            $records = $this->read($file);
            $record = $records[$id] ?? null;
            if ($record === null) { return McpConfirmationOutcome::ABSENT; }
            if ($record['binding'] !== $binding || $record['expires'] !== $expires) { return McpConfirmationOutcome::MISMATCHED; }
            if ($record['expires'] <= time()) { return McpConfirmationOutcome::EXPIRED; }
            if ($record['consumed']) { return McpConfirmationOutcome::ALREADY_CONSUMED; }
            $records[$id]['consumed'] = true;
            $this->write($file, $records);
            return McpConfirmationOutcome::CONSUMED;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function read(mixed $file): array
    {
        $contents = stream_get_contents($file);
        return $contents === '' ? [] : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }

    private function write(mixed $file, array $records): void
    {
        $data = json_encode($records, JSON_THROW_ON_ERROR);
        rewind($file);
        if (fwrite($file, $data) !== strlen($data) || !ftruncate($file, strlen($data)) || !fflush($file) || !fsync($file)) {
            throw new RuntimeException('Fixture persistence failed');
        }
    }
}
