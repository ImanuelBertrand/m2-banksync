<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;

return RectorConfig::configure()
//    ->withPaths([
//        __DIR__ . '/Block',
//        __DIR__ . '/Console',
//        __DIR__ . '/Controller',
//        __DIR__ . '/Cron',
//        __DIR__ . '/Helper',
//        __DIR__ . '/Lib',
//        __DIR__ . '/Logger',
//        __DIR__ . '/Model',
//        __DIR__ . '/Service',
//        __DIR__ . '/Setup',
//        __DIR__ . '/Ui',
//    ])
    ->withPaths([__DIR__])
    ->withSkip([
        __DIR__ . '/vendor',
        RemoveParentCallWithoutParentRector::class,
    ])
    // uncomment to reach your current PHP version
    ->withPhpSets(php81: true);
//    ->withTypeCoverageLevel(0)
//    ->withDeadCodeLevel(0)
//    ->withCodeQualityLevel(0);
