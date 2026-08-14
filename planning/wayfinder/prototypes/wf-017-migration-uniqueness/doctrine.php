<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

require __DIR__ . '/../wf-017-transaction-seam/doctrine/vendor/autoload.php';
require __DIR__ . '/shared.php';

$framework = getenv('PROTOTYPE_FRAMEWORK');
if (!is_string($framework) || $framework === '') {
    throw new RuntimeException('PROTOTYPE_FRAMEWORK is required.');
}

$config = databaseConfig(databaseUrl());
$connection = DriverManager::getConnection([
    'driver' => $config['driver'] === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql',
    'host' => $config['host'],
    'port' => $config['port'],
    'dbname' => $config['database'],
    'user' => $config['user'],
    'password' => $config['password'],
]);
$migrate = static function () use ($connection): void {
    $schema = new Schema();

    $users = $schema->createTable('users');
    $users->addColumn('id', Types::STRING, ['length' => 36]);
    $users->addColumn('canonical_email', Types::STRING, ['length' => 320]);
    $users->addColumn('account_state', Types::STRING, ['length' => 32]);
    $users->setPrimaryKey(['id'], 'pk_users');
    $users->addUniqueIndex(['canonical_email'], 'uniq_users_canonical_email');

    $roles = $schema->createTable('roles');
    $roles->addColumn('id', Types::STRING, ['length' => 36]);
    $roles->addColumn('name', Types::STRING, ['length' => 120]);
    $roles->setPrimaryKey(['id'], 'pk_roles');

    $assignments = $schema->createTable('user_roles');
    $assignments->addColumn('user_id', Types::STRING, ['length' => 36]);
    $assignments->addColumn('role_id', Types::STRING, ['length' => 36]);
    $assignments->setPrimaryKey(['user_id', 'role_id'], 'pk_user_roles');
    $assignments->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_roles_user');
    $assignments->addForeignKeyConstraint('roles', ['role_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_roles_role');

    foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
        $connection->executeStatement($sql);
    }
};

runMigrationProbe(
    $framework,
    $framework === 'Symfony' ? 'Doctrine DBAL schema through DoctrineMigrationsBundle composition' : 'Doctrine DBAL schema through Doctrine Migrations CLI composition',
    ['doctrine/dbal' => Composer\InstalledVersions::getPrettyVersion('doctrine/dbal')],
    $migrate,
);
