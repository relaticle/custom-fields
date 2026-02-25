<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Models\CustomField;
use Throwable;

/**
 * Migrates lookup-based field types (select, multi-select, etc.) to the Record field type.
 */
final class MigrateLookupFieldsStep implements UpgradeStep
{
    /** @var list<string> */
    private const LEGACY_LOOKUP_TYPES = ['select', 'multi-select', 'radio', 'checkbox-list', 'tags-input', 'toggle-buttons'];

    /** @var list<string> */
    private const MULTI_VALUE_TYPES = ['multi-select', 'checkbox-list', 'tags-input'];

    public function name(): string
    {
        return 'Migrate Lookup Fields';
    }

    public function description(): string
    {
        return 'Convert lookup-based fields (select, multi-select, radio, etc.) to Record field type';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        $customFieldModel = CustomFields::newCustomFieldModel();

        $fieldsToMigrate = $customFieldModel::query()
            ->withoutGlobalScopes()
            ->whereIn('type', self::LEGACY_LOOKUP_TYPES)
            ->whereNotNull('lookup_type')
            ->where('lookup_type', '!=', '')
            ->get();

        if ($fieldsToMigrate->isEmpty()) {
            return UpgradeStepResult::skipped('No legacy lookup fields found');
        }

        $command->table(
            ['ID', 'Name', 'Current Type', 'Lookup Type', 'Will Become'],
            $fieldsToMigrate->map(fn (CustomField $field): array => [
                $field->getKey(),
                $field->name,
                $field->type,
                $field->lookup_type,
                'record'.(in_array($field->type, self::MULTI_VALUE_TYPES, true) ? ' (multi)' : ' (single)'),
            ])->toArray()
        );

        if ($dryRun) {
            return UpgradeStepResult::success($fieldsToMigrate->count());
        }

        $migrated = 0;
        $failed = 0;
        $errors = [];

        foreach ($fieldsToMigrate as $field) {
            try {
                $originalType = $field->type;
                $isMultiValue = in_array($originalType, self::MULTI_VALUE_TYPES, true);

                $field->type = 'record';

                $settings = $field->settings instanceof CustomFieldSettingsData
                    ? $field->settings
                    : new CustomFieldSettingsData;

                $settings->allow_multiple = $isMultiValue;
                $field->settings = $settings;

                $field->saveQuietly();

                $command->line(sprintf(
                    '  <info>✓</info> %s: %s → record%s',
                    $field->name,
                    $originalType,
                    $isMultiValue ? ' (allow_multiple=true)' : ''
                ));

                $migrated++;
            } catch (Throwable $e) {
                $command->line(sprintf('  <error>✗</error> %s: %s', $field->name, $e->getMessage()));
                $errors[] = sprintf('%s: %s', $field->name, $e->getMessage());
                $failed++;
            }
        }

        return new UpgradeStepResult(
            success: $failed === 0,
            itemsProcessed: $migrated,
            itemsFailed: $failed,
            errors: $errors,
        );
    }
}
