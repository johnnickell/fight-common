<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use Fight\Release\Adapter\PackageSurfaceInspector;
use Fight\Release\Application\CertificationRecord;

require __DIR__.'/functions.php';

try {
    $arguments = $_SERVER['argv'] ?? [];
    $command = $arguments[1] ?? '';
    if ($command === 'surface' && count($arguments) === 5) {
        $result = new PackageSurfaceInspector()->inspect($arguments[2], $arguments[3], $arguments[4]);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }

    if ($command === 'consumer-manifest' && count($arguments) === 5) {
        $manifest = [
            'name'          => 'fight/release-certification-consumer',
            'type'          => 'project',
            'require'       => [
                'php'                      => '>=8.5',
                'johnnickell/fight-common' => $arguments[4]
            ],
            'repositories'  => [['type' => 'artifact', 'url' => $arguments[3]]],
            'config'        => ['allow-plugins' => false],
            'prefer-stable' => true
        ];
        file_put_contents(
            $arguments[2],
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
        ) !== false || throw new RuntimeException('The consumer manifest could not be written.');
        exit(0);
    }

    if ($command === 'archive-manifest' && count($arguments) === 4) {
        $manifest = certification_json($arguments[2]);
        $manifest['version'] = $arguments[3];
        file_put_contents(
            $arguments[2],
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
        ) !== false || throw new RuntimeException('The archive manifest could not be written.');
        exit(0);
    }

    if ($command === 'record' && count($arguments) === 13) {
        $archiveSha256 = hash_file('sha256', $arguments[4]);
        is_string($archiveSha256) || throw new RuntimeException('The archive digest is unavailable.');
        $starterReferences = certification_json($arguments[11]);
        $record = new CertificationRecord()->create(
            $arguments[2],
            $arguments[3],
            basename($arguments[4]),
            $archiveSha256,
            certification_commands($arguments[5]),
            [
                'default' => certification_versions($arguments[6]),
                'latest'  => certification_versions($arguments[7]),
                'lowest'  => certification_versions($arguments[8])
            ],
            certification_json($arguments[9]),
            certification_json($arguments[10]),
            $starterReferences['receipts'] ?? []
        );
        file_put_contents(
            $arguments[12],
            json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
        ) !== false || throw new RuntimeException('The certification record could not be written.');
        exit(0);
    }

    throw new RuntimeException('Unsupported certification helper invocation.');
} catch (Throwable $throwable) {
    fwrite(STDERR, 'release certification: '.$throwable->getMessage().PHP_EOL);
    exit(1);
}
