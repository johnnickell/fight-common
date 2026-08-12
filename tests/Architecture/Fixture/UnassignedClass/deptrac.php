<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\DeptracConfig;

$configureRuntime = require dirname(__DIR__, 4).'/deptrac.runtime.php';

return static function (DeptracConfig $config) use ($configureRuntime): void {
    $configureRuntime($config, __DIR__.'/src');
};
