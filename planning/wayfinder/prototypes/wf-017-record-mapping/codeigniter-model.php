<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config;
use CodeIgniter\Model;

define('APPPATH', __DIR__ . '/codeigniter-app/');
define('ENVIRONMENT', 'testing');

require __DIR__ . '/../wf-017-transaction-seam/codeigniter/vendor/autoload.php';
require __DIR__ . '/codeigniter-bootstrap.php';
require __DIR__ . '/shared.php';

final class CiUserModel extends Model
{
    protected $table = 'mapping_users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $allowedFields = ['id', 'email'];
}

final class CiRoleModel extends Model
{
    protected $table = 'mapping_roles';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $allowedFields = ['id'];
}

final class CiUserRoleModel extends Model
{
    protected $table = 'mapping_user_roles';
    protected $primaryKey = 'user_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $allowedFields = ['user_id', 'role_id'];
}

final readonly class CodeIgniterModelMappingRepository implements MappingUserRepository
{
    private CiUserModel $users;
    private CiUserRoleModel $assignments;

    public function __construct(private BaseConnection $connection)
    {
        $this->users = new CiUserModel($connection);
        $this->assignments = new CiUserRoleModel($connection);
    }

    public function save(MappingUser $user): void
    {
        $this->users->save(['id' => $user->id(), 'email' => $user->email()]);
        $this->assignments->where('user_id', $user->id())->delete();
        foreach ($user->roleIds() as $roleId) {
            $this->assignments->insert(['user_id' => $user->id(), 'role_id' => $roleId]);
        }
    }

    public function get(string $id): MappingUser
    {
        $row = $this->users->find($id);
        if (!is_array($row)) {
            throw new RuntimeException('Prototype user not found.');
        }
        $assignments = $this->assignments->where('user_id', $id)->orderBy('role_id')->findAll();
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
        return [CiUserModel::class, CiRoleModel::class, CiUserRoleModel::class];
    }
}

$connection = createCodeIgniterConnection();
createCodeIgniterSchema($connection);
$roles = new CiRoleModel($connection);
foreach (['role-admin', 'role-editor', 'role-auditor'] as $roleId) {
    $roles->insert(['id' => $roleId]);
}

printReceipt(runMappingProbe(
    'CodeIgniter Model gateways',
    ['codeigniter4/framework' => Composer\InstalledVersions::getPrettyVersion('codeigniter4/framework')],
    new CodeIgniterModelMappingRepository($connection),
    'table-focused CodeIgniter Models return row arrays that the repository maps to the aggregate',
    'separate Model for the join table; relationship composition remains repository-owned',
    'selected: CodeIgniter documents Model as its ordinary table gateway and it keeps Query Builder available for joins',
));

function createCodeIgniterConnection(): BaseConnection
{
    return Config::connect([
        'DSN' => '', 'database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => '',
        'pConnect' => false, 'DBDebug' => true, 'charset' => 'utf8', 'DBCollat' => '',
        'swapPre' => '', 'encrypt' => false, 'compress' => false, 'strictOn' => true,
        'failover' => [], 'port' => 0, 'foreignKeys' => true, 'busyTimeout' => 1000,
    ], false);
}

function createCodeIgniterSchema(BaseConnection $connection): void
{
    $connection->query('CREATE TABLE mapping_users (id VARCHAR(80) PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL)');
    $connection->query('CREATE TABLE mapping_roles (id VARCHAR(80) PRIMARY KEY)');
    $connection->query('CREATE TABLE mapping_user_roles (user_id VARCHAR(80) NOT NULL, role_id VARCHAR(80) NOT NULL, PRIMARY KEY (user_id, role_id))');
}
