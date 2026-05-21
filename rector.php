<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests'
    ])
    ->withPhpSets(php84: true)
    ->withSkip([
        RemoveParentCallWithoutParentRector::class,
        AddOverrideAttributeToOverriddenMethodsRector::class,
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withTypeCoverageLevel(8)
    ->withDeadCodeLevel(8)
    ->withCodeQualityLevel(8)
    ->withPreparedSets(
        codingStyle: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true
    );
