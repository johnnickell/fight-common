<?php

declare(strict_types=1);

use CodeIgniter\Database\Config;

define('APPPATH', __DIR__ . '/../wf-017-record-mapping/codeigniter-app/');
define('ENVIRONMENT', 'testing');

require __DIR__ . '/../wf-017-transaction-seam/codeigniter/vendor/autoload.php';
require __DIR__ . '/../wf-017-record-mapping/codeigniter-bootstrap.php';
require __DIR__ . '/shared.php';

$config = databaseConfig(databaseUrl());
$database = [
    'DSN' => '',
    'hostname' => $config['host'],
    'username' => $config['user'],
    'password' => $config['password'],
    'database' => $config['database'],
    'DBDriver' => $config['driver'] === 'pgsql' ? 'Postgre' : 'MySQLi',
    'DBPrefix' => '',
    'pConnect' => false,
    'DBDebug' => true,
    'charset' => $config['driver'] === 'pgsql' ? 'utf8' : 'utf8mb4',
    'DBCollat' => $config['driver'] === 'pgsql' ? '' : 'utf8mb4_unicode_ci',
    'swapPre' => '',
    'encrypt' => false,
    'compress' => false,
    'strictOn' => true,
    'failover' => [],
    'port' => $config['port'],
];
$connection = Config::connect($database, false);
$forge = Config::forge($connection);

$migrate = static function () use ($forge): void {
    $forge->addField([
        'id' => ['type' => 'VARCHAR', 'constraint' => 36],
        'canonical_email' => ['type' => 'VARCHAR', 'constraint' => 320],
        'account_state' => ['type' => 'VARCHAR', 'constraint' => 32],
    ]);
    $forge->addPrimaryKey('id', 'pk_users');
    $forge->addUniqueKey('canonical_email', 'uniq_users_canonical_email');
    $forge->createTable('users');

    $forge->addField([
        'id' => ['type' => 'VARCHAR', 'constraint' => 36],
        'name' => ['type' => 'VARCHAR', 'constraint' => 120],
    ]);
    $forge->addPrimaryKey('id', 'pk_roles');
    $forge->createTable('roles');

    $forge->addField([
        'user_id' => ['type' => 'VARCHAR', 'constraint' => 36],
        'role_id' => ['type' => 'VARCHAR', 'constraint' => 36],
    ]);
    $forge->addPrimaryKey(['user_id', 'role_id'], 'pk_user_roles');
    $forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE', 'fk_user_roles_user');
    $forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE', 'fk_user_roles_role');
    $forge->createTable('user_roles');
};

runMigrationProbe(
    'CodeIgniter',
    'CodeIgniter Forge migration',
    ['codeigniter4/framework' => Composer\InstalledVersions::getPrettyVersion('codeigniter4/framework')],
    $migrate,
);
