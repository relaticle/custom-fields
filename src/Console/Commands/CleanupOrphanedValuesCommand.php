<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Models\CustomFieldValue;

final class CleanupOrphanedValuesCommand extends Command
{
    /** @var string */
    protected $signature = 'custom-fields:cleanup-orphaned-values
                            {--dry-run : Show what would be deleted without making changes}
                            {--force : Run without confirmation prompts}';

    /** @var string */
    protected $description = 'Remove custom field values whose parent entity no longer exists';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForced = (bool) $this->option('force');

        if ($isDryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        $table = (new CustomFieldValue)->getTable();
        $entityTypes = DB::table($table)
            ->select('entity_type')
            ->distinct()
            ->pluck('entity_type');

        if ($entityTypes->isEmpty()) {
            $this->info('No custom field values found.');

            return self::SUCCESS;
        }

        $morphMap = Relation::morphMap();
        $totalOrphaned = 0;
        $rows = [];

        foreach ($entityTypes as $type) {
            $class = $morphMap[$type] ?? $type;

            if (! class_exists($class)) {
                $this->warn('Skipping unknown entity type: '.$type);

                continue;
            }

            /** @var Model $model */
            $model = new $class;
            $entityTable = $model->getTable();

            $orphanedCount = DB::table($table)
                ->where('entity_type', $type)
                ->whereNotExists(function ($query) use ($entityTable): void {
                    $query->select(DB::raw(1))
                        ->from($entityTable)
                        ->whereColumn($entityTable.'.id', 'custom_field_values.entity_id');
                })
                ->count();

            if ($orphanedCount > 0) {
                $rows[] = [$type, $orphanedCount];
                $totalOrphaned += $orphanedCount;
            }
        }

        if ($totalOrphaned === 0) {
            $this->info('No orphaned custom field values found.');

            return self::SUCCESS;
        }

        $this->table(['Entity Type', 'Orphaned Values'], $rows);
        $this->newLine();
        $this->line('Total orphaned values: '.$totalOrphaned);

        if ($isDryRun) {
            $this->newLine();
            $this->info('DRY RUN COMPLETE - No changes were made');

            return self::SUCCESS;
        }

        if (! $isForced && ! $this->confirm('Delete these orphaned values?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($rows as [$type]) {
            $class = $morphMap[$type] ?? $type;
            /** @var Model $model */
            $model = new $class;
            $entityTable = $model->getTable();

            $count = DB::table($table)
                ->where('entity_type', $type)
                ->whereNotExists(function ($query) use ($entityTable): void {
                    $query->select(DB::raw(1))
                        ->from($entityTable)
                        ->whereColumn($entityTable.'.id', 'custom_field_values.entity_id');
                })
                ->delete();

            $this->info(sprintf('Deleted %d orphaned values for %s.', $count, $type));
            $deleted += $count;
        }

        $this->newLine();
        $this->comment(sprintf('Cleaned up %d orphaned custom field values.', $deleted));

        return self::SUCCESS;
    }
}
