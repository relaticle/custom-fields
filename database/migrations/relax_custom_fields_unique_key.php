<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

    public function down(): void
    {
        $this->assertNoDuplicatesUnderNarrowKey();

        $this->swapUniqueKey(
            from: $this->wideColumns(),
            fromIndexName: $this->wideIndexName(),
            to: $this->narrowColumns(),
            toIndexName: null,
        );
    }

    /**
     * MySQL runs each ALTER TABLE as its own auto-committing DDL statement, so dropping
     * the wide key and adding the narrow one are not transactional together. If rows exist
     * that share (code, entity_type[, tenant]) across different sections — exactly the
     * shape the wide key exists to allow — the DROP succeeds and the subsequent ADD fails
     * on the duplicate, leaving the table with neither unique key. Check first and abort
     * before touching anything.
     */
    private function assertNoDuplicatesUnderNarrowKey(): void
    {
        $table = config('custom-fields.database.table_names.custom_fields');

        if (! Schema::hasColumn($table, 'custom_field_section_id')) {
            return;
        }

        $columns = $this->narrowColumns();

        $duplicateCodes = DB::table($table)
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('count(*) > 1')
            ->pluck('code');

        if ($duplicateCodes->isEmpty()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Cannot roll back the custom_fields unique key: %d code(s) — including "%s" — are shared by more than one row for the same (%s), only differing by custom_field_section_id. onlySections() allows this under the wide key, but the narrow key being restored cannot. Resolve or remove the duplicate rows before rolling back this migration.',
            $duplicateCodes->count(),
            $duplicateCodes->first(),
            implode(', ', $columns)
        ));
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
