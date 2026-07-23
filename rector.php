<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;

return RectorConfig::configure()
    ->withCache(cacheDirectory: '/tmp/rector_cache')
    ->withPaths([__DIR__])
    ->withSkip([
        __DIR__ . '/vendor',
        RemoveParentCallWithoutParentRector::class,
        // These grids register a grid-column frame_callback, which Magento only invokes when it is
        // an [$widget, 'method'] array (Grid\Column::getRowField() gates on is_array()). Converting
        // it to first-class-callable syntax turns it into a Closure, silently disabling the column
        // decoration. Keep the array form here.
        ArrayToFirstClassCallableRector::class => [
            __DIR__ . '/Block/Adminhtml/Customer/Edit/Tab/Orders.php',
            __DIR__ . '/Block/Adminhtml/Customer/Edit/Tab/Invoices.php',
        ],
    ])
    ->withPhpSets(php81: true)
    ->withPreparedSets(
        codeQuality: true,
    );
