<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;
$configureRuntime = require __DIR__.'/deptrac.runtime.php';
$configureRelease = require __DIR__.'/deptrac.release.php';

return static function (DeptracConfig $config) use ($configureRelease, $configureRuntime): void {
    $configureRuntime(
        $config,
        __DIR__.'/src/Domain',
        __DIR__.'/src/Application',
        __DIR__.'/src/Adapter',
        __DIR__.'/src/Standards',
    );
    $configureRelease(
        $config,
        __DIR__.'/release/src/Application',
        __DIR__.'/release/src/Adapter',
        __DIR__.'/release/scripts',
        __DIR__.'/release/consumer',
    );
    $qualityTooling = Layer::withName('Quality tooling')->collectors(
        ClassLikeConfig::create('^Fight\\Common\\Tooling\\')
    );
    $config
        ->paths(__DIR__.'/scripts')
        ->layers($qualityTooling)
        ->rulesets(Ruleset::forLayer($qualityTooling)->accesses(Layer::withName('PHP internals')))
        ->cacheFile(__DIR__.'/var/cache/deptrac.cache');
};
