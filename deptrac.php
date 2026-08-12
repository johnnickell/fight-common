<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\DeptracConfig;

$configureRuntime = require __DIR__.'/deptrac.runtime.php';

return static function (DeptracConfig $config) use ($configureRuntime): void {
    $configureRuntime(
        $config,
        __DIR__.'/src/Domain',
        __DIR__.'/src/Application',
        __DIR__.'/src/Adapter',
        __DIR__.'/src/Standards',
    );
    $config->cacheFile(__DIR__.'/var/cache/deptrac.cache');
};
