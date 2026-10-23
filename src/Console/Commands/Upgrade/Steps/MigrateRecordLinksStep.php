<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Console\Commands\Upgrade\UnmigratedRecordFields;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldValue;

/**
 * Moves 3.x record links out of json_value: a definition per record field, an edge per id.
 * The value rows are kept, so a host can compare both stores and roll back until the
 * separate purge step runs.
 */
final class MigrateRecordLinksStep implements UpgradeStep
{
    private const int CHUNK_SIZE = 500;

    public function __construct(private readonly UnmigratedRecordFields $records) {}

    public function name(): string
    {
        return 'Migrate Record Links';
    }

    public function description(): string
    {
        return 'Move record-type field values onto relationship definitions and links; each field stays a Record field';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_RELATIONSHIPS)) {
            return UpgradeStepResult::skipped('The relationships feature is disabled, so record fields keep their value rows.');
        }

        if (! Schema::hasTable($this->table('custom_field_relationships')) || ! Schema::hasTable($this->table('custom_field_links'))) {
            return UpgradeStepResult::skipped(sprintf(
                'Tables %s and %s do not exist yet: publish and run the relationship migrations first.',
                $this->table('custom_field_relationships'),
                $this->table('custom_field_links'),
            ));
        }

        $created = 0;
        $failed = 0;
        $warnings = [];

        foreach ($this->records->recordFields() as $field) {
            $command->line(sprintf('  Migrating record field <fg=white>%s</>...', $field->code));

            $definition = $this->records->definitionFor($field);

            if (! $definition instanceof CustomFieldRelationship && blank($this->records->legacyTargetEntityType($field))) {
                // A field that never stored a link has nothing to lose by having no target,
                // so only one holding values stops the upgrade.
                if (! $this->records->values($field)->exists()) {
                    $warnings[] = sprintf("Field '%s' has no lookup type and no values, so there is nothing to migrate", $field->code);
                    $command->line(sprintf('  <comment>○</comment> %s: no lookup type and no values, skipped', $field->code));

                    continue;
                }

                $failed++;
                $warnings[] = sprintf("Field '%s' holds values but has no lookup type, so its target is unknown", $field->code);
                $command->line(sprintf('  <error>✗</error> %s: values with no lookup type', $field->code));

                continue;
            }

            // Legacy values are always written from the record that holds the field, so a
            // field reading the far end of its definition would migrate to reversed edges.
            if ($definition instanceof CustomFieldRelationship && (string) $definition->from_field_id !== (string) $field->getKey()) {
                $failed++;
                $warnings[] = sprintf("Field '%s' reads the to end of relationship '%s', so its value rows need migrating by hand", $field->code, $definition->code);
                $command->line(sprintf('  <comment>○</comment> %s: reads the to end of %s, skipped', $field->code, $definition->code));

                continue;
            }

            $dangling = 0;

            $links = $dryRun
                ? $this->countLinks($field, $definition, $dangling)
                : $this->migrate($field, $definition, $dangling);

            $created += $links;
            $command->line(sprintf('  <info>✓</info> %s: %d link(s)%s', $field->code, $links, $dryRun ? ' would be created' : ' created'));

            if ($dangling > 0) {
                $warnings[] = sprintf("Field '%s': %d id(s) point at rows that no longer exist and were skipped", $field->code, $dangling);
                $command->line(sprintf('  <comment>○</comment> %s: %d id(s) point at missing rows, skipped', $field->code, $dangling));
            }
        }

        return new UpgradeStepResult(
            success: $failed === 0,
            itemsProcessed: $created,
            itemsFailed: $failed,
            warnings: $warnings,
        );
    }

    private function migrate(CustomField $field, ?CustomFieldRelationship $definition, int &$dangling): int
    {
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($field, $definition, &$created, &$skipped): void {
            $definition ??= $this->createDefinition($field);

            $this->records->values($field)->chunkById(self::CHUNK_SIZE, function (EloquentCollection $values) use ($definition, $field, &$created, &$skipped): void {
                $ledger = $this->records->ledgerTargets($definition, $values);
                $reachable = $this->reachableTargets($definition->to_entity_type, $values);

                foreach ($values as $value) {
                    foreach ($this->records->targets($value) as $index => $targetId) {
                        if (in_array($targetId, $ledger[(string) $value->entity_id] ?? [], true)) {
                            continue;
                        }

                        if (! in_array($targetId, $reachable, true)) {
                            $skipped++;

                            continue;
                        }

                        $this->insert($definition, $field, $value, $targetId, $index);
                        $ledger[(string) $value->entity_id][] = $targetId;
                        $created++;
                    }
                }
            });
        });

        $dangling += $skipped;

        return $created;
    }

    private function countLinks(CustomField $field, ?CustomFieldRelationship $definition, int &$dangling): int
    {
        $counted = 0;
        $skipped = 0;
        $targetType = $definition instanceof CustomFieldRelationship
            ? $definition->to_entity_type
            : $this->records->legacyTargetEntityType($field);

        $this->records->values($field)->chunkById(self::CHUNK_SIZE, function (EloquentCollection $values) use ($definition, $targetType, &$counted, &$skipped): void {
            $ledger = $definition instanceof CustomFieldRelationship ? $this->records->ledgerTargets($definition, $values) : [];
            $reachable = $this->reachableTargets($targetType, $values);

            foreach ($values as $value) {
                foreach ($this->records->targets($value) as $targetId) {
                    if (in_array($targetId, $ledger[(string) $value->entity_id] ?? [], true)) {
                        continue;
                    }

                    if (! in_array($targetId, $reachable, true)) {
                        $skipped++;

                        continue;
                    }

                    $counted++;
                }
            }
        });

        $dangling += $skipped;

        return $counted;
    }

    /**
     * A legacy array can still name a row somebody deleted outright, and an edge to nothing
     * is the dangling reference the ledger exists to end. Global scopes come off the target
     * query: one run migrates every tenant, and a soft-deleted end keeps its edges.
     *
     * @param  EloquentCollection<int, CustomFieldValue>  $values
     * @return array<int, string>
     */
    private function reachableTargets(string $entityType, EloquentCollection $values): array
    {
        $ids = array_values(array_unique(array_merge(...array_map(
            fn (CustomFieldValue $value): array => $this->records->targets($value),
            $values->all(),
        ) ?: [[]])));

        if ($ids === []) {
            return [];
        }

        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;

        if (! class_exists($entityClass) || ! is_subclass_of($entityClass, Model::class)) {
            return [];
        }

        $target = new $entityClass;

        return $target->newQuery()
            ->withoutGlobalScopes()
            ->whereKey($ids)
            ->pluck($target->getKeyName())
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();
    }

    private function insert(CustomFieldRelationship $definition, CustomField $field, CustomFieldValue $value, string $targetId, int $index): void
    {
        $attributes = [
            'relationship_id' => $definition->getKey(),
            'from_entity_type' => $value->entity_type,
            'from_entity_id' => $value->entity_id,
            'to_entity_type' => $definition->to_entity_type,
            'to_entity_id' => $targetId,
            'sort_order' => $index,
            'active_from' => now(),
            'source' => CustomFieldLink::SOURCE_MIGRATION,
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantKey = (string) config('custom-fields.database.column_names.tenant_foreign_key');
            $attributes[$tenantKey] = $field->{$tenantKey};
        }

        CustomFields::newLinkModel()->newQuery()->create($attributes);
    }

    /**
     * A 3.x record field points one way and its multiplicity lived in the settings, so that
     * is the definition it becomes: one slot, no partner. The field keeps its record type,
     * which is why a host reading this log sees no rename.
     */
    private function createDefinition(CustomField $field): CustomFieldRelationship
    {
        $attributes = [
            'code' => $this->availableCode($field),
            'from_entity_type' => $field->entity_type,
            'to_entity_type' => $this->records->legacyTargetEntityType($field),
            'cardinality' => $field->settings->allow_multiple
                ? RelationshipCardinality::ManyToMany
                : RelationshipCardinality::ManyToOne,
            'is_symmetric' => false,
            'from_field_id' => $field->getKey(),
            'to_field_id' => null,
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantKey = (string) config('custom-fields.database.column_names.tenant_foreign_key');
            $attributes[$tenantKey] = $field->{$tenantKey};
        }

        return CustomFields::newRelationshipModel()->newQuery()->create($attributes);
    }

    /**
     * Field codes are unique per entity type and definition codes per tenant, so the same
     * code can arrive twice from two entities of one tenant, and every tenant may hold its
     * own copy of it.
     */
    private function availableCode(CustomField $field): string
    {
        $candidate = $field->code;
        $suffix = 1;

        while ($this->codeIsTaken($candidate, $field)) {
            $candidate = sprintf('%s_%d', $field->code, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function codeIsTaken(string $code, CustomField $field): bool
    {
        $query = CustomFields::newRelationshipModel()
            ->newQuery()
            ->withoutGlobalScopes()
            ->where('code', $code);

        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            return $query->exists();
        }

        $tenantKey = (string) config('custom-fields.database.column_names.tenant_foreign_key');
        $tenantId = $field->{$tenantKey};

        if ($tenantId === null) {
            return $query->whereNull($tenantKey)->exists();
        }

        return $query->where($tenantKey, $tenantId)->exists();
    }

    private function table(string $key): string
    {
        return (string) config('custom-fields.database.table_names.'.$key);
    }
}
