<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;

return RectorConfig::configure()
    ->withCache(cacheDirectory: '/tmp/rector_cache')
    ->withPaths([__DIR__])
    ->withSkip([
        __DIR__ . '/vendor',
        RemoveParentCallWithoutParentRector::class,
    ])
    ->withPhpSets(php81: true)
    ->withPreparedSets(
        codeQuality: true,
    );
