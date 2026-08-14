<?php

declare(strict_types=1);

// PROTOTYPE — one-command runner. Requires the repository's `fight-common` Docker image.

$root = realpath(__DIR__ . '/../../../..');
if ($root === false) {
    throw new RuntimeException('Could not resolve repository root.');
}

$base = '/workspace/planning/wayfinder/prototypes';
$transaction = $base . '/wf-017-transaction-seam';
$split = $base . '/wf-017-transactional-uow-split';

foreach (['doctrine', 'laravel', 'yii', 'codeigniter'] as $directory) {
    run(sprintf(
        'docker run --rm -v %s:/workspace -w %s/%s fight-common composer install --no-interaction --no-progress',
        escapeshellarg($root),
        escapeshellarg($transaction),
        escapeshellarg($directory),
    ));
}

$lanes = [
    ['script' => 'doctrine.php', 'receipt' => 'symfony.json', 'framework' => 'Symfony'],
    ['script' => 'laravel.php', 'receipt' => 'laravel.json'],
    ['script' => 'yii.php', 'receipt' => 'yii.json'],
    ['script' => 'codeigniter.php', 'receipt' => 'codeigniter.json'],
    ['script' => 'doctrine.php', 'receipt' => 'slim.json', 'framework' => 'Slim'],
];

foreach ($lanes as $lane) {
    $environment = sprintf('-e PROTOTYPE_RECEIPT=%s/receipts/%s', $split, $lane['receipt']);
    if (isset($lane['framework'])) {
        $environment .= ' -e PROTOTYPE_FRAMEWORK=' . escapeshellarg($lane['framework']);
    }

    run(sprintf(
        'docker run --rm %s -v %s:/workspace -w %s fight-common php %s',
        $environment,
        escapeshellarg($root),
        escapeshellarg($split),
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
