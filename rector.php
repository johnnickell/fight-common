<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\FuncCall\ClosureFromCallableToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/release/src',
        __DIR__.'/release/tests'
    ])
    ->withPhpSets(php84: true)
    ->withSkip([
        NewlineBetweenClassLikeStmtsRector::class,
        RemoveParentCallWithoutParentRector::class,
        // Compatibility policy stores names as data; loading runtime classes would reverse the release boundary.
        StringClassNameToClassConstantRector::class          => [
            __DIR__.'/release/src/Application/PublicApiManifestAuthority.php',
            __DIR__.'/release/src/Application/SchedulerEvidenceAuthority.php',
            __DIR__.'/src/Application/Scheduler/Scheduler.php'
        ],
        // The legacy Scheduler bridge intentionally late-binds an optional Process implementation.
        ClosureFromCallableToFirstClassCallableRector::class => [
            __DIR__.'/src/Application/Scheduler/Scheduler.php'
        ],
        // Array callables are load-bearing here: removeHandler compares with === against
        // stored [$service, $method] arrays, so a Closure would never match.
        ArrayToFirstClassCallableRector::class               => [
            __DIR__.'/tests/Adapter/Messaging/Event/Sync/ServiceAwareEventDispatcherTest.php'
        ]
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
