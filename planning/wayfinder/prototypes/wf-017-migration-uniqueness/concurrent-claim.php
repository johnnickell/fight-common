<?php

declare(strict_types=1);

require __DIR__ . '/shared.php';

try {
    $pdo = prototypePdo(databaseUrl());
    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO users (id, canonical_email, account_state) VALUES (:id, :email, :state)',
    )->execute([
        'id' => 'user-deleted',
        'email' => 'same@example.test',
        'state' => 'DELETED',
    ]);
    $pdo->commit();
    echo json_encode(['outcome' => 'unexpected_commit'], JSON_THROW_ON_ERROR);
} catch (PDOException $exception) {
    echo json_encode([
        'outcome' => 'unique_violation',
        'sqlstate' => $exception->errorInfo[0] ?? $exception->getCode(),
    ], JSON_THROW_ON_ERROR);
}
