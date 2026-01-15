<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Throwable;

/**
 * Clears relevant caches after upgrade.
 */
final class ClearCachesStep implements UpgradeStep
{
    public function name(): string
    {
        return 'Clear Caches';
    }

    public function description(): string
    {
        return 'Clear config and application caches';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        if ($dryRun) {
            $command->line('  Would clear: config cache, application cache, custom-fields cache');

            return UpgradeStepResult::success(3);
        }

        $cleared = 0;
        $errors = [];

        // Clear config cache
        try {
            Artisan::call('config:clear');
            $command->line('  <info>✓</info> Config cache cleared');
            $cleared++;
        } catch (Throwable $throwable) {
            $errors[] = 'Config cache: '.$throwable->getMessage();
            $command->line(sprintf('  <error>✗</error> Config cache: %s', $throwable->getMessage()));
        }

        // Clear application cache
        try {
            Artisan::call('cache:clear');
            $command->line('  <info>✓</info> Application cache cleared');
            $cleared++;
        } catch (Throwable $throwable) {
            $errors[] = 'Application cache: '.$throwable->getMessage();
            $command->line(sprintf('  <error>✗</error> Application cache: %s', $throwable->getMessage()));
        }

        // Clear custom-fields specific cache keys
        try {
            Cache::forget('custom-fields:entity-registry');
            Cache::forget('custom-fields:field-types');
            $command->line('  <info>✓</info> Custom-fields cache cleared');
            $cleared++;
        } catch (Throwable $throwable) {
            $errors[] = 'Custom-fields cache: '.$throwable->getMessage();
            $command->line(sprintf('  <error>✗</error> Custom-fields cache: %s', $throwable->getMessage()));
        }

        return new UpgradeStepResult(
            success: $errors === [],
            itemsProcessed: $cleared,
            itemsFailed: count($errors),
            errors: $errors,
        );
    }
}
