<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

require __DIR__ . '/../wf-017-transaction-seam/laravel/vendor/autoload.php';
require __DIR__ . '/shared.php';

final class LaravelUserRecord extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $table = 'mapping_users';
    protected $keyType = 'string';
    protected $guarded = [];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            LaravelRoleRecord::class,
            'mapping_user_roles',
            'user_id',
            'role_id',
        );
    }
}

final class LaravelRoleRecord extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $table = 'mapping_roles';
    protected $keyType = 'string';
    protected $guarded = [];
}

final readonly class LaravelMappingRepository implements MappingUserRepository
{
    public function save(MappingUser $user): void
    {
        $record = LaravelUserRecord::query()->updateOrCreate(
            ['id' => $user->id()],
            ['email' => $user->email()],
        );
        $record->roles()->sync($user->roleIds());
    }

    public function get(string $id): MappingUser
    {
        $record = LaravelUserRecord::query()->with('roles')->findOrFail($id);
        $roleIds = $record->roles->map(static fn (LaravelRoleRecord $role): string => (string) $role->getKey())->all();

        return MappingUser::reconstitute((string) $record->getKey(), (string) $record->email, $roleIds);
    }

    public function counts(): array
    {
        $connection = LaravelUserRecord::query()->getConnection();

        return [
            'users' => $connection->table('mapping_users')->count(),
            'roles' => $connection->table('mapping_roles')->count(),
            'assignments' => $connection->table('mapping_user_roles')->count(),
        ];
    }

    public function recordTypes(): array
    {
        return [LaravelUserRecord::class, LaravelRoleRecord::class];
    }
}

$capsule = new Manager();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('mapping_users', static function ($table): void {
    $table->string('id')->primary();
    $table->string('email')->unique();
});
$schema->create('mapping_roles', static function ($table): void {
    $table->string('id')->primary();
});
$schema->create('mapping_user_roles', static function ($table): void {
    $table->string('user_id');
    $table->string('role_id');
    $table->primary(['user_id', 'role_id']);
});
foreach (['role-admin', 'role-editor', 'role-auditor'] as $roleId) {
    LaravelRoleRecord::query()->create(['id' => $roleId]);
}

printReceipt(runMappingProbe(
    'Laravel Eloquent records',
    ['illuminate/database' => Composer\InstalledVersions::getPrettyVersion('illuminate/database')],
    new LaravelMappingRepository(),
    'adapter-owned Eloquent records mapped explicitly to the portable aggregate',
    'Eloquent belongsToMany with sync() for exact membership',
    'selected: native relationship and identity APIs fit without making the aggregate an Eloquent model',
));
