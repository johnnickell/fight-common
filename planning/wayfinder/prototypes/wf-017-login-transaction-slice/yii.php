<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Prototype\LoginTransactionSlice\LoginOutcome;
use Prototype\LoginTransactionSlice\NativeTransactionalUnitOfWork;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

use function Prototype\LoginTransactionSlice\handlerFactory;
use function Prototype\LoginTransactionSlice\responseParts;
use function Prototype\LoginTransactionSlice\runLoginProbe;

require __DIR__ . '/../wf-017-transaction-seam/yii/vendor/autoload.php';
require __DIR__ . '/../wf-017-http-action/vendor/autoload.php';
require __DIR__ . '/shared.php';

$connection = new Connection(new Driver(new Dsn(databaseName: 'memory')), new SchemaCache(new Psr16Cache(new ArrayAdapter())));
$connection->createCommand('CREATE TABLE users (id VARCHAR(80) PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, state VARCHAR(32) NOT NULL)')->execute();
$connection->createCommand('CREATE TABLE sessions (id VARCHAR(80) PRIMARY KEY, user_id VARCHAR(80) NOT NULL)')->execute();
$connection->createCommand('CREATE TABLE audits (id VARCHAR(80) PRIMARY KEY, user_id VARCHAR(80) NOT NULL)')->execute();
$connection->createCommand()->insert('users', [
    'id' => 'user-001', 'email' => 'ada@example.test',
    'password_hash' => password_hash('correct horse battery staple', PASSWORD_DEFAULT), 'state' => 'ACTIVE',
])->execute();

$unitOfWork = new NativeTransactionalUnitOfWork('Yii DB transaction', static fn (callable $operation): mixed => $connection->transaction($operation));
$findUser = static function (string $email) use ($connection): ?array {
    $row = $connection->createCommand('SELECT id, password_hash, state FROM users WHERE email = :email', [':email' => $email])->queryOne();
    return $row === false ? null : $row;
};
$insertSession = static fn (string $id, string $userId): int => $connection->createCommand()->insert('sessions', ['id' => $id, 'user_id' => $userId])->execute();
$insertAudit = static fn (string $id, string $userId): int => $connection->createCommand()->insert('audits', ['id' => $id, 'user_id' => $userId])->execute();
$counts = static fn (): array => [
    'sessions' => (int) $connection->createCommand('SELECT COUNT(*) FROM sessions')->queryScalar(),
    'audits' => (int) $connection->createCommand('SELECT COUNT(*) FROM audits')->queryScalar(),
];
$respond = static function (LoginOutcome $outcome): array {
    $parts = responseParts($outcome);
    $response = new Response($parts['status'], ['Content-Type' => 'application/json'], json_encode($parts['body'], JSON_THROW_ON_ERROR));
    if ($parts['cookie'] !== null) {
        $response = $response->withHeader('Set-Cookie', $parts['cookie']);
    }
    return [
        'class' => $response::class,
        'status' => $response->getStatusCode(),
        'headers' => ['content-type' => $response->getHeaderLine('Content-Type'), 'set-cookie' => $response->getHeaderLine('Set-Cookie')],
        'body' => json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
    ];
};

runLoginProbe(
    'Yii',
    ['yiisoft/db' => Composer\InstalledVersions::getPrettyVersion('yiisoft/db'), 'nyholm/psr7' => Composer\InstalledVersions::getPrettyVersion('nyholm/psr7')],
    $unitOfWork,
    $respond,
    $counts,
    handlerFactory($unitOfWork, $findUser, $insertSession, $insertAudit),
);
