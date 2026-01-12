<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands;

use Illuminate\Console\Command;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\ClearCachesStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\MigrateEmailFormatStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\MigrateLookupFieldsStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\MigratePhoneFormatStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\ValidateSchemaStep;

/**
 * Main upgrade command for custom-fields 2.x → 3.x migration.
 */
final class UpgradeCommand extends Command
{
    /** @var string */
    protected $signature = 'custom-fields:upgrade
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Run without confirmation prompts}
                            {--skip= : Skip specific steps (comma-separated: lookup-fields,email-format,phone-format,validate-schema,clear-caches)}';

    /** @var string */
    protected $description = 'Upgrade custom-fields data from 2.x to 3.x';

    /** @var array<string, class-string<UpgradeStep>> */
    private const STEPS = [
        'lookup-fields' => MigrateLookupFieldsStep::class,
        'email-format' => MigrateEmailFormatStep::class,
        'phone-format' => MigratePhoneFormatStep::class,
        'validate-schema' => ValidateSchemaStep::class,
        'clear-caches' => ClearCachesStep::class,
    ];

    public function handle(): int
    {
        $this->displayHeader();

        $isDryRun = (bool) $this->option('dry-run');
        $isForced = (bool) $this->option('force');
        $stepsToSkip = $this->getSkippedSteps();

        if ($isDryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        if (! $isForced && ! $isDryRun && ! $this->confirm('This will upgrade your custom-fields data. Continue?', true)) {
            $this->info('Upgrade cancelled.');

            return self::SUCCESS;
        }

        $results = $this->runSteps($isDryRun, $stepsToSkip);
        $this->displaySummary($results, $isDryRun);

        return $this->hasErrors($results) ? self::FAILURE : self::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>Custom Fields Upgrade: 2.x → 3.x</>');
        $this->line(str_repeat('=', 40));
        $this->newLine();
    }

    /**
     * @return list<string>
     */
    private function getSkippedSteps(): array
    {
        $skipOption = $this->option('skip');

        if (! is_string($skipOption) || $skipOption === '') {
            return [];
        }

        return array_map('trim', explode(',', $skipOption));
    }

    /**
     * @param  list<string>  $stepsToSkip
     * @return array<string, UpgradeStepResult>
     */
    private function runSteps(bool $isDryRun, array $stepsToSkip): array
    {
        $results = [];
        $stepNumber = 1;
        $activeSteps = array_diff_key(self::STEPS, array_flip($stepsToSkip));
        $totalSteps = count($activeSteps);

        foreach (self::STEPS as $key => $stepClass) {
            if (in_array($key, $stepsToSkip, true)) {
                $this->line(sprintf('<comment>Skipping: %s</comment>', $key));
                $this->newLine();

                continue;
            }

            /** @var UpgradeStep $step */
            $step = app($stepClass);

            $this->displayStepHeader($stepNumber, $totalSteps, $step);

            $result = $step->execute($isDryRun, $this);
            $results[$key] = $result;

            $this->displayStepResult($result);
            $this->newLine();

            $stepNumber++;
        }

        return $results;
    }

    private function displayStepHeader(int $stepNumber, int $totalSteps, UpgradeStep $step): void
    {
        $this->line(sprintf(
            '<fg=white>Step %d/%d: %s</>',
            $stepNumber,
            $totalSteps,
            $step->name()
        ));
        $this->line(str_repeat('-', 50));
        $this->line(sprintf('  <fg=gray>%s</>', $step->description()));
    }

    private function displayStepResult(UpgradeStepResult $result): void
    {
        foreach ($result->warnings as $warning) {
            $this->line(sprintf('  <comment>○</comment> %s', $warning));
        }

        foreach ($result->errors as $error) {
            $this->line(sprintf('  <error>✗</error> %s', $error));
        }

        if ($result->success && $result->itemsProcessed > 0) {
            $this->line(sprintf(
                '  <info>Completed:</info> %d processed%s',
                $result->itemsProcessed,
                $result->itemsFailed > 0 ? sprintf(', %d failed', $result->itemsFailed) : ''
            ));
        }
    }

    /**
     * @param  array<string, UpgradeStepResult>  $results
     */
    private function displaySummary(array $results, bool $isDryRun): void
    {
        $this->newLine();
        $this->line(str_repeat('═', 50));

        $hasErrors = $this->hasErrors($results);

        if ($isDryRun) {
            $this->info('DRY RUN COMPLETE - No changes were made');
        } elseif ($hasErrors) {
            $this->error('UPGRADE COMPLETED WITH ERRORS');
        } else {
            $this->info('UPGRADE COMPLETE');
        }

        $this->line(str_repeat('═', 50));

        // Summary stats
        $totalProcessed = 0;
        $totalFailed = 0;
        foreach ($results as $result) {
            $totalProcessed += $result->itemsProcessed;
            $totalFailed += $result->itemsFailed;
        }

        $this->newLine();
        $this->line(sprintf('  Total items processed: %d', $totalProcessed));
        if ($totalFailed > 0) {
            $this->line(sprintf('  <error>Total items failed: %d</error>', $totalFailed));
        }

        // Manual action reminder
        $this->newLine();
        $this->warn('Manual Action Required:');
        $this->line('  Update config/custom-fields.php to use the new format if needed.');
        $this->line('  See: https://docs.relaticle.com/custom-fields/upgrade-3x');
    }

    /**
     * @param  array<string, UpgradeStepResult>  $results
     */
    private function hasErrors(array $results): bool
    {
        foreach ($results as $result) {
            if (! $result->success) {
                return true;
            }
        }

        return false;
    }
}
