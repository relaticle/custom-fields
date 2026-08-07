<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;

/*
 * `onlySections()` (see BaseBuilder::onlySections()) lets consumers that version their
 * form definitions scope custom-field resolution by section id instead of by code, so a
 * cloned section can carry a field whose code already exists on its sibling section. The
 * unique key created by create_custom_fields_table.php — (code, entity_type[, tenant]) —
 * blocks exactly that data shape at the database level, so any consumer of onlySections()
 * would fail to save the second field unless this key is widened to also include
 * custom_field_section_id.
 *
 * custom_field_sections is deliberately left untouched: onlySections() scopes by section
 * id, never by section code, so nothing here requires sections to share a code.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('custom-fields.database.table_names.custom_fields');

        if (! Schema::hasColumn($table, 'custom_field_section_id')) {
            return;
        }

        $oldColumns = ['code', 'entity_type'];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $oldColumns[] = config('custom-fields.database.column_names.tenant_foreign_key');
        }

        $newColumns = [...$oldColumns, 'custom_field_section_id'];
        $newIndexName = FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)
            ? 'cf_code_entity_tenant_section_unique'
            : 'cf_code_entity_section_unique';

        $oldIndexName = $this->defaultUniqueIndexName($table, $oldColumns);
        $existingIndexes = collect(Schema::getIndexes($table))->pluck('name');

        Schema::table($table, function (Blueprint $blueprint) use ($existingIndexes, $oldColumns, $oldIndexName, $newColumns, $newIndexName): void {
            if ($existingIndexes->contains($oldIndexName)) {
                $blueprint->dropUnique($oldColumns);
            }

            if (! $existingIndexes->contains($newIndexName)) {
                $blueprint->unique($newColumns, $newIndexName);
            }
        });
    }

    /**
     * Mirrors Laravel's own auto-generated unique-index name so the drop target matches
     * exactly what create_custom_fields_table.php produced, without hardcoding a name that
     * would drift if the tenant foreign key column is renamed via config.
     *
     * @param  array<int, string>  $columns
     */
    private function defaultUniqueIndexName(string $table, array $columns): string
    {
        return strtolower($table.'_'.implode('_', $columns).'_unique');
    }
};
