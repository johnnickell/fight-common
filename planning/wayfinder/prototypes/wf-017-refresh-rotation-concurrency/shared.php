<?php

declare(strict_types=1);

final readonly class PrototypeStore
{
    /**
     * @param callable(): void $begin
     * @param callable(string, list<mixed>): array<string, mixed>|null $fetch
     * @param callable(string, list<mixed>): void $execute
     * @param callable(): void $commit
     * @param callable(): void $rollback
     * @param array<string, string|null> $versions
     */
    public function __construct(
        public string $api,
        public array $versions,
        private mixed $begin,
        private mixed $fetch,
        private mixed $execute,
        private mixed $commit,
        private mixed $rollback,
    ) {}

    public function begin(): void
    {
        ($this->begin)();
    }

    /** @param list<mixed> $parameters
     *  @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $parameters = []): ?array
    {
        return ($this->fetch)($sql, $parameters);
    }

    /** @param list<mixed> $parameters */
    public function execute(string $sql, array $parameters = []): void
    {
        ($this->execute)($sql, $parameters);
    }

    public function commit(): void
    {
        ($this->commit)();
    }

    public function rollback(): void
    {
        ($this->rollback)();
    }
}

function prototypeDatabaseUrl(): string
{
    $url = getenv('PROTOTYPE_DATABASE_URL');
    if (!is_string($url) || $url === '') {
        throw new RuntimeException('PROTOTYPE_DATABASE_URL is required.');
    }

    return $url;
}

/** @return array{driver: string, host: string, port: int, database: string, user: string, password: string} */
function prototypeDatabaseConfig(): array
{
    $parts = parse_url(prototypeDatabaseUrl());
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'], $parts['path'], $parts['user'], $parts['pass'])) {
        throw new RuntimeException('Prototype database URL is invalid.');
    }

    return [
        'driver' => $parts['scheme'] === 'postgresql' ? 'pgsql' : $parts['scheme'],
        'host' => $parts['host'],
        'port' => (int) ($parts['port'] ?? ($parts['scheme'] === 'postgresql' ? 5432 : 3306)),
        'database' => ltrim($parts['path'], '/'),
        'user' => urldecode($parts['user']),
        'password' => urldecode($parts['pass']),
    ];
}

