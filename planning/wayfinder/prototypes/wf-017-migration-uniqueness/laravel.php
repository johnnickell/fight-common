<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;

require __DIR__ . '/../wf-017-transaction-seam/laravel/vendor/autoload.php';
require __DIR__ . '/shared.php';

$config = databaseConfig(databaseUrl());
$capsule = new Manager();
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
$capsule->setAsGlobal();

$migrate = static function () use ($capsule): void {
    $schema = $capsule->getConnection()->getSchemaBuilder();
    $schema->create('users', static function (Blueprint $table): void {
        $table->string('id', 36);
        $table->string('canonical_email', 320);
        $table->string('account_state', 32);
        $table->primary('id', 'pk_users');
        $table->unique('canonical_email', 'uniq_users_canonical_email');
    });
    $schema->create('roles', static function (Blueprint $table): void {
        $table->string('id', 36);
        $table->string('name', 120);
        $table->primary('id', 'pk_roles');
    });
    $schema->create('user_roles', static function (Blueprint $table): void {
        $table->string('user_id', 36);
        $table->string('role_id', 36);
        $table->primary(['user_id', 'role_id'], 'pk_user_roles');
        $table->foreign('user_id', 'fk_user_roles_user')->references('id')->on('users')->cascadeOnDelete();
        $table->foreign('role_id', 'fk_user_roles_role')->references('id')->on('roles')->cascadeOnDelete();
    });
};

runMigrationProbe(
    'Laravel',
    'Laravel Schema Builder migration',
    ['illuminate/database' => Composer\InstalledVersions::getPrettyVersion('illuminate/database')],
    $migrate,
);
