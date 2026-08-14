<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager;
use Illuminate\Http\JsonResponse;
use Prototype\LoginTransactionSlice\LoginOutcome;
use Prototype\LoginTransactionSlice\NativeTransactionalUnitOfWork;
use Symfony\Component\HttpFoundation\Cookie;

use function Prototype\LoginTransactionSlice\handlerFactory;
use function Prototype\LoginTransactionSlice\responseParts;
use function Prototype\LoginTransactionSlice\runLoginProbe;

require __DIR__ . '/../wf-017-http-action/vendor/autoload.php';
require __DIR__ . '/shared.php';

$capsule = new Manager();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$connection = $capsule->getConnection();
$schema = $connection->getSchemaBuilder();
$schema->create('users', static function ($table): void {
    $table->string('id')->primary();
    $table->string('email')->unique();
    $table->string('password_hash');
    $table->string('state');
});
$schema->create('sessions', static function ($table): void { $table->string('id')->primary(); $table->string('user_id'); });
$schema->create('audits', static function ($table): void { $table->string('id')->primary(); $table->string('user_id'); });
$connection->table('users')->insert([
    'id' => 'user-001', 'email' => 'ada@example.test',
    'password_hash' => password_hash('correct horse battery staple', PASSWORD_DEFAULT), 'state' => 'ACTIVE',
]);

$unitOfWork = new NativeTransactionalUnitOfWork('Laravel database transaction', static fn (callable $operation): mixed => $connection->transaction($operation));
$findUser = static fn (string $email): ?array => ($row = $connection->table('users')->where('email', $email)->first()) === null ? null : (array) $row;
$insertSession = static fn (string $id, string $userId): bool => $connection->table('sessions')->insert(['id' => $id, 'user_id' => $userId]);
$insertAudit = static fn (string $id, string $userId): bool => $connection->table('audits')->insert(['id' => $id, 'user_id' => $userId]);
$counts = static fn (): array => ['sessions' => $connection->table('sessions')->count(), 'audits' => $connection->table('audits')->count()];
$respond = static function (LoginOutcome $outcome): array {
    $parts = responseParts($outcome);
    $response = new JsonResponse($parts['body'], $parts['status']);
    if ($parts['cookie'] !== null) {
        $response->headers->setCookie(new Cookie(
            'refresh',
            $outcome->refreshToken ?? '',
            0,
            '/api/v1/access/session',
            null,
            true,
            true,
            false,
            'strict',
        ));
    }
    $cookies = $response->headers->getCookies();
    return [
        'class' => $response::class,
        'status' => $response->getStatusCode(),
        'headers' => ['content-type' => (string) $response->headers->get('Content-Type'), 'set-cookie' => isset($cookies[0]) ? (string) $cookies[0] : ''],
        'body' => json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR),
    ];
};

runLoginProbe(
    'Laravel',
    ['illuminate/database' => Composer\InstalledVersions::getPrettyVersion('illuminate/database'), 'illuminate/http' => Composer\InstalledVersions::getPrettyVersion('illuminate/http')],
    $unitOfWork,
    $respond,
    $counts,
    handlerFactory($unitOfWork, $findUser, $insertSession, $insertAudit),
);
