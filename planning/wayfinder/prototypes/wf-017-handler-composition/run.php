<?php

declare(strict_types=1);

// PROTOTYPE — one-command runner. Requires the repository's `fight-common` Docker image.

$root = realpath(__DIR__ . '/../../../..');
if ($root === false) {
    throw new RuntimeException('Could not resolve repository root.');
}
$base = '/workspace/planning/wayfinder/prototypes/wf-017-handler-composition';
run(sprintf(
    'docker run --rm -v %s:/workspace -w %s fight-common composer install --no-interaction --no-progress',
    escapeshellarg($root),
    escapeshellarg($base),
));

foreach (['symfony', 'laravel', 'yii', 'codeigniter', 'slim'] as $lane) {
    run(sprintf(
        'docker run --rm -e PROTOTYPE_RECEIPT=%s/receipts/%s.json -v %s:/workspace -w %s fight-common php %s.php',
        $base,
        $lane,
        escapeshellarg($root),
        escapeshellarg($base),
        escapeshellarg($lane),
    ));
}

function run(string $command): void
{
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException(sprintf('Prototype command failed with exit code %d.', $exitCode));
    }
}
