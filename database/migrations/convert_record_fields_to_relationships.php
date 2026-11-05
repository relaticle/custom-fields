<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Enums\WriteSource;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Support\KeyType;

/*
 * A 3.x record field kept its target in custom_fields.lookup_type and its ids in json_value.
 * From 4.0 the target lives on a relationship definition and every id is a link, so this
 * converts each record field in place, removes its value rows, and drops the column.
 */
return new class extends Migration
{
    private const int CHUNK_SIZE = 500;

    public function up(): void
    {
        $fields = $this->table('custom_fields');

        if (! Schema::hasColumn($fields, 'lookup_type')) {
            return;
        }

        DB::table($fields)->where('type', 'record')->eachById(function (object $field): void {
            $this->convert($field);
        }, self::CHUNK_SIZE);

        Schema::table($fields, function (Blueprint $table): void {
            $table->dropColumn('lookup_type');
        });
    }

    private function convert(object $field): void
    {
        $definition = $this->existingDefinition($field) ?? $this->createDefinition($field);

        if ($definition === null) {
            // A field with no target cannot point anywhere, so it is switched off, and
            // its stored ids stay where they are for the host to inspect.
            DB::table($this->table('custom_fields'))->where('id', $field->id)->update(['active' => false]);

            return;
        }

        $readsFromEnd = (string) $definition->from_field_id === (string) $field->id;
        $targetType = $readsFromEnd ? $definition->to_entity_type : $definition->from_entity_type;
        $values = DB::table($this->table('custom_field_values'))->where('custom_field_id', $field->id);

        (clone $values)->whereNotNull('json_value')->eachById(function (object $value) use ($definition, $field, $readsFromEnd, $targetType): void {
            $this->link($definition, $field, $value, $readsFromEnd, $targetType);
        }, self::CHUNK_SIZE);

        $values->delete();
    }

    private function existingDefinition(object $field): ?object
    {
        return DB::table($this->table('custom_field_relationships'))
            ->where(fn (Builder $query): Builder => $query->where('from_field_id', $field->id)->orWhere('to_field_id', $field->id))
            ->first();
    }

    private function createDefinition(object $field): ?object
    {
        if (! is_string($field->lookup_type) || $field->lookup_type === '') {
            return null;
        }

        $settings = json_decode((string) $field->settings, true);
        $multiple = is_array($settings) && ($settings['allow_multiple'] ?? false) === true;

        $attributes = $this->withKeyAndTenant([
            'code' => $this->availableCode($field),
            'from_entity_type' => $field->entity_type,
            'to_entity_type' => $field->lookup_type,
            'cardinality' => ($multiple ? RelationshipCardinality::ManyToMany : RelationshipCardinality::ManyToOne)->value,
            'is_symmetric' => false,
            'from_field_id' => $field->id,
            'to_field_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $field);

        $table = DB::table($this->table('custom_field_relationships'));

        if (! isset($attributes['id'])) {
            $attributes['id'] = $table->insertGetId($attributes);
        } else {
            $table->insert($attributes);
        }

        return (object) $attributes;
    }

    private function link(object $definition, object $field, object $value, bool $readsFromEnd, string $targetType): void
    {
        $ids = json_decode((string) $value->json_value, true);

        if (! is_array($ids)) {
            return;
        }

        $targets = $this->reachable($targetType, $ids);
        $linked = $this->linkedTargets($definition, $value, $readsFromEnd);
        $position = 0;

        foreach ($ids as $id) {
            $id = (string) $id;

            if (! in_array($id, $targets, true) || in_array($id, $linked, true)) {
                continue;
            }

            $near = ['type' => $value->entity_type, 'id' => $value->entity_id];
            $far = ['type' => $targetType, 'id' => $id];
            [$from, $to] = $readsFromEnd ? [$near, $far] : [$far, $near];

            DB::table($this->table('custom_field_links'))->insert($this->withKeyAndTenant([
                'relationship_id' => $definition->id,
                'from_entity_type' => $from['type'],
                'from_entity_id' => $from['id'],
                'to_entity_type' => $to['type'],
                'to_entity_id' => $to['id'],
                'sort_order' => $position++,
                'active_from' => now(),
                'source' => WriteSource::Migration->value,
            ], $field));

            $linked[] = $id;
        }
    }

    /**
     * Ids whose row still exists, read past every scope: a soft-deleted end keeps its link.
     * The morph map is the one thing only the application knows, so the target's table and
     * key come from its model.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    private function reachable(string $entityType, array $ids): array
    {
        $class = Relation::getMorphedModel($entityType) ?? $entityType;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return [];
        }

        $target = new $class;

        return DB::table($target->getTable())
            ->whereIn($target->getKeyName(), array_values(array_filter($ids, is_scalar(...))))
            ->pluck($target->getKeyName())
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function linkedTargets(object $definition, object $value, bool $readsFromEnd): array
    {
        [$near, $far] = $readsFromEnd ? ['from', 'to'] : ['to', 'from'];

        return DB::table($this->table('custom_field_links'))
            ->where('relationship_id', $definition->id)
            ->where($near.'_entity_type', $value->entity_type)
            ->where($near.'_entity_id', $value->entity_id)
            ->pluck($far.'_entity_id')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();
    }

    private function availableCode(object $field): string
    {
        $candidate = $field->code;
        $suffix = 1;

        while ($this->tenantQuery(DB::table($this->table('custom_field_relationships')), $field)->where('code', $candidate)->exists()) {
            $candidate = sprintf('%s_%d', $field->code, $suffix++);
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withKeyAndTenant(array $attributes, object $field): array
    {
        $key = KeyType::newKey();

        if ($key !== null) {
            $attributes = ['id' => $key, ...$attributes];
        }

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantKey = $this->tenantKey();
            $attributes[$tenantKey] = $field->{$tenantKey};
        }

        return $attributes;
    }

    private function tenantQuery(Builder $query, object $field): Builder
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            return $query;
        }

        $tenantKey = $this->tenantKey();

        return $field->{$tenantKey} === null
            ? $query->whereNull($tenantKey)
            : $query->where($tenantKey, $field->{$tenantKey});
    }

    private function tenantKey(): string
    {
        return (string) config('custom-fields.database.column_names.tenant_foreign_key');
    }

    private function table(string $key): string
    {
        return (string) config('custom-fields.database.table_names.'.$key);
    }
};
