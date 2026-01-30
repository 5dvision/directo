<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPHPStanConfigs([
        __DIR__ . '/phpstan.neon'
    ])
    ->withCache(
        cacheDirectory: __DIR__ . '/.phpunit.cache/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        codingStyle: true,
    )
    ->withPhpSets();
