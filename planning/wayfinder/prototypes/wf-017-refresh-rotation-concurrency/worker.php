<?php

declare(strict_types=1);

require __DIR__ . '/shared.php';

$framework = getenv('PROTOTYPE_FRAMEWORK');
$action = $argv[1] ?? '';
if (!is_string($framework) || $framework === '') {
    throw new RuntimeException('PROTOTYPE_FRAMEWORK is required.');
}
$store = prototypeStore($framework);

if ($action === 'setup') {
    $store->execute('DROP TABLE IF EXISTS refresh_audits');
    $store->execute('DROP TABLE IF EXISTS refresh_sessions');
    $store->execute('CREATE TABLE refresh_sessions (id VARCHAR(80) PRIMARY KEY, user_id VARCHAR(80) NOT NULL, family_id VARCHAR(80) NOT NULL, token_digest VARCHAR(120) NOT NULL UNIQUE, status VARCHAR(24) NOT NULL, rotated_at INTEGER NULL, successor_id VARCHAR(80) NULL)');
    $store->execute('CREATE TABLE refresh_audits (id VARCHAR(80) PRIMARY KEY, family_id VARCHAR(80) NOT NULL, action VARCHAR(80) NOT NULL)');
    $store->execute('INSERT INTO refresh_sessions (id, user_id, family_id, token_digest, status, rotated_at, successor_id) VALUES (?, ?, ?, ?, ?, NULL, NULL)', ['session-current', 'user-001', 'family-device-a', 'digest-current', 'ACTIVE']);
    echo json_encode(['decision' => 'setup', 'api' => $store->api, 'versions' => $store->versions], JSON_THROW_ON_ERROR);
    exit(0);
}
if ($action === 'inspect') {
    echo json_encode([
        'current' => $store->fetch('SELECT id, status, rotated_at, successor_id FROM refresh_sessions WHERE id = ?', ['session-current']),
        'successor' => $store->fetch('SELECT id, status FROM refresh_sessions WHERE id = ?', ['session-successor']),
        'rotation_audit' => $store->fetch('SELECT id, action FROM refresh_audits WHERE id = ?', ['audit-rotation']),
        'reuse_audit' => $store->fetch('SELECT id, action FROM refresh_audits WHERE id = ?', ['audit-reuse']),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

$now = (int) (getenv('PROTOTYPE_NOW') ?: 100);
$hold = (int) (getenv('PROTOTYPE_HOLD_MS') ?: 0);
$marker = getenv('PROTOTYPE_LOCK_MARKER');
$marker = is_string($marker) && $marker !== '' ? $marker : null;
try {
    echo json_encode(rotateSession($store, $now, $hold, $marker, $action === 'fail-audit'), JSON_THROW_ON_ERROR);
} catch (RuntimeException $exception) {
    echo json_encode(['decision' => 'failed', 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
