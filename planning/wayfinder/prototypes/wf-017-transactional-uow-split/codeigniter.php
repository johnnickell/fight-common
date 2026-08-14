<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config;
use Prototype\Contract\TransactionalUnitOfWork;

define('APPPATH', __DIR__ . '/../wf-017-transaction-seam/codeigniter/app/');
define('ENVIRONMENT', 'testing');

require __DIR__ . '/../wf-017-transaction-seam/codeigniter/vendor/autoload.php';
require __DIR__ . '/contracts.php';
require __DIR__ . '/shared.php';

final class CandidateCodeIgniterUnitOfWork implements TransactionalUnitOfWork
{
    private bool $active = false;

    public function __construct(private readonly BaseConnection $connection) {}

    public function commitTransactional(callable $operation): mixed
    {
        if ($this->active) {
            throw new LogicException('Nested UnitOfWork transactions are not supported.');
        }

        $this->active = true;
        if (!$this->connection->transBegin()) {
            $this->active = false;
            throw new RuntimeException('CodeIgniter could not begin transaction.');
        }

        try {
            $result = $operation();
            if (!$this->connection->transStatus() || !$this->connection->transCommit()) {
                throw new RuntimeException('CodeIgniter transaction did not commit.');
            }

            return $result;
        } catch (Throwable $throwable) {
            $this->connection->transRollback();
            throw $throwable;
        } finally {
            $this->active = false;
        }
    }

    public function isClosed(): bool
    {
        return false;
    }
}

$connection = Config::connect([
    'DSN' => '',
    'database' => ':memory:',
    'DBDriver' => 'SQLite3',
    'DBPrefix' => '',
    'pConnect' => false,
    'DBDebug' => true,
    'charset' => 'utf8',
    'DBCollat' => '',
    'swapPre' => '',
    'encrypt' => false,
    'compress' => false,
    'strictOn' => true,
    'failover' => [],
    'port' => 0,
    'foreignKeys' => true,
    'busyTimeout' => 1000,
], false);
$connection->query('CREATE TABLE sessions (id VARCHAR(80) PRIMARY KEY)');
$connection->query('CREATE TABLE audits (id VARCHAR(80) PRIMARY KEY)');
$counts = static fn (): array => [
    'sessions' => (int) $connection->query('SELECT COUNT(*) AS aggregate FROM sessions')->getRowArray()['aggregate'],
    'audits' => (int) $connection->query('SELECT COUNT(*) AS aggregate FROM audits')->getRowArray()['aggregate'],
];

writeReceipt(runContractSplitProbe(
    'CodeIgniter native transaction-only adapter',
    ['codeigniter4/framework' => Composer\InstalledVersions::getPrettyVersion('codeigniter4/framework')],
    new CandidateCodeIgniterUnitOfWork($connection),
    static function (string $sessionId, string $auditId) use ($connection): void {
        $connection->query('INSERT INTO sessions (id) VALUES (?)', [$sessionId]);
        $connection->query('INSERT INTO audits (id) VALUES (?)', [$auditId]);
    },
    $counts,
    false,
));
