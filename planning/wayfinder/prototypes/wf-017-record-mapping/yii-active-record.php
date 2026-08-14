<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

require __DIR__ . '/yii-active-record/vendor/autoload.php';
require __DIR__ . '/shared.php';

final class YiiUserRecord extends ActiveRecord
{
    public string $id;
    public string $email;

    public function tableName(): string
    {
        return 'mapping_users';
    }
}

final class YiiRoleRecord extends ActiveRecord
{
    public string $id;

    public function tableName(): string
    {
        return 'mapping_roles';
    }
}

final class YiiUserRoleRecord extends ActiveRecord
{
    public string $user_id;
    public string $role_id;

    public function tableName(): string
    {
        return 'mapping_user_roles';
    }
}

final readonly class YiiActiveRecordMappingRepository implements MappingUserRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(MappingUser $user): void
    {
        $record = YiiUserRecord::query()->findByPk($user->id());
        if (!$record instanceof YiiUserRecord) {
            $record = new YiiUserRecord();
            $record->id = $user->id();
        }
        $record->email = $user->email();
        $record->save();

        (new YiiUserRoleRecord())->deleteAll(['user_id' => $user->id()]);
        foreach ($user->roleIds() as $roleId) {
            $assignment = new YiiUserRoleRecord();
            $assignment->user_id = $user->id();
            $assignment->role_id = $roleId;
            $assignment->save();
        }
    }

    public function get(string $id): MappingUser
    {
        $record = YiiUserRecord::query()->findByPk($id);
        if (!$record instanceof YiiUserRecord) {
            throw new RuntimeException('Prototype user not found.');
        }
        $assignments = YiiUserRoleRecord::query()->where(['user_id' => $id])->orderBy('role_id')->all();
        $roleIds = array_map(
            static fn (YiiUserRoleRecord $assignment): string => $assignment->role_id,
            $assignments,
        );

        return MappingUser::reconstitute($record->id, $record->email, $roleIds);
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
        return [YiiUserRecord::class, YiiRoleRecord::class, YiiUserRoleRecord::class];
    }
}

$connection = new Connection(
    new Driver(new Dsn(databaseName: 'memory')),
    new SchemaCache(new Psr16Cache(new ArrayAdapter())),
);
ConnectionProvider::set($connection);
$connection->createCommand('CREATE TABLE mapping_users (id VARCHAR(80) PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL)')->execute();
$connection->createCommand('CREATE TABLE mapping_roles (id VARCHAR(80) PRIMARY KEY)')->execute();
$connection->createCommand('CREATE TABLE mapping_user_roles (user_id VARCHAR(80) NOT NULL, role_id VARCHAR(80) NOT NULL, PRIMARY KEY (user_id, role_id))')->execute();
foreach (['role-admin', 'role-editor', 'role-auditor'] as $roleId) {
    $role = new YiiRoleRecord();
    $role->id = $roleId;
    $role->save();
}

printReceipt(runMappingProbe(
    'Yii Active Record adapter records',
    [
        'yiisoft/active-record' => Composer\InstalledVersions::getPrettyVersion('yiisoft/active-record'),
        'yiisoft/db' => Composer\InstalledVersions::getPrettyVersion('yiisoft/db'),
    ],
    new YiiActiveRecordMappingRepository($connection),
    'adapter-owned Yii Active Record rows mapped explicitly to the portable aggregate',
    'Active Record identity and row state plus explicit join-record replacement',
    'selected: stable native package removes repetitive row-state mechanics while keeping records outside Application',
));
