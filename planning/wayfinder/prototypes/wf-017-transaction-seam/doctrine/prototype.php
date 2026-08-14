<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Fight\Common\Adapter\Repository\DoctrineUnitOfWork;
use Fight\Common\Application\Repository\UnitOfWork;
use Prototype\AuditRecord;
use Prototype\GuardedDoctrineUnitOfWork;
use Prototype\SessionRecord;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../shared.php';

$config = ORMSetup::createXMLMetadataConfiguration([__DIR__ . '/mapping'], true);
$config->enableNativeLazyObjects(true);
$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
$entityManager = new EntityManager($connection, $config);
(new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

$guarded = getenv('PROTOTYPE_DOCTRINE_GUARD') === '1';
$makeUnitOfWork = static fn (EntityManager $manager): UnitOfWork => $guarded
    ? new GuardedDoctrineUnitOfWork($manager)
    : new DoctrineUnitOfWork($manager);
$factory = static fn (): UnitOfWork => $makeUnitOfWork(new EntityManager($connection, $config));
$write = static function (string $sessionId, string $auditId) use ($entityManager): void {
    $entityManager->persist(new SessionRecord($sessionId));
    $entityManager->persist(new AuditRecord($auditId));
    $entityManager->flush();
};
$counts = static fn (): array => [
    'sessions' => (int) $connection->fetchOne('SELECT COUNT(*) FROM sessions'),
    'audits' => (int) $connection->fetchOne('SELECT COUNT(*) FROM audits'),
];

printReceipt(runTransactionProbe(
    sprintf(
        '%s Doctrine XML transaction%s',
        getenv('PROTOTYPE_FRAMEWORK') ?: 'Symfony/Slim',
        $guarded ? ' with adapter-local nesting guard' : '',
    ),
    [
        'doctrine/orm' => Composer\InstalledVersions::getPrettyVersion('doctrine/orm'),
        'doctrine/dbal' => Composer\InstalledVersions::getPrettyVersion('doctrine/dbal'),
    ],
    $makeUnitOfWork($entityManager),
    $write,
    $counts,
    $factory,
    'natural: EntityManager flushes pending changes',
));
