<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config;

define('APPPATH', __DIR__ . '/codeigniter-app/');
define('ENVIRONMENT', 'testing');

require __DIR__ . '/../wf-017-transaction-seam/codeigniter/vendor/autoload.php';
require __DIR__ . '/codeigniter-bootstrap.php';
require __DIR__ . '/shared.php';

final readonly class CodeIgniterQueryBuilderMappingRepository implements MappingUserRepository
{
    public function __construct(private BaseConnection $connection) {}

    public function save(MappingUser $user): void
    {
        $exists = $this->connection->table('mapping_users')->where('id', $user->id())->countAllResults() > 0;
        if ($exists) {
            $this->connection->table('mapping_users')->where('id', $user->id())->update(['email' => $user->email()]);
        } else {
            $this->connection->table('mapping_users')->insert(['id' => $user->id(), 'email' => $user->email()]);
        }
        $this->connection->table('mapping_user_roles')->where('user_id', $user->id())->delete();
        foreach ($user->roleIds() as $roleId) {
            $this->connection->table('mapping_user_roles')->insert(['user_id' => $user->id(), 'role_id' => $roleId]);
        }
    }

    public function get(string $id): MappingUser
    {
        $row = $this->connection->table('mapping_users')->where('id', $id)->get()->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Prototype user not found.');
        }
        $assignments = $this->connection->table('mapping_user_roles')
            ->select('role_id')->where('user_id', $id)->orderBy('role_id')->get()->getResultArray();
        $roleIds = array_map(static fn (array $assignment): string => (string) $assignment['role_id'], $assignments);

        return MappingUser::reconstitute((string) $row['id'], (string) $row['email'], $roleIds);
    }

    public function counts(): array
    {
        return [
            'users' => $this->connection->table('mapping_users')->countAllResults(),
            'roles' => $this->connection->table('mapping_roles')->countAllResults(),
            'assignments' => $this->connection->table('mapping_user_roles')->countAllResults(),
        ];
    }

    public function recordTypes(): array
    {
        return [];
    }
}

$connection = Config::connect([
    'DSN' => '', 'database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => '',
    'pConnect' => false, 'DBDebug' => true, 'charset' => 'utf8', 'DBCollat' => '',
    'swapPre' => '', 'encrypt' => false, 'compress' => false, 'strictOn' => true,
    'failover' => [], 'port' => 0, 'foreignKeys' => true, 'busyTimeout' => 1000,
], false);
$connection->query('CREATE TABLE mapping_users (id VARCHAR(80) PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL)');
$connection->query('CREATE TABLE mapping_roles (id VARCHAR(80) PRIMARY KEY)');
$connection->query('CREATE TABLE mapping_user_roles (user_id VARCHAR(80) NOT NULL, role_id VARCHAR(80) NOT NULL, PRIMARY KEY (user_id, role_id))');
foreach (['role-admin', 'role-editor', 'role-auditor'] as $roleId) {
    $connection->table('mapping_roles')->insert(['id' => $roleId]);
}

printReceipt(runMappingProbe(
    'CodeIgniter Query Builder',
    ['codeigniter4/framework' => Composer\InstalledVersions::getPrettyVersion('codeigniter4/framework')],
    new CodeIgniterQueryBuilderMappingRepository($connection),
    'explicit Query Builder rows mapped to the portable aggregate',
    'manual join-table replacement',
    'valid lower-level option, but duplicates table gateway behavior already provided by CodeIgniter Model',
));
