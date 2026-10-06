<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Console\Commands\Upgrade\UnmigratedRecordFields;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldValue;

/**
 * Drops the value rows the links step copied. Opt-in (--purge) and always last, so a host
 * verifies the ledger against the old store before the old store goes.
 */
final class PurgeMigratedRecordValuesStep implements UpgradeStep
{
    public function __construct(private readonly UnmigratedRecordFields $unmigrated) {}

    public function name(): string
    {
        return 'Purge Migrated Record Values';
    }

    public function description(): string
    {
        return 'Delete the json_value rows of record fields now backed by links';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_RELATIONSHIPS)) {
            return UpgradeStepResult::skipped('The relationships feature is disabled, so record fields keep their value rows.');
        }

        $unmigrated = $this->unmigrated->codes();

        if ($unmigrated !== []) {
            return UpgradeStepResult::failed(sprintf(
                'Nothing is purged while %s still store links in json_value: run the Migrate Record Links step first.',
                implode(', ', $unmigrated),
            ));
        }

        $purged = 0;

        foreach ($this->migratedFields() as $field) {
            $command->line(sprintf('  Purging value rows of <fg=white>%s</>...', $field->code));

            $values = $this->values($field);
            $rows = $dryRun ? $values->count() : $values->delete();

            $purged += $rows;
            $command->line(sprintf('  <info>✓</info> %s: %d row(s)%s', $field->code, $rows, $dryRun ? ' would be deleted' : ' deleted'));
        }

        return UpgradeStepResult::success($purged);
    }

    /**
     * Every record field is definition-backed by now, the guard above having said so.
     *
     * @return array<int, CustomField>
     */
    private function migratedFields(): array
    {
        $fieldsTable = (string) config('custom-fields.database.table_names.custom_fields');

        if (! Schema::hasTable($fieldsTable)) {
            return [];
        }

        return CustomFields::newCustomFieldModel()
            ->newQuery()
            ->withoutGlobalScopes()
            ->where('type', 'record')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return Builder<CustomFieldValue>
     */
    private function values(CustomField $field): Builder
    {
        return CustomFields::newValueModel()
            ->newQuery()
            ->withoutGlobalScopes()
            ->where('custom_field_id', $field->getKey())
            ->whereNotNull('json_value');
    }
}
