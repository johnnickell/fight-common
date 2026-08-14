<?php

declare(strict_types=1);

function databaseUrl(): string
{
    $url = getenv('PROTOTYPE_DATABASE_URL');
    if (!is_string($url) || $url === '') {
        throw new RuntimeException('PROTOTYPE_DATABASE_URL is required.');
    }

    return $url;
}

/** @return array{driver: string, host: string, port: int, database: string, user: string, password: string} */
function databaseConfig(string $url): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'], $parts['path'], $parts['user'], $parts['pass'])) {
        throw new RuntimeException('Prototype database URL is invalid.');
    }

    return [
        'driver' => $parts['scheme'] === 'postgresql' ? 'pgsql' : $parts['scheme'],
        'host' => $parts['host'],
        'port' => (int) ($parts['port'] ?? ($parts['scheme'] === 'postgresql' ? 5432 : 3306)),
        'database' => ltrim($parts['path'], '/'),
        'user' => urldecode($parts['user']),
        'password' => urldecode($parts['pass']),
    ];
}

function prototypePdo(string $url): PDO
{
    $config = databaseConfig($url);
    $dsn = sprintf(
        '%s:host=%s;port=%d;dbname=%s',
        $config['driver'],
        $config['host'],
        $config['port'],
        $config['database'],
    );

    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function dropPrototypeTables(PDO $pdo): void
{
    $pdo->exec('DROP TABLE IF EXISTS user_roles');
    $pdo->exec('DROP TABLE IF EXISTS roles');
    $pdo->exec('DROP TABLE IF EXISTS users');
}

/**
 * @param array<string, string|null> $versions
 * @param callable(): void $migrate
 */
function runMigrationProbe(string $framework, string $migrationApi, array $versions, callable $migrate): void
{
    $url = databaseUrl();
    $config = databaseConfig($url);
    $pdo = prototypePdo($url);

    dropPrototypeTables($pdo);
    $migrate();

    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO users (id, canonical_email, account_state) VALUES (:id, :email, :state)',
    );
    $insert->execute([
        'id' => 'user-pending',
        'email' => 'same@example.test',
        'state' => 'PENDING_ACTIVATION',
    ]);

    $child = startConcurrentClaim($url);
    usleep(300_000);
    $pdo->commit();
    $loser = finishConcurrentClaim($child);

    if (($loser['outcome'] ?? null) !== 'unique_violation') {
        throw new RuntimeException('The concurrent canonical-email claimant did not lose to the unique constraint.');
    }

    $insert->execute([
        'id' => 'user-active',
        'email' => 'different@example.test',
        'state' => 'ACTIVE',
    ]);
    $pdo->prepare('INSERT INTO roles (id, name) VALUES (:id, :name)')->execute([
        'id' => 'role-admin',
        'name' => 'ROLE_ADMIN',
    ]);
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user, :role)')->execute([
        'user' => 'user-pending',
        'role' => 'role-admin',
    ]);

    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $relationship = $pdo->query('SELECT user_id, role_id FROM user_roles')->fetch();
    if ($users !== 2 || $relationship !== ['user_id' => 'user-pending', 'role_id' => 'role-admin']) {
        throw new RuntimeException('Identity or relationship verification failed.');
    }

    $constraints = inspectConstraints($pdo, $config['driver'], $config['database']);
    if (!$constraints['canonical_email_unique'] || !$constraints['user_id_relationship']) {
        throw new RuntimeException('Expected unique or relationship constraint was not discoverable.');
    }

    $receipt = [
        'prototype' => 'WF-017 migration uniqueness',
        'framework' => $framework,
        'database' => $config['driver'] === 'pgsql' ? 'PostgreSQL' : 'MySQL',
        'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'migration_api' => $migrationApi,
        'versions' => $versions,
        'schema' => [
            'identity' => 'users.id',
            'canonical_email_unique_index' => 'uniq_users_canonical_email',
            'unique_index_columns' => ['canonical_email'],
            'documented_tenant_evolution' => ['tenant_id', 'canonical_email'],
            'relationship_identity' => ['user_roles.user_id', 'user_roles.role_id'],
        ],
        'checks' => [
            'pending_claim_committed' => true,
            'concurrent_deleted_claim_rejected' => true,
            'database_unique_sqlstate' => $loser['sqlstate'] ?? null,
            'different_email_across_state_allowed' => true,
            'relationship_keyed_by_user_id' => true,
            'unique_constraint_discovered' => $constraints['canonical_email_unique'],
            'foreign_key_discovered' => $constraints['user_id_relationship'],
        ],
        'storage' => ['users' => $users, 'roles' => 1, 'assignments' => 1],
        'verdict' => 'pass',
    ];

    $path = getenv('PROTOTYPE_RECEIPT');
    if (!is_string($path) || $path === '') {
        throw new RuntimeException('PROTOTYPE_RECEIPT is required.');
    }
    file_put_contents($path, json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
    echo sprintf("PASS %s / %s\n", $framework, $receipt['database']);
}

/** @return array{process: resource, pipes: array<int, resource>} */
function startConcurrentClaim(string $url): array
{
    $command = [PHP_BINARY, __DIR__ . '/concurrent-claim.php'];
    $pipes = [];
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__,
        ['PROTOTYPE_DATABASE_URL' => $url],
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start concurrent claimant.');
    }
    fclose($pipes[0]);

    return ['process' => $process, 'pipes' => $pipes];
}

/** @param array{process: resource, pipes: array<int, resource>} $child */
function finishConcurrentClaim(array $child): array
{
    $stdout = stream_get_contents($child['pipes'][1]);
    $stderr = stream_get_contents($child['pipes'][2]);
    fclose($child['pipes'][1]);
    fclose($child['pipes'][2]);
    $exitCode = proc_close($child['process']);
    if ($exitCode !== 0) {
        throw new RuntimeException('Concurrent claimant failed: ' . trim((string) $stderr));
    }

    $result = json_decode((string) $stdout, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($result)) {
        throw new RuntimeException('Concurrent claimant returned an invalid result.');
    }

    return $result;
}

/** @return array{canonical_email_unique: bool, user_id_relationship: bool} */
function inspectConstraints(PDO $pdo, string $driver, string $database): array
{
    if ($driver === 'pgsql') {
        $index = $pdo->query(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'users' AND indexname = 'uniq_users_canonical_email'",
        )->fetchColumn();
        $foreignKey = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_name = 'user_roles' AND column_name = 'user_id' AND position_in_unique_constraint IS NOT NULL",
        )->fetchColumn();
    } else {
        $index = $pdo->query(
            "SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'uniq_users_canonical_email' AND non_unique = 0",
        )->fetchColumn();
        $query = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema = :database AND table_name = 'user_roles' AND column_name = 'user_id' AND referenced_table_name = 'users'",
        );
        $query->execute(['database' => $database]);
        $foreignKey = $query->fetchColumn();
    }

    return [
        'canonical_email_unique' => is_string($index) && str_contains(strtolower($index), 'canonical_email'),
        'user_id_relationship' => (int) $foreignKey === 1,
    ];
}
