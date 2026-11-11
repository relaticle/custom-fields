<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands;

use Illuminate\Console\Command;
use Relaticle\CustomFields\EntitySystem\EntityManager;

/**
 * @internal
 */
final class ClearCacheCommand extends Command
{
    /** @var string */
    protected $signature = 'custom-fields:clear-cache';

    /** @var string */
    protected $description = 'Clear the cached custom fields entity registry';

    public function handle(EntityManager $entityManager): int
    {
        $entityManager->clearCache();

        $this->components->info('Custom fields entity cache cleared.');

        return self::SUCCESS;
    }
}
