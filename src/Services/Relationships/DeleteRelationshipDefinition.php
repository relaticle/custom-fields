<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use Relaticle\CustomFields\Support\CodeGenerator;

final readonly class DeleteRelationshipDefinition
{
    public function execute(CustomFieldRelationship $definition, bool $deleteFields = false): void
    {
        DB::transaction(function () use ($definition, $deleteFields): void {
            $slots = $this->slots($definition);

            // Hosts run without foreign keys often enough (sqlite defaults them off) that the
            // cascade cannot be the only thing removing the edges. The definition delete is not
            // tenant-scoped either, so neither is this one: no context may strand an edge.
            CustomFields::newLinkModel()
                ->newQuery()
                ->withoutGlobalScope(TenantScope::class)
                ->where('relationship_id', $definition->getKey())
                ->delete();

            $definition->delete();

            foreach ($slots as [$field, $direction]) {
                if ($deleteFields) {
                    $field->delete();

                    continue;
                }

                $this->keepAsOneWay($definition, $field, $direction);
            }
        });
    }

    /**
     * @return array<int, array{0: CustomField, 1: string}>
     */
    private function slots(CustomFieldRelationship $definition): array
    {
        $fields = CustomFields::newCustomFieldModel()
            ->query()
            ->withDeactivated()
            ->whereKey(array_filter([$definition->from_field_id, $definition->to_field_id]))
            ->get()
            ->keyBy(fn (CustomField $field): string => (string) $field->getKey());

        $slots = [];

        foreach ([CustomFieldRelationship::DIRECTION_FROM => $definition->from_field_id, CustomFieldRelationship::DIRECTION_TO => $definition->to_field_id] as $direction => $fieldId) {
            $field = $fields->get((string) $fieldId);

            if (! $field instanceof CustomField) {
                continue;
            }

            $slots[(string) $fieldId] ??= [$field, $direction];
        }

        return array_values($slots);
    }

    private function keepAsOneWay(CustomFieldRelationship $definition, CustomField $field, string $direction): void
    {
        $holdsFromSlot = $definition->is_symmetric || $direction === CustomFieldRelationship::DIRECTION_FROM;
        $holdsToSlot = $definition->is_symmetric || $direction === CustomFieldRelationship::DIRECTION_TO;

        $attributes = [
            'code' => CodeGenerator::generateUniqueRelationshipCode($field->code),
            'from_entity_type' => $definition->from_entity_type,
            'to_entity_type' => $definition->to_entity_type,
            'cardinality' => $definition->cardinality,
            'is_symmetric' => $definition->is_symmetric,
            'from_field_id' => $holdsFromSlot ? $field->getKey() : null,
            'to_field_id' => $holdsToSlot ? $field->getKey() : null,
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantKey = config('custom-fields.database.column_names.tenant_foreign_key');
            $attributes[$tenantKey] = $definition->{$tenantKey};
        }

        CustomFields::newRelationshipModel()->newQuery()->create($attributes);
    }
}
