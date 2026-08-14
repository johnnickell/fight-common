<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql;
use Yiisoft\Db\Pgsql;

require __DIR__ . '/yii/vendor/autoload.php';
require __DIR__ . '/shared.php';

$config = databaseConfig(databaseUrl());
$schemaCache = new SchemaCache(new Psr16Cache(new ArrayAdapter()));
if ($config['driver'] === 'pgsql') {
    $connection = new Pgsql\Connection(
        new Pgsql\Driver(new Pgsql\Dsn(host: $config['host'], databaseName: $config['database'], port: (string) $config['port']), $config['user'], $config['password']),
        $schemaCache,
    );
} else {
    $connection = new Mysql\Connection(
        new Mysql\Driver(new Mysql\Dsn(host: $config['host'], databaseName: $config['database'], port: (string) $config['port']), $config['user'], $config['password']),
        $schemaCache,
    );
}

$migrate = static function () use ($connection, $config): void {
    assert($connection instanceof ConnectionInterface);
    $options = $config['driver'] === 'mysql' ? 'ENGINE=InnoDB' : null;
    $connection->createCommand()->createTable('users', [
        'id' => 'varchar(36) NOT NULL PRIMARY KEY',
        'canonical_email' => 'varchar(320) NOT NULL',
        'account_state' => 'varchar(32) NOT NULL',
    ], $options)->execute();
    $connection->createCommand()->createIndex('users', 'uniq_users_canonical_email', 'canonical_email', 'UNIQUE')->execute();
    $connection->createCommand()->createTable('roles', [
        'id' => 'varchar(36) NOT NULL PRIMARY KEY',
        'name' => 'varchar(120) NOT NULL',
    ], $options)->execute();
    $connection->createCommand()->createTable('user_roles', [
        'user_id' => 'varchar(36) NOT NULL',
        'role_id' => 'varchar(36) NOT NULL',
    ], $options)->execute();
    $connection->createCommand()->addPrimaryKey('user_roles', 'pk_user_roles', ['user_id', 'role_id'])->execute();
    $connection->createCommand()->addForeignKey('user_roles', 'fk_user_roles_user', 'user_id', 'users', 'id', 'CASCADE')->execute();
    $connection->createCommand()->addForeignKey('user_roles', 'fk_user_roles_role', 'role_id', 'roles', 'id', 'CASCADE')->execute();
};

runMigrationProbe(
    'Yii',
    'Yii DB DDL command migration',
    [
        'yiisoft/db' => Composer\InstalledVersions::getPrettyVersion('yiisoft/db'),
        $config['driver'] === 'pgsql' ? 'yiisoft/db-pgsql' : 'yiisoft/db-mysql' => Composer\InstalledVersions::getPrettyVersion(
            $config['driver'] === 'pgsql' ? 'yiisoft/db-pgsql' : 'yiisoft/db-mysql',
        ),
    ],
    $migrate,
);
