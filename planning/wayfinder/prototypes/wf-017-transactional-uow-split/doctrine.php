<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Prototype\AuditRecord;
use Prototype\Contract\UnitOfWork;
use Prototype\SessionRecord;

$transactionPrototype = __DIR__ . '/../wf-017-transaction-seam/doctrine';
require $transactionPrototype . '/vendor/autoload.php';
require __DIR__ . '/contracts.php';
require __DIR__ . '/shared.php';

final readonly class CandidateDoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function commit(): void
    {
        $this->entityManager->flush();
    }

    public function commitTransactional(callable $operation): mixed
    {
        if ($this->entityManager->getConnection()->getTransactionNestingLevel() > 0) {
            throw new LogicException('Nested UnitOfWork transactions are not supported.');
        }

        return $this->entityManager->wrapInTransaction($operation);
    }

    public function isClosed(): bool
    {
        return !$this->entityManager->isOpen();
    }
}

$config = ORMSetup::createXMLMetadataConfiguration([$transactionPrototype . '/mapping'], true);
$config->enableNativeLazyObjects(true);
$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
$entityManager = new EntityManager($connection, $config);
(new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
$unitOfWork = new CandidateDoctrineUnitOfWork($entityManager);

$write = static function (string $sessionId, string $auditId) use ($entityManager): void {
    $entityManager->persist(new SessionRecord($sessionId));
    $entityManager->persist(new AuditRecord($auditId));
    $entityManager->flush();
};
$counts = static fn (): array => [
    'sessions' => (int) $connection->fetchOne('SELECT COUNT(*) FROM sessions'),
    'audits' => (int) $connection->fetchOne('SELECT COUNT(*) FROM audits'),
];
$legacyProof = static function (UnitOfWork $legacy) use ($entityManager, $counts): bool {
    $entityManager->persist(new SessionRecord('session-legacy-pending'));
    runLegacyCommit($legacy);

    return $counts() === ['sessions' => 2, 'audits' => 1];
};

writeReceipt(runContractSplitProbe(
    sprintf('%s Doctrine XML candidate legacy adapter', getenv('PROTOTYPE_FRAMEWORK') ?: 'Symfony/Slim'),
    [
        'doctrine/orm' => Composer\InstalledVersions::getPrettyVersion('doctrine/orm'),
        'doctrine/dbal' => Composer\InstalledVersions::getPrettyVersion('doctrine/dbal'),
    ],
    $unitOfWork,
    $write,
    $counts,
    true,
    $legacyProof,
));
