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
 *
 * custom_field_section_id is nullable, and both MySQL and Postgres treat NULL as distinct
 * in a unique index — including within a composite one. So after this migration, two rows
 * that both have custom_field_section_id IS NULL can still share (code, entity_type[,
 * tenant]): the wide key does not constrain them, because NULL never equals NULL for
 * uniqueness purposes. That's a protection existing installs have today (every row is
 * globally unique per entity type) and silently lose once this ships. There is no schema
 * workaround for this — a consumer that needs collision protection for sectionless fields
 * must keep them out of this data shape or enforce it at the application layer.
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

    /**
     * Drops $from's unique key if present and adds $to's if absent.
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
     * Laravel's shipped config/database.php enables `prefix_indexes` for mysql and pgsql,
     * which makes Blueprint::createIndexName() fold the connection's table prefix into the
     * name it generates. Skipping that step here would compute a drop target that never
     * matches the real index name on a prefixed install, so the drop would silently no-op.
     *
     * @param  array<int, string>  $columns
     */
    private function defaultUniqueIndexName(string $table, array $columns): string
    {
        $connection = Schema::getConnection();

        $prefixedTable = $connection->getConfig('prefix_indexes')
            ? $this->applyTablePrefix($table, $connection->getTablePrefix())
            : $table;

        $index = strtolower($prefixedTable.'_'.implode('_', $columns).'_unique');

        return str_replace(['-', '.'], '_', $index);
    }

    private function applyTablePrefix(string $table, string $prefix): string
    {
        return str_contains($table, '.')
            ? substr_replace($table, '.'.$prefix, strrpos($table, '.'), 1)
            : $prefix.$table;
    }
};
