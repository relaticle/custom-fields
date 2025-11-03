<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;

// Specific rules are handled by the sets

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/tests/Fixtures',
        __DIR__.'/tests/database',
        __DIR__.'/vendor',
        RemoveUnusedVariableAssignRector::class => [
            __DIR__.'/tests', // we like unused variables in tests for clear naming
        ],
    ])
    ->withImportNames()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
    );
