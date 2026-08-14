<?php

declare(strict_types=1);

// PROTOTYPE — one-command runner. Requires the repository's `fight-common` Docker image.

$root = realpath(__DIR__ . '/../../../..');
if ($root === false) {
    throw new RuntimeException('Could not resolve repository root.');
}

$base = '/workspace/planning/wayfinder/prototypes';
$dependencyRoots = [
    'wf-017-transaction-seam/doctrine',
    'wf-017-transaction-seam/laravel',
    'wf-017-transaction-seam/yii',
    'wf-017-transaction-seam/codeigniter',
    'wf-017-record-mapping/yii-active-record',
];
foreach ($dependencyRoots as $directory) {
    run(sprintf(
        'docker run --rm -v %s:/workspace -w %s/%s fight-common composer install --no-interaction --no-progress',
        escapeshellarg($root),
        escapeshellarg($base),
        escapeshellarg($directory),
    ));
}

$lanes = [
    ['script' => 'doctrine.php', 'receipt' => 'symfony.json', 'framework' => 'Symfony'],
    ['script' => 'doctrine.php', 'receipt' => 'slim.json', 'framework' => 'Slim'],
    ['script' => 'laravel.php', 'receipt' => 'laravel.json'],
    ['script' => 'yii-active-record.php', 'receipt' => 'yii-active-record.json'],
    ['script' => 'yii-db.php', 'receipt' => 'yii-db.json'],
    ['script' => 'codeigniter-model.php', 'receipt' => 'codeigniter-model.json'],
    ['script' => 'codeigniter-query-builder.php', 'receipt' => 'codeigniter-query-builder.json'],
];
foreach ($lanes as $lane) {
    $environment = sprintf(
        '-e PROTOTYPE_RECEIPT=%s/wf-017-record-mapping/receipts/%s',
        $base,
        $lane['receipt'],
    );
    if (isset($lane['framework'])) {
        $environment .= ' -e PROTOTYPE_FRAMEWORK=' . escapeshellarg($lane['framework']);
    }
    run(sprintf(
        'docker run --rm %s -v %s:/workspace -w %s/wf-017-record-mapping fight-common php %s',
        $environment,
        escapeshellarg($root),
        escapeshellarg($base),
        escapeshellarg($lane['script']),
    ));
}

function run(string $command): void
{
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException(sprintf('Prototype command failed with exit code %d.', $exitCode));
    }
}
