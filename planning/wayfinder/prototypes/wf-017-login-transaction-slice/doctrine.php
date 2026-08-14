<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Nyholm\Psr7\Response as Psr7Response;
use Prototype\LoginTransactionSlice\LoginOutcome;
use Prototype\LoginTransactionSlice\NativeTransactionalUnitOfWork;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

use function Prototype\LoginTransactionSlice\handlerFactory;
use function Prototype\LoginTransactionSlice\responseParts;
use function Prototype\LoginTransactionSlice\runLoginProbe;

require __DIR__ . '/../wf-017-transaction-seam/doctrine/vendor/autoload.php';
require __DIR__ . '/../wf-017-http-action/vendor/autoload.php';
require __DIR__ . '/shared.php';

$framework = getenv('PROTOTYPE_FRAMEWORK') ?: 'Symfony';
$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE users (id VARCHAR(80) PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, state VARCHAR(32) NOT NULL)');
$connection->executeStatement('CREATE TABLE sessions (id VARCHAR(80) PRIMARY KEY, user_id VARCHAR(80) NOT NULL)');
$connection->executeStatement('CREATE TABLE audits (id VARCHAR(80) PRIMARY KEY, user_id VARCHAR(80) NOT NULL)');
$connection->insert('users', [
    'id' => 'user-001',
    'email' => 'ada@example.test',
    'password_hash' => password_hash('correct horse battery staple', PASSWORD_DEFAULT),
    'state' => 'ACTIVE',
]);

$unitOfWork = new NativeTransactionalUnitOfWork(
    $framework . ' Doctrine DBAL transaction',
    static fn (callable $operation): mixed => $connection->transactional($operation),
);
$findUser = static function (string $email) use ($connection): ?array {
    $row = $connection->fetchAssociative('SELECT id, password_hash, state FROM users WHERE email = ?', [$email]);
    return $row === false ? null : $row;
};
$insertSession = static fn (string $id, string $userId): int => $connection->insert('sessions', ['id' => $id, 'user_id' => $userId]);
$insertAudit = static fn (string $id, string $userId): int => $connection->insert('audits', ['id' => $id, 'user_id' => $userId]);
$counts = static fn (): array => [
    'sessions' => (int) $connection->fetchOne('SELECT COUNT(*) FROM sessions'),
    'audits' => (int) $connection->fetchOne('SELECT COUNT(*) FROM audits'),
];

$respond = static function (LoginOutcome $outcome) use ($framework): array {
    $parts = responseParts($outcome);
    if ($framework === 'Symfony') {
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
            'headers' => [
                'content-type' => (string) $response->headers->get('Content-Type'),
                'set-cookie' => isset($cookies[0]) ? (string) $cookies[0] : '',
            ],
            'body' => json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    $response = new Psr7Response($parts['status'], ['Content-Type' => 'application/json']);
    $response->getBody()->write(json_encode($parts['body'], JSON_THROW_ON_ERROR));
    if ($parts['cookie'] !== null) {
        $response = $response->withHeader('Set-Cookie', $parts['cookie']);
    }
    return inspectPsr7($response);
};

runLoginProbe(
    $framework,
    [
        'doctrine/dbal' => Composer\InstalledVersions::getPrettyVersion('doctrine/dbal'),
        $framework === 'Symfony' ? 'symfony/http-foundation' : 'nyholm/psr7' => Composer\InstalledVersions::getPrettyVersion($framework === 'Symfony' ? 'symfony/http-foundation' : 'nyholm/psr7'),
    ],
    $unitOfWork,
    $respond,
    $counts,
    handlerFactory($unitOfWork, $findUser, $insertSession, $insertAudit),
);

/** @return array{class: string, status: int, headers: array<string, string>, body: array<string, mixed>} */
function inspectPsr7(ResponseInterface $response): array
{
    return [
        'class' => $response::class,
        'status' => $response->getStatusCode(),
        'headers' => [
            'content-type' => $response->getHeaderLine('Content-Type'),
            'set-cookie' => $response->getHeaderLine('Set-Cookie'),
        ],
        'body' => json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
    ];
}
