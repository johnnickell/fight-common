<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

require __DIR__ . '/../wf-017-transaction-seam/yii/vendor/autoload.php';
require __DIR__ . '/shared.php';

final readonly class YiiDbMappingRepository implements MappingUserRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(MappingUser $user): void
    {
        $exists = (int) $this->connection->createCommand(
            'SELECT COUNT(*) FROM mapping_users WHERE id = :id',
            [':id' => $user->id()],
        )->queryScalar() > 0;
        if ($exists) {
            $this->connection->createCommand()->update(
                'mapping_users',
                ['email' => $user->email()],
                ['id' => $user->id()],
            )->execute();
        } else {
            $this->connection->createCommand()->insert(
                'mapping_users',
                ['id' => $user->id(), 'email' => $user->email()],
            )->execute();
        }

        $this->connection->createCommand()->delete('mapping_user_roles', ['user_id' => $user->id()])->execute();
        foreach ($user->roleIds() as $roleId) {
            $this->connection->createCommand()->insert(
                'mapping_user_roles',
                ['user_id' => $user->id(), 'role_id' => $roleId],
            )->execute();
        }
    }

    public function get(string $id): MappingUser
    {
        $row = $this->connection->createCommand(
            'SELECT id, email FROM mapping_users WHERE id = :id',
            [':id' => $id],
        )->queryOne();
        if (!is_array($row)) {
            throw new RuntimeException('Prototype user not found.');
        }
        $roleIds = $this->connection->createCommand(
            'SELECT role_id FROM mapping_user_roles WHERE user_id = :id ORDER BY role_id',
            [':id' => $id],
        )->queryColumn();

        return MappingUser::reconstitute((string) $row['id'], (string) $row['email'], array_map('strval', $roleIds));
    }

    public function counts(): array
    {
        return [
            'users' => (int) $this->connection->createCommand('SELECT COUNT(*) FROM mapping_users')->queryScalar(),
            'roles' => (int) $this->connection->createCommand('SELECT COUNT(*) FROM mapping_roles')->queryScalar(),
            'assignments' => (int) $this->connection->createCommand('SELECT COUNT(*) FROM mapping_user_roles')->queryScalar(),
        ];
    }

    public function recordTypes(): array
    {
        return [];
    }
}

$connection = new Connection(
    new Driver(new Dsn(databaseName: 'memory')),
    new SchemaCache(new Psr16Cache(new ArrayAdapter())),
);
createYiiSchema($connection);

printReceipt(runMappingProbe(
    'Yii DB commands',
    ['yiisoft/db' => Composer\InstalledVersions::getPrettyVersion('yiisoft/db')],
    new YiiDbMappingRepository($connection),
    'explicit row arrays and SQL commands mapped to the portable aggregate',
    'manual join-table replacement',
    'valid fallback, but repeats identity, row-state, and relationship mechanics supplied by Yii Active Record',
));

function createYiiSchema(ConnectionInterface $connection): void
{
    $connection->createCommand('CREATE TABLE mapping_users (id VARCHAR(80) PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL)')->execute();
    $connection->createCommand('CREATE TABLE mapping_roles (id VARCHAR(80) PRIMARY KEY)')->execute();
    $connection->createCommand('CREATE TABLE mapping_user_roles (user_id VARCHAR(80) NOT NULL, role_id VARCHAR(80) NOT NULL, PRIMARY KEY (user_id, role_id))')->execute();
    foreach (['role-admin', 'role-editor', 'role-auditor'] as $roleId) {
        $connection->createCommand()->insert('mapping_roles', ['id' => $roleId])->execute();
    }
}
