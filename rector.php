<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests'
    ])
    ->withPhpSets(php84: true)
    ->withSkip([
        NewlineBetweenClassLikeStmtsRector::class,
        RemoveParentCallWithoutParentRector::class,
        AddOverrideAttributeToOverriddenMethodsRector::class,
        // Array callables are load-bearing here: removeHandler compares with === against
        // stored [$service, $method] arrays, so a Closure would never match.
        ArrayToFirstClassCallableRector::class => [
            __DIR__.'/tests/Adapter/Messaging/Event/Sync/ServiceAwareEventDispatcherTest.php',
        ],
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
