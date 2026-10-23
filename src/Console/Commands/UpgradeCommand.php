<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands;

use Illuminate\Console\Command;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\ClearCachesStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\MigrateRecordLinksStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\PurgeMigratedRecordValuesStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\ValidateSchemaStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;

final class UpgradeCommand extends Command
{
    /** @var string */
    protected $signature = 'custom-fields:upgrade
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Run without confirmation prompts}
                            {--purge : Also delete the record values the links step has migrated}
                            {--skip= : Skip specific steps (comma-separated: validate-schema,migrate-record-links,purge-record-values,clear-caches)}';

    /** @var string */
    protected $description = 'Run the registered custom-fields upgrade steps';

    public const string STEP_MIGRATE_RECORD_LINKS = 'migrate-record-links';

    public const string STEP_PURGE_RECORD_VALUES = 'purge-record-values';

    /** @var array<string, class-string<UpgradeStep>> */
    private const STEPS = [
        'validate-schema' => ValidateSchemaStep::class,
        self::STEP_MIGRATE_RECORD_LINKS => MigrateRecordLinksStep::class,
        self::STEP_PURGE_RECORD_VALUES => PurgeMigratedRecordValuesStep::class,
        'clear-caches' => ClearCachesStep::class,
    ];

    public function handle(): int
    {
        $this->displayHeader();

        $isDryRun = (bool) $this->option('dry-run');
        $isForced = (bool) $this->option('force');
        $stepsToSkip = $this->getSkippedSteps();

        $unknownSteps = array_diff($stepsToSkip, array_keys(self::STEPS));

        if ($unknownSteps !== []) {
            $this->line(sprintf('<error>Unknown --skip value(s): %s.</error>', implode(', ', $unknownSteps)));
            $this->line(sprintf('Valid steps: %s.', implode(', ', array_keys(self::STEPS))));

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        if (! $isForced && ! $isDryRun && ! $this->confirm('This will upgrade your custom-fields data. Continue?', true)) {
            $this->info('Upgrade cancelled.');

            return self::SUCCESS;
        }

        $results = $this->runSteps($isDryRun);
        $this->displaySummary($results, $isDryRun);

        return $this->hasErrors($results) ? self::FAILURE : self::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>Custom Fields Upgrade</>');
        $this->line(str_repeat('=', 40));
        $this->newLine();
    }

    /**
     * Whether a step runs in this invocation. The purge is opt-in: it deletes the store the
     * migration was copied from, so nothing but --purge may start it.
     */
    public function willRun(string $step): bool
    {
        if ($step === self::STEP_PURGE_RECORD_VALUES && ! $this->option('purge')) {
            return false;
        }

        return ! in_array($step, $this->getSkippedSteps(), true);
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

        $values = array_map('trim', explode(',', $skipOption));

        // Only empty elements are dropped: a bare array_filter() also swallows "0", which
        // would then reach no step and no unknown-value error either.
        return array_values(array_unique(array_filter($values, fn (string $value): bool => $value !== '')));
    }

    /**
     * @return array<string, UpgradeStepResult>
     */
    private function runSteps(bool $isDryRun): array
    {
        $results = [];
        $stepNumber = 1;
        $steps = array_filter(self::STEPS, fn (string $stepClass, string $key): bool => $this->willRun($key), ARRAY_FILTER_USE_BOTH);
        $totalSteps = count($steps);

        foreach (self::STEPS as $key => $stepClass) {
            if (! array_key_exists($key, $steps)) {
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

            // Every later step reads what an earlier one wrote, and the purge deletes the
            // store the migration copies from, so a failure ends the run rather than
            // handing the next step a state it was told not to trust.
            if (! $result->success) {
                $this->line(sprintf('<error>Stopping: %s failed.</error>', $key));
                $this->newLine();

                break;
            }

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

        $this->newLine();
        $this->line('  See: https://relaticle.github.io/custom-fields/getting-started/upgrade-guide');
    }

    /**
     * @param  array<string, UpgradeStepResult>  $results
     */
    private function hasErrors(array $results): bool
    {
        return collect($results)->contains(fn (UpgradeStepResult $result): bool => ! $result->success);
    }
}
