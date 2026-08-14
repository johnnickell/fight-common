<?php

declare(strict_types=1);

// PROTOTYPE — one-command runner. Requires the repository's `fight-common` Docker image.

$root = realpath(__DIR__ . '/../../../..');
if ($root === false) {
    throw new RuntimeException('Could not resolve repository root.');
}
$prototypeRoot = '/workspace/planning/wayfinder/prototypes';
$dependencies = [
    'wf-017-transaction-seam/doctrine',
    'wf-017-transaction-seam/laravel',
    'wf-017-transaction-seam/yii',
    'wf-017-transaction-seam/codeigniter',
    'wf-017-http-action',
];
foreach ($dependencies as $directory) {
    run(sprintf(
        'docker run --rm -v %s:/workspace -w %s/%s fight-common composer install --no-interaction --no-progress',
        escapeshellarg($root),
        escapeshellarg($prototypeRoot),
        escapeshellarg($directory),
    ));
}

$lanes = [
    ['script' => 'doctrine.php', 'framework' => 'Symfony', 'receipt' => 'symfony.json'],
    ['script' => 'laravel.php', 'framework' => null, 'receipt' => 'laravel.json'],
    ['script' => 'yii.php', 'framework' => null, 'receipt' => 'yii.json'],
    ['script' => 'codeigniter.php', 'framework' => null, 'receipt' => 'codeigniter.json'],
    ['script' => 'doctrine.php', 'framework' => 'Slim', 'receipt' => 'slim.json'],
];
foreach ($lanes as $lane) {
    $environment = sprintf(
        '-e PROTOTYPE_RECEIPT=%s/wf-017-login-transaction-slice/receipts/%s',
        $prototypeRoot,
        $lane['receipt'],
    );
    if ($lane['framework'] !== null) {
        $environment .= ' -e PROTOTYPE_FRAMEWORK=' . escapeshellarg($lane['framework']);
    }
    run(sprintf(
        'docker run --rm %s -v %s:/workspace -w %s/wf-017-login-transaction-slice fight-common php %s',
        $environment,
        escapeshellarg($root),
        escapeshellarg($prototypeRoot),
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
