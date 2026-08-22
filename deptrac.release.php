<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config, string ...$paths): void {
    $phpInternals = Layer::withName('PHP internals');

    $config
        ->paths(...$paths)
        ->layers(
            $releaseApplication = Layer::withName('Release Application')->collectors(
                ClassLikeConfig::create('^Fight\\Release\\Application\\'),
            ),
            $releaseAdapter = Layer::withName('Release Adapter')->collectors(
                ClassLikeConfig::create('^Fight\\Release\\Adapter\\'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($releaseApplication)->accesses($phpInternals),
            Ruleset::forLayer($releaseAdapter)->accesses($releaseApplication, $phpInternals),
        )
    ;
};
