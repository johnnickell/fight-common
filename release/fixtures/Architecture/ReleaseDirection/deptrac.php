<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\DeptracConfig;

$root = dirname(__DIR__, 4);
$configureRuntime = require $root.'/deptrac.runtime.php';
$configureRelease = require $root.'/deptrac.release.php';

return static function (DeptracConfig $config) use ($configureRelease, $configureRuntime): void {
    $fixture = __DIR__;
    $configureRuntime($config, $fixture.'/src/Application');
    $configureRelease(
        $config,
        $fixture.'/release/Application',
        $fixture.'/release/Adapter',
    );
};
