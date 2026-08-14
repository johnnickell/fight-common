<?php

declare(strict_types=1);

// PROTOTYPE — one-command runner. Requires the repository's `fight-common` Docker image.

$root = realpath(__DIR__ . '/../../../..');
if ($root === false) {
    throw new RuntimeException('Could not resolve repository root.');
}

$base = '/workspace/planning/wayfinder/prototypes/wf-017-transaction-seam';
$lanes = [
    ['directory' => 'doctrine', 'receipt' => 'symfony.json', 'framework' => 'Symfony'],
    ['directory' => 'laravel', 'receipt' => 'laravel.json', 'framework' => null],
    ['directory' => 'yii', 'receipt' => 'yii.json', 'framework' => null],
    ['directory' => 'codeigniter', 'receipt' => 'codeigniter.json', 'framework' => null],
    ['directory' => 'doctrine', 'receipt' => 'slim.json', 'framework' => 'Slim'],
];

foreach (array_unique(array_column($lanes, 'directory')) as $directory) {
    run(sprintf(
        'docker run --rm -v %s:/workspace -w %s/%s fight-common composer install --no-interaction --no-progress',
        escapeshellarg($root),
        escapeshellarg($base),
        escapeshellarg($directory),
    ));
}

foreach ($lanes as $lane) {
    $environment = sprintf(
        '-e PROTOTYPE_RECEIPT=%s/receipts/%s',
        $base,
        $lane['receipt'],
    );
    if ($lane['framework'] !== null) {
        $environment .= ' -e PROTOTYPE_FRAMEWORK=' . escapeshellarg($lane['framework']);
    }

    run(sprintf(
        'docker run --rm %s -v %s:/workspace -w %s/%s fight-common php prototype.php',
        $environment,
        escapeshellarg($root),
        escapeshellarg($base),
        escapeshellarg($lane['directory']),
    ));
}

function run(string $command): void
{
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException(sprintf('Prototype command failed with exit code %d.', $exitCode));
    }
}
