<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\CustomFields;
use Throwable;

/**
 * Migrates email field values from single string format (v2) to array format (v3).
 */
final class MigrateEmailFormatStep implements UpgradeStep
{
    public function name(): string
    {
        return 'Migrate Email Format';
    }

    public function description(): string
    {
        return 'Convert email field values from single string to array format';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        $customFieldModel = CustomFields::newCustomFieldModel();
        $customFieldValueModel = CustomFields::newValueModel();

        $emailFields = $customFieldModel::query()
            ->withoutGlobalScopes()
            ->where('type', 'email')
            ->get();

        if ($emailFields->isEmpty()) {
            return UpgradeStepResult::skipped('No email fields found');
        }

        $valuesToMigrate = $customFieldValueModel::query()
            ->withoutGlobalScopes()
            ->whereIn('custom_field_id', $emailFields->pluck('id'))
            ->whereNotNull('string_value')
            ->where('string_value', '!=', '')
            ->whereNull('json_value')
            ->get();

        if ($valuesToMigrate->isEmpty()) {
            return UpgradeStepResult::skipped('No legacy email values found');
        }

        $command->line(sprintf('  Found %d email value(s) to migrate', $valuesToMigrate->count()));

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

        $command->line(sprintf('  <info>✓</info> Migrated %d email value(s)', $migrated));

        return new UpgradeStepResult(
            success: $failed === 0,
            itemsProcessed: $migrated,
            itemsFailed: $failed,
            errors: $errors,
        );
    }
}
