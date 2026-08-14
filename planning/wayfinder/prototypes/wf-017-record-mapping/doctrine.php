<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PrototypeRecordMapping\Doctrine\RoleRecord;
use PrototypeRecordMapping\Doctrine\UserRecord;

require __DIR__ . '/../wf-017-transaction-seam/doctrine/vendor/autoload.php';
require __DIR__ . '/shared.php';
require __DIR__ . '/doctrine-records.php';

final readonly class DoctrineMappingRepository implements MappingUserRepository
{
    public function __construct(private EntityManager $entityManager) {}

    public function save(MappingUser $user): void
    {
        $record = $this->entityManager->find(UserRecord::class, $user->id());
        if (!$record instanceof UserRecord) {
            $record = new UserRecord($user->id(), $user->email());
            $this->entityManager->persist($record);
        } else {
            $record->revise($user->email());
        }

        $roles = array_map(function (string $roleId): RoleRecord {
            $role = $this->entityManager->find(RoleRecord::class, $roleId);
            if (!$role instanceof RoleRecord) {
                throw new RuntimeException('Unknown prototype role: ' . $roleId);
            }

            return $role;
        }, $user->roleIds());
        $record->replaceRoles($roles);
        $this->entityManager->flush();
    }

    public function get(string $id): MappingUser
    {
        $record = $this->entityManager->find(UserRecord::class, $id);
        if (!$record instanceof UserRecord) {
            throw new RuntimeException('Prototype user not found.');
        }

        return MappingUser::reconstitute($record->id(), $record->email(), $record->roleIds());
    }

    public function counts(): array
    {
        $connection = $this->entityManager->getConnection();

        return [
            'users' => (int) $connection->fetchOne('SELECT COUNT(*) FROM mapping_users'),
            'roles' => (int) $connection->fetchOne('SELECT COUNT(*) FROM mapping_roles'),
            'assignments' => (int) $connection->fetchOne('SELECT COUNT(*) FROM mapping_user_roles'),
        ];
    }

    public function recordTypes(): array
    {
        return [UserRecord::class, RoleRecord::class];
    }
}

$config = ORMSetup::createXMLMetadataConfiguration([__DIR__ . '/doctrine-mapping'], true);
$config->enableNativeLazyObjects(true);
$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
$entityManager = new EntityManager($connection, $config);
(new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
foreach (['role-admin', 'role-editor', 'role-auditor'] as $roleId) {
    $entityManager->persist(new RoleRecord($roleId));
}
$entityManager->flush();

printReceipt(runMappingProbe(
    (getenv('PROTOTYPE_FRAMEWORK') ?: 'Symfony/Slim') . ' Doctrine XML records',
    [
        'doctrine/orm' => Composer\InstalledVersions::getPrettyVersion('doctrine/orm'),
        'doctrine/dbal' => Composer\InstalledVersions::getPrettyVersion('doctrine/dbal'),
    ],
    new DoctrineMappingRepository($entityManager),
    'adapter-owned Doctrine records mapped explicitly to the portable aggregate',
    'Doctrine many-to-many collection and join table',
    'baseline: natural for the established Symfony and Slim composition',
));
