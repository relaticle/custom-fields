<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Migrates email field values from string_value to json_value format.
 *
 * This command is needed after the EmailFieldType was changed from STRING
 * data type to MULTI_CHOICE, which stores values in json_value as an array.
 */
class MigrateEmailFieldValuesCommand extends Command
{
    protected $signature = 'custom-fields:migrate-email-values
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Run without confirmation in production}';

    protected $description = 'Migrate email field values from string_value to json_value array format';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if (app()->isProduction() && ! $isDryRun && ! $this->option('force') && ! $this->confirm('You are running in production. Are you sure you want to continue?')) {
            $this->info('Migration cancelled.');

            return self::SUCCESS;
        }

        $fieldTable = config('custom-fields.database.table_names.custom_fields');
        $valueTable = config('custom-fields.database.table_names.custom_field_values');

        // Find all email type custom fields
        $emailFields = DB::table($fieldTable)
            ->where('type', 'email')
            ->pluck('id');

        if ($emailFields->isEmpty()) {
            $this->info('No email fields found. Nothing to migrate.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d email field(s).', $emailFields->count()));

        // Find values that need migration (have string_value but no json_value)
        $valuesToMigrate = DB::table($valueTable)
            ->whereIn('custom_field_id', $emailFields)
            ->whereNotNull('string_value')
            ->where('string_value', '!=', '')
            ->where(function ($query): void {
                $query->whereNull('json_value')
                    ->orWhere('json_value', '=', '[]')
                    ->orWhere('json_value', '=', 'null');
            })
            ->get();

        if ($valuesToMigrate->isEmpty()) {
            $this->info('No email values need migration. All values are already in the correct format.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d email value(s) to migrate.', $valuesToMigrate->count()));

        if ($isDryRun) {
            $this->warn('Dry run mode - no changes will be made.');
            $this->newLine();

            $this->table(
                ['ID', 'Entity Type', 'Entity ID', 'Current Value', 'New Format'],
                $valuesToMigrate->map(fn ($value): array => [
                    $value->id,
                    $value->entity_type,
                    $value->entity_id,
                    $value->string_value,
                    json_encode([$value->string_value]),
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($valuesToMigrate->count());
        $bar->start();

        $migrated = 0;
        $errors = 0;

        foreach ($valuesToMigrate as $value) {
            try {
                DB::table($valueTable)
                    ->where('id', $value->id)
                    ->update([
                        'json_value' => json_encode([$value->string_value]),
                        'string_value' => null,
                    ]);

                $migrated++;
            } catch (Throwable $e) {
                $this->newLine();
                $this->error(sprintf('Failed to migrate value ID %d: %s', $value->id, $e->getMessage()));
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf('Migration complete. Migrated: %d, Errors: %d', $migrated, $errors));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