function prototypeStore(string $framework): PrototypeStore
{
    $config = prototypeDatabaseConfig();
    if ($framework === 'Symfony' || $framework === 'Slim') {
        require_once __DIR__ . '/../wf-017-transaction-seam/doctrine/vendor/autoload.php';
        $connection = Doctrine\DBAL\DriverManager::getConnection([
            'driver' => $config['driver'] === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql',
            'host' => $config['host'],
            'port' => $config['port'],
            'dbname' => $config['database'],
            'user' => $config['user'],
            'password' => $config['password'],
        ]);

        return new PrototypeStore(
            'Doctrine DBAL transaction and SELECT FOR UPDATE',
            ['doctrine/dbal' => Composer\InstalledVersions::getPrettyVersion('doctrine/dbal')],
            $connection->beginTransaction(...),
            static function (string $sql, array $parameters) use ($connection): ?array {
                $row = $connection->fetchAssociative($sql, $parameters);

                return $row === false ? null : $row;
            },
            static function (string $sql, array $parameters) use ($connection): void {
                $connection->executeStatement($sql, $parameters);
            },
            $connection->commit(...),
            $connection->rollBack(...),
        );
    }
    if ($framework === 'Laravel') {
        require_once __DIR__ . '/../wf-017-transaction-seam/laravel/vendor/autoload.php';
        $capsule = new Illuminate\Database\Capsule\Manager();
        $capsule->addConnection([
            'driver' => $config['driver'] === 'pgsql' ? 'pgsql' : 'mysql',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['user'],
            'password' => $config['password'],
            'charset' => $config['driver'] === 'pgsql' ? 'utf8' : 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $connection = $capsule->getConnection();

        return new PrototypeStore(
            'Illuminate Database transaction and SELECT FOR UPDATE',
            ['illuminate/database' => Composer\InstalledVersions::getPrettyVersion('illuminate/database')],
            $connection->beginTransaction(...),
            static function (string $sql, array $parameters) use ($connection): ?array {
                $row = $connection->selectOne($sql, $parameters);

                return $row === null ? null : (array) $row;
            },
            static function (string $sql, array $parameters) use ($connection): void {
                $connection->statement($sql, $parameters);
            },
            $connection->commit(...),
            $connection->rollBack(...),
        );
    }
    if ($framework === 'Yii') {
        require_once __DIR__ . '/../wf-017-migration-uniqueness/yii/vendor/autoload.php';
        $cache = new Yiisoft\Db\Cache\SchemaCache(new Symfony\Component\Cache\Psr16Cache(new Symfony\Component\Cache\Adapter\ArrayAdapter()));
        $connection = $config['driver'] === 'pgsql'
            ? new Yiisoft\Db\Pgsql\Connection(new Yiisoft\Db\Pgsql\Driver(new Yiisoft\Db\Pgsql\Dsn(host: $config['host'], databaseName: $config['database'], port: (string) $config['port']), $config['user'], $config['password']), $cache)
            : new Yiisoft\Db\Mysql\Connection(new Yiisoft\Db\Mysql\Driver(new Yiisoft\Db\Mysql\Dsn(host: $config['host'], databaseName: $config['database'], port: (string) $config['port']), $config['user'], $config['password']), $cache);
        $transaction = null;

        return new PrototypeStore(
            'Yii DB transaction and SELECT FOR UPDATE',
            ['yiisoft/db' => Composer\InstalledVersions::getPrettyVersion('yiisoft/db')],
            static function () use ($connection, &$transaction): void {
                $transaction = $connection->beginTransaction();
            },
            static function (string $sql, array $parameters) use ($connection): ?array {
                $row = $connection->createCommand($sql, yiiParameters($parameters))->queryOne();

                return $row === false ? null : $row;
            },
            static function (string $sql, array $parameters) use ($connection): void {
                $connection->createCommand($sql, yiiParameters($parameters))->execute();
            },
            static function () use (&$transaction): void {
                $transaction?->commit();
            },
            static function () use (&$transaction): void {
                $transaction?->rollBack();
            },
        );
    }
    if ($framework === 'CodeIgniter') {
        define('APPPATH', __DIR__ . '/../wf-017-record-mapping/codeigniter-app/');
        define('ENVIRONMENT', 'testing');
        require_once __DIR__ . '/../wf-017-transaction-seam/codeigniter/vendor/autoload.php';
        require_once __DIR__ . '/../wf-017-record-mapping/codeigniter-bootstrap.php';
        $connection = CodeIgniter\Database\Config::connect([
            'DSN' => '', 'hostname' => $config['host'], 'username' => $config['user'], 'password' => $config['password'],
            'database' => $config['database'], 'DBDriver' => $config['driver'] === 'pgsql' ? 'Postgre' : 'MySQLi',
            'DBPrefix' => '', 'pConnect' => false, 'DBDebug' => true, 'charset' => $config['driver'] === 'pgsql' ? 'utf8' : 'utf8mb4',
            'DBCollat' => $config['driver'] === 'pgsql' ? '' : 'utf8mb4_unicode_ci', 'swapPre' => '', 'encrypt' => false,
            'compress' => false, 'strictOn' => true, 'failover' => [], 'port' => $config['port'],
        ], false);

        return new PrototypeStore(
            'CodeIgniter explicit transaction and SELECT FOR UPDATE',
            ['codeigniter4/framework' => Composer\InstalledVersions::getPrettyVersion('codeigniter4/framework')],
            static function () use ($connection): void {
                if (!$connection->transBegin()) {
                    throw new RuntimeException('Could not begin CodeIgniter transaction.');
                }
            },
            static fn (string $sql, array $parameters): ?array => $connection->query($sql, $parameters)->getRowArray(),
            static function (string $sql, array $parameters) use ($connection): void {
                if ($connection->query($sql, $parameters) === false) {
                    throw new RuntimeException('CodeIgniter statement failed.');
                }
            },
            static function () use ($connection): void {
                if (!$connection->transStatus() || !$connection->transCommit()) {
                    throw new RuntimeException('CodeIgniter transaction did not commit.');
                }
            },
            static function () use ($connection): void {
                $connection->transRollback();
            },
        );
    }

    throw new RuntimeException('Unknown prototype framework.');
}

/** @param list<mixed> $parameters
 *  @return array<int, mixed>
 */
function yiiParameters(array $parameters): array
{
    return $parameters === [] ? [] : array_combine(range(1, count($parameters)), $parameters);
}

/** @return array<string, mixed> */
function rotateSession(PrototypeStore $store, int $now, int $holdMilliseconds, ?string $marker, bool $failAudit): array
{
    $started = hrtime(true);
    $store->begin();
    try {
        $session = $store->fetch(
            'SELECT id, user_id, family_id, status, rotated_at, successor_id FROM refresh_sessions WHERE token_digest = ? FOR UPDATE',
            ['digest-current'],
        );
        $lockWaitMilliseconds = (int) ((hrtime(true) - $started) / 1_000_000);
        if ($marker !== null) {
            file_put_contents($marker, 'locked');
        }
        if ($holdMilliseconds > 0) {
            usleep($holdMilliseconds * 1_000);
        }
        if ($session === null) {
            $store->commit();

            return ['decision' => 'invalid', 'lock_wait_ms' => $lockWaitMilliseconds];
        }
        if ($session['status'] === 'ACTIVE') {
            $store->execute(
                'INSERT INTO refresh_sessions (id, user_id, family_id, token_digest, status, rotated_at, successor_id) VALUES (?, ?, ?, ?, ?, NULL, NULL)',
                ['session-successor', $session['user_id'], $session['family_id'], 'digest-successor', 'ACTIVE'],
            );
            $store->execute(
                'UPDATE refresh_sessions SET status = ?, rotated_at = ?, successor_id = ? WHERE id = ?',
                ['ROTATED', $now, 'session-successor', $session['id']],
            );
            if ($failAudit) {
                throw new RuntimeException('forced audit failure');
            }
            $store->execute(
                'INSERT INTO refresh_audits (id, family_id, action) VALUES (?, ?, ?)',
                ['audit-rotation', $session['family_id'], 'REFRESH_ROTATED'],
            );
            $store->commit();

            return ['decision' => 'rotated', 'lock_wait_ms' => $lockWaitMilliseconds];
        }
        if ($session['status'] === 'ROTATED' && $now - (int) $session['rotated_at'] <= 5) {
            $store->commit();

            return ['decision' => 'bounded_conflict', 'lock_wait_ms' => $lockWaitMilliseconds];
        }
        $store->execute('UPDATE refresh_sessions SET status = ? WHERE family_id = ? AND status = ?', ['REVOKED', $session['family_id'], 'ACTIVE']);
        $store->execute(
            'INSERT INTO refresh_audits (id, family_id, action) VALUES (?, ?, ?)',
            ['audit-reuse', $session['family_id'], 'REFRESH_REUSE_DETECTED'],
        );
        $store->commit();

        return ['decision' => 'reuse_detected', 'lock_wait_ms' => $lockWaitMilliseconds];
    } catch (Throwable $throwable) {
        $store->rollback();
        throw $throwable;
    }
}
