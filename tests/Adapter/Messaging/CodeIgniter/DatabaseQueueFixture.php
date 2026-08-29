<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\CodeIgniter;

use CodeIgniter\Config\Factories;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Handlers\DatabaseHandler;

final readonly class DatabaseQueueFixture
{
    private function __construct(
        public DatabaseHandler $queue,
        private BaseConnection $database,
    ) {
    }

    /** @param array<string, class-string> $jobHandlers */
    public static function boot(array $jobHandlers): self
    {
        \Illuminate\Container\Container::getInstance()->instance('config', new class {
            public function get(mixed $key, mixed $default = null): mixed
            {
                return is_string($key)
                    ? Factories::get('config', $key) ?? $default
                    : $default;
            }
        });

        require dirname(__DIR__, 4).'/vendor/codeigniter4/framework/system/Test/bootstrap.php';

        $config = new class extends QueueConfig {
            /** @var array{dbGroup: string, getShared: bool, skipLocked: bool} */
            public array $database = ['dbGroup' => 'tests', 'getShared' => true, 'skipLocked' => true];
            /** @var array<string, class-string> */
            public array $jobHandlers = [];
        };
        $config->jobHandlers = $jobHandlers;
        Factories::injectMock('config', 'Queue', $config);

        $database = \Config\Database::connect('tests');
        self::createQueueTables($database);

        return new self(new DatabaseHandler($config), $database);
    }

    public function close(): void
    {
        $prefix = $this->database->getPrefix();
        $this->database->query('DROP TABLE IF EXISTS '.$prefix.'queue_jobs_failed');
        $this->database->query('DROP TABLE IF EXISTS '.$prefix.'queue_jobs');
        Factories::reset('config');
    }

    private static function createQueueTables(BaseConnection $database): void
    {
        $prefix = $database->getPrefix();
        $database->query('CREATE TABLE '.$prefix.'queue_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue VARCHAR(64) NOT NULL, payload TEXT NOT NULL, priority VARCHAR(64) NOT NULL, status INTEGER NOT NULL DEFAULT 0, attempts INTEGER NOT NULL DEFAULT 0, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL)');
        $database->query('CREATE TABLE '.$prefix.'queue_jobs_failed (id INTEGER PRIMARY KEY AUTOINCREMENT, connection VARCHAR(64) NOT NULL, queue VARCHAR(64) NOT NULL, payload TEXT NOT NULL, priority VARCHAR(64) NOT NULL, exception TEXT NOT NULL, failed_at INTEGER NOT NULL)');
    }
}
