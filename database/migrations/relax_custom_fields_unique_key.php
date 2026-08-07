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
        $this->swapUniqueKey(
            from: $this->narrowColumns(),
            fromIndexName: null,
            to: $this->wideColumns(),
            toIndexName: $this->wideIndexName(),
        );
    }

    public function down(): void
    {
        $this->swapUniqueKey(
            from: $this->wideColumns(),
            fromIndexName: $this->wideIndexName(),
            to: $this->narrowColumns(),
            toIndexName: null,
        );
    }

    /**
     * Drops $from's unique key if present and adds $to's if absent. Shared by both
     * directions: up() widens (code, entity_type[, tenant]) to also include
     * custom_field_section_id; down() narrows it back to the original key.
     *
     * @param  array<int, string>  $from
     * @param  array<int, string>  $to
     */
    private function swapUniqueKey(array $from, ?string $fromIndexName, array $to, ?string $toIndexName): void
    {
        $table = config('custom-fields.database.table_names.custom_fields');

        if (! Schema::hasColumn($table, 'custom_field_section_id')) {
            return;
        }

        $fromIndexName ??= $this->defaultUniqueIndexName($table, $from);
        $toIndexName ??= $this->defaultUniqueIndexName($table, $to);
        $existingIndexes = collect(Schema::getIndexes($table))->pluck('name');

        Schema::table($table, function (Blueprint $blueprint) use ($existingIndexes, $fromIndexName, $to, $toIndexName): void {
            if ($existingIndexes->contains($fromIndexName)) {
                $blueprint->dropUnique($fromIndexName);
            }

            if (! $existingIndexes->contains($toIndexName)) {
                $blueprint->unique($to, $toIndexName);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function narrowColumns(): array
    {
        $columns = ['code', 'entity_type'];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $columns[] = config('custom-fields.database.column_names.tenant_foreign_key');
        }

        return $columns;
    }

    /**
     * @return array<int, string>
     */
    private function wideColumns(): array
    {
        return [...$this->narrowColumns(), 'custom_field_section_id'];
    }

    private function wideIndexName(): string
    {
        return FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)
            ? 'cf_code_entity_tenant_section_unique'
            : 'cf_code_entity_section_unique';
    }

    /**
     * Mirrors Laravel's own auto-generated unique-index name (Blueprint::createIndexName())
     * so the drop target matches exactly what create_custom_fields_table.php produced,
     * without hardcoding a name that would drift if a configured column name changes.
     *
     * @param  array<int, string>  $columns
     */
    private function defaultUniqueIndexName(string $table, array $columns): string
    {
        $index = strtolower($table.'_'.implode('_', $columns).'_unique');

        return str_replace(['-', '.'], '_', $index);
    }
};
