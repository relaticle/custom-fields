<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\CustomFields;
use Throwable;

/**
 * Base class for migrating field values from single string format (v2) to array format (v3).
 */
abstract class MigrateStringToJsonFormatStep implements UpgradeStep
{
    abstract protected function fieldType(): string;

    abstract protected function fieldTypeLabel(): string;

    public function name(): string
    {
        return "Migrate {$this->fieldTypeLabel()} Format";
    }

    public function description(): string
    {
        return "Convert {$this->fieldType()} field values from single string to array format";
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        $customFieldModel = CustomFields::newCustomFieldModel();
        $customFieldValueModel = CustomFields::newValueModel();
        $fieldType = $this->fieldType();

        $fields = $customFieldModel::query()
            ->withoutGlobalScopes()
            ->where('type', $fieldType)
            ->get();

        if ($fields->isEmpty()) {
            return UpgradeStepResult::skipped("No {$fieldType} fields found");
        }

        $valuesToMigrate = $customFieldValueModel::query()
            ->withoutGlobalScopes()
            ->whereIn('custom_field_id', $fields->pluck('id'))
            ->whereNotNull('string_value')
            ->where('string_value', '!=', '')
            ->whereNull('json_value')
            ->get();

        if ($valuesToMigrate->isEmpty()) {
            return UpgradeStepResult::skipped("No legacy {$fieldType} values found");
        }

        $command->line(sprintf('  Found %d %s value(s) to migrate', $valuesToMigrate->count(), $fieldType));

        if ($dryRun) {
            return UpgradeStepResult::success($valuesToMigrate->count());
        }

        $migrated = 0;
        $failed = 0;
        $errors = [];

        foreach ($valuesToMigrate as $value) {
            try {
                $originalValue = $value->string_value;

                $value->json_value = [$originalValue];
                $value->string_value = null;
                $value->save();

                $migrated++;
            } catch (Throwable $e) {
                $command->line(sprintf('  <error>✗</error> Value ID %s: %s', $value->id, $e->getMessage()));
                $errors[] = sprintf('Value ID %s: %s', $value->id, $e->getMessage());
                $failed++;
            }
        }

        $command->line(sprintf('  <info>✓</info> Migrated %d %s value(s)', $migrated, $fieldType));

        return new UpgradeStepResult(
            success: $failed === 0,
            itemsProcessed: $migrated,
            itemsFailed: $failed,
            errors: $errors,
        );
    }
}
