<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use Fight\Release\Application\Boundary\BaselineStructuralInventoryPort;
use Fight\Release\Application\Boundary\CompatibilityWorkspacePort;
use Fight\Release\Application\Boundary\StructuralInventoryPort;
use PharData;
use RuntimeException;

/**
 * Class GitBaselineStructuralInventory
 */
final readonly class GitBaselineStructuralInventory implements BaselineStructuralInventoryPort
{
    /**
     * Constructs GitBaselineStructuralInventory
     */
    public function __construct(
        private string $repository,
        private CompatibilityWorkspacePort $workspace,
        private StructuralInventoryPort $inventory
    ) {
    }

    /**
     * Exports and inventories the immutable baseline source
     *
     * @return array<string, mixed>
     */
    public function baselineStructuralInventory(string $commitOid, string $workspace): array
    {
        $baselineRoot = $workspace.'/baseline';
        $this->workspace->createDirectory($baselineRoot);
        $archive = $workspace.'/baseline.tar';
        $this->runProcess([
            '/usr/bin/git',
            '-c',
            'safe.directory='.$this->repository,
            'archive',
            '--format=tar',
            '--output='.$archive,
            $commitOid,
            'src'
        ]);
        new PharData($archive)->extractTo($baselineRoot);

        return $this->inventory->structuralInventory($baselineRoot, $commitOid);
    }

    /**
     * Runs one closed Git archive process
     *
     * @param array $command Closed Git archive argument vector.
     *
     * @phpstan-param list<string> $command
     */
    private function runProcess(array $command): void
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            $this->repository,
            ['PATH' => '/usr/local/bin:/usr/bin:/bin'],
            ['bypass_shell' => true]
        );
        assert(is_resource($process));
        stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $status === 0 || throw new RuntimeException(
            'The baseline archive process failed: '.(is_string($error) ? trim($error) : 'unknown failure')
        );
    }
}
