<?php

declare(strict_types=1);

use CodeIgniter\Database\Config;
use CodeIgniter\HTTP\Response;
use Prototype\LoginTransactionSlice\LoginOutcome;
use Prototype\LoginTransactionSlice\NativeTransactionalUnitOfWork;

use function Prototype\LoginTransactionSlice\handlerFactory;
use function Prototype\LoginTransactionSlice\responseParts;
use function Prototype\LoginTransactionSlice\runLoginProbe;

define('APPPATH', __DIR__ . '/../wf-017-record-mapping/codeigniter-app/');
define('ENVIRONMENT', 'testing');

require __DIR__ . '/../wf-017-transaction-seam/codeigniter/vendor/autoload.php';
require __DIR__ . '/../wf-017-record-mapping/codeigniter-bootstrap.php';
require __DIR__ . '/shared.php';

final class PrototypeLoginResponse extends Response
{
    public function __construct() {}
}

$connection = Config::connect([
    'DSN' => '', 'database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => '', 'pConnect' => false,
    'DBDebug' => true, 'charset' => 'utf8', 'DBCollat' => '', 'swapPre' => '', 'encrypt' => false,
    'compress' => false, 'strictOn' => true, 'failover' => [], 'port' => 0, 'foreignKeys' => true, 'busyTimeout' => 1000,
], false);
$connection->query('CREATE TABLE users (id VARCHAR(80) PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, state VARCHAR(32) NOT NULL)');
$connection->query('CREATE TABLE sessions (id VARCHAR(80) PRIMARY KEY, user_id VARCHAR(80) NOT NULL)');
$connection->query('CREATE TABLE audits (id VARCHAR(80) PRIMARY KEY, user_id VARCHAR(80) NOT NULL)');
$connection->table('users')->insert([
    'id' => 'user-001', 'email' => 'ada@example.test',
    'password_hash' => password_hash('correct horse battery staple', PASSWORD_DEFAULT), 'state' => 'ACTIVE',
]);

$unitOfWork = new NativeTransactionalUnitOfWork('CodeIgniter explicit transaction', static function (callable $operation) use ($connection): mixed {
    if (!$connection->transBegin()) {
        throw new RuntimeException('could not begin transaction');
    }
    try {
        $result = $operation();
        if (!$connection->transStatus() || !$connection->transCommit()) {
            throw new RuntimeException('transaction did not commit');
        }
        return $result;
    } catch (Throwable $throwable) {
        $connection->transRollback();
        throw $throwable;
    }
});
$findUser = static fn (string $email): ?array => $connection->table('users')->where('email', $email)->get()->getRowArray();
$insertSession = static fn (string $id, string $userId): bool => $connection->table('sessions')->insert(['id' => $id, 'user_id' => $userId]);
$insertAudit = static fn (string $id, string $userId): bool => $connection->table('audits')->insert(['id' => $id, 'user_id' => $userId]);
$counts = static fn (): array => [
    'sessions' => (int) $connection->table('sessions')->countAllResults(),
    'audits' => (int) $connection->table('audits')->countAllResults(),
];
$respond = static function (LoginOutcome $outcome): array {
    $parts = responseParts($outcome);
    $response = (new PrototypeLoginResponse())->setStatusCode($parts['status'])->setHeader('Content-Type', 'application/json')->setBody(json_encode($parts['body'], JSON_THROW_ON_ERROR));
    if ($parts['cookie'] !== null) {
        $response->setHeader('Set-Cookie', $parts['cookie']);
    }
    return [
        'class' => $response::class,
        'status' => $response->getStatusCode(),
        'headers' => ['content-type' => $response->getHeaderLine('Content-Type'), 'set-cookie' => $response->getHeaderLine('Set-Cookie')],
        'body' => json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
    ];
};

runLoginProbe(
    'CodeIgniter',
    ['codeigniter4/framework' => Composer\InstalledVersions::getPrettyVersion('codeigniter4/framework')],
    $unitOfWork,
    $respond,
    $counts,
    handlerFactory($unitOfWork, $findUser, $insertSession, $insertAudit),
);
