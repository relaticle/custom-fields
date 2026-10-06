<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldValue;

/**
 * The legacy record store read against the edge ledger. A field is unmigrated while one of
 * its json_value ids has no row on its definition, which is the only question the gate, the
 * migration, and the purge ever ask, so all three ask it here.
 */
final readonly class UnmigratedRecordFields
{
    private const int CHUNK_SIZE = 500;

    /**
     * @return array<int, string> field codes, across every tenant
     */
    public function codes(): array
    {
        $codes = [];

        foreach ($this->recordFields() as $field) {
            if ($this->hasMissingTargets($field)) {
                $codes[] = $field->code;
            }
        }

        return $codes;
    }

    /**
     * Record fields whose target is still only on the retiring column. Values do not come
     * into it: a field that holds none has a target the drop would erase just the same, and
     * the migration step gives it a definition. A field with no target loses nothing, so it
     * never blocks the drop.
     *
     * @return array<int, string> field codes, across every tenant
     */
    public function withoutDefinition(): array
    {
        $codes = [];

        foreach ($this->recordFields() as $field) {
            if ($this->definitionFor($field) instanceof CustomFieldRelationship) {
                continue;
            }

            if (blank($this->legacyTargetEntityType($field))) {
                continue;
            }

            $codes[] = $field->code;
        }

        return $codes;
    }

    /**
     * The column is read raw: the model stopped declaring it, and on a host that has already
     * migrated it is gone.
     */
    public function legacyTargetEntityType(CustomField $field): string
    {
        $target = $field->getRawOriginal('lookup_type');

        return is_string($target) ? $target : '';
    }

    /**
     * A closed edge counts as migrated: the id reached the ledger and was unlinked there,
     * which is not the same as never having arrived, and re-inserting it would resurrect a
     * link the user removed.
     */
    public function hasMissingTargets(CustomField $field): bool
    {
        $definition = $this->definitionFor($field);
        $missing = false;

        $this->values($field)->chunkById(self::CHUNK_SIZE, function (EloquentCollection $values) use ($definition, &$missing): bool {
            $ledger = $definition instanceof CustomFieldRelationship
                ? $this->ledgerTargets($definition, $values)
                : [];

            foreach ($values as $value) {
                foreach ($this->targets($value) as $targetId) {
                    if (in_array($targetId, $ledger[(string) $value->entity_id] ?? [], true)) {
                        continue;
                    }

                    $missing = true;

                    return false;
                }
            }

            return true;
        });

        return $missing;
    }

    /**
     * Every id these records already hold on the definition, open or closed.
     *
     * @param  EloquentCollection<int, CustomFieldValue>  $values
     * @return array<string, array<int, string>>
     */
    public function ledgerTargets(CustomFieldRelationship $definition, EloquentCollection $values): array
    {
        $links = CustomFields::newLinkModel()
            ->newQuery()
            ->withoutGlobalScopes()
            ->where('relationship_id', $definition->getKey())
            ->whereIn('from_entity_id', $values->pluck('entity_id')->all())
            ->get();

        $ledger = [];

        foreach ($links as $link) {
            $ledger[(string) $link->from_entity_id][] = (string) $link->to_entity_id;
        }

        return $ledger;
    }

    /**
     * @return array<int, string>
     */
    public function targets(CustomFieldValue $value): array
    {
        $ids = $value->json_value?->all() ?? [];

        return array_values(array_unique(array_map(
            static fn (mixed $id): string => (string) $id,
            array_filter($ids, static fn (mixed $id): bool => is_int($id) || (is_string($id) && $id !== '')),
        )));
    }

    public function definitionFor(CustomField $field): ?CustomFieldRelationship
    {
        if (! Schema::hasTable((string) config('custom-fields.database.table_names.custom_field_relationships'))) {
            return null;
        }

        return CustomFields::newRelationshipModel()
            ->newQuery()
            ->withoutGlobalScopes()
            ->where(fn (Builder $query): Builder => $query
                ->where('from_field_id', $field->getKey())
                ->orWhere('to_field_id', $field->getKey()))
            ->first();
    }

    /**
     * @return EloquentCollection<int, CustomField>
     */
    public function recordFields(): EloquentCollection
    {
        $fields = (string) config('custom-fields.database.table_names.custom_fields');
        $values = (string) config('custom-fields.database.table_names.custom_field_values');

        if (! Schema::hasTable($fields) || ! Schema::hasTable($values)) {
            return CustomFields::newCustomFieldModel()->newCollection();
        }

        return CustomFields::newCustomFieldModel()
            ->newQuery()
            ->withoutGlobalScopes()
            ->where('type', 'record')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<CustomFieldValue>
     */
    public function values(CustomField $field): Builder
    {
        return CustomFields::newValueModel()
            ->newQuery()
            ->withoutGlobalScopes()
            ->where('custom_field_id', $field->getKey())
            ->whereNotNull('json_value');
    }
}
