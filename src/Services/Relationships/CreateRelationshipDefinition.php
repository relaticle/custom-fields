<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\Scopes\ActivableScope;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Support\CodeGenerator;

final readonly class CreateRelationshipDefinition
{
    private const string DEFAULT_SECTION_CODE = 'default';

    public function execute(RelationshipDefinitionData $data): CustomFieldRelationship
    {
        $this->assertDefinable($data);

        return DB::transaction(function () use ($data): CustomFieldRelationship {
            $tenantId = TenantContextService::getCurrentTenantId();

            $fromField = $data->fromField instanceof FieldSlotData
                ? $this->slotField($data->fromField, $data->fromEntityType, $tenantId)
                : null;

            $toField = $data->toField instanceof FieldSlotData
                ? $this->slotField($data->toField, $data->toEntityType, $tenantId)
                : null;

            // A symmetric relationship renders one field that reads both ends, so both slots
            // point at it and directionFor() answers 'from' for either direction.
            $attributes = [
                'code' => $data->code,
                'from_entity_type' => $data->fromEntityType,
                'to_entity_type' => $data->toEntityType,
                'cardinality' => $data->cardinality,
                'is_symmetric' => $data->isSymmetric,
                'from_field_id' => $fromField?->getKey(),
                'to_field_id' => $data->isSymmetric ? $fromField?->getKey() : $toField?->getKey(),
            ];

            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                $attributes[config('custom-fields.database.column_names.tenant_foreign_key')] = $tenantId;
            }

            return CustomFields::newRelationshipModel()->newQuery()->create($attributes);
        });
    }

    private function assertDefinable(RelationshipDefinitionData $data): void
    {
        $this->assertEndResolves($data->fromEntityType, $data->code);
        $this->assertEndResolves($data->toEntityType, $data->code);

        if ($data->isSymmetric && $data->fromEntityType !== $data->toEntityType) {
            throw new InvalidArgumentException('A symmetric relationship requires matching entity types.');
        }

        if ($data->isSymmetric && $data->toField instanceof FieldSlotData) {
            throw new InvalidArgumentException('A symmetric relationship has a single field slot.');
        }

        if ($data->isSymmetric && $data->cardinality->fromSideIsSingle() !== $data->cardinality->toSideIsSingle()) {
            throw new InvalidArgumentException(sprintf('A symmetric relationship cannot use the directional cardinality [%s].', $data->cardinality->value));
        }

        if ($this->codeIsTaken($data->code)) {
            throw new InvalidArgumentException(sprintf('A relationship with the code [%s] already exists.', $data->code));
        }

        foreach ([$data->fromField, $data->toField] as $slot) {
            if ($slot instanceof FieldSlotData) {
                $this->assertSlotTypeLinks($slot, $data->code);
            }
        }
    }

    /**
     * A slot renders one end, so its field type has to be one that points at records. A slot
     * adopting an existing field is checked against that row instead.
     */
    private function assertSlotTypeLinks(FieldSlotData $slot, string $code): void
    {
        if ($slot->fieldId !== null) {
            return;
        }

        if (CustomFieldsType::getFieldType($slot->type)?->requiresRelationship === true) {
            return;
        }

        throw new InvalidArgumentException(sprintf('A relationship slot cannot render the [%s] field type (relationship [%s]).', $slot->type, $code));
    }

    /**
     * Ends are locked once a definition exists, so an unusable one is rejected here rather
     * than at the first write. The resolution mirrors the writer's.
     */
    private function assertEndResolves(string $entityType, string $code): void
    {
        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;

        if (class_exists($entityClass) && is_subclass_of($entityClass, Model::class)) {
            return;
        }

        throw new InvalidArgumentException(sprintf('A relationship cannot end on the unresolvable entity type [%s] (relationship [%s]).', $entityType, $code));
    }

    private function codeIsTaken(string $code): bool
    {
        return CustomFields::newRelationshipModel()
            ->newQuery()
            ->where('code', $code)
            ->exists();
    }

    private function slotField(FieldSlotData $slot, string $entityType, int|string|null $tenantId): CustomField
    {
        if ($slot->fieldId !== null) {
            return $this->adoptSlotField($slot->fieldId, $entityType);
        }

        return $this->createSlotField($slot, $entityType, $tenantId);
    }

    /**
     * A caller that owns the whole field form (the management UI, a preset migration) writes
     * the field itself and hands the key over, so the definition wraps that row.
     */
    private function adoptSlotField(int|string $fieldId, string $entityType): CustomField
    {
        $field = CustomFields::newCustomFieldModel()
            ->query()
            ->withDeactivated()
            ->whereKey($fieldId)
            ->first();

        if (! $field instanceof CustomField) {
            throw new InvalidArgumentException(sprintf('Field [%s] cannot be a relationship slot: it does not exist.', $fieldId));
        }

        if ($field->typeData?->requiresRelationship !== true) {
            throw new InvalidArgumentException(sprintf('Field [%s] cannot be a relationship slot: it is a [%s] field.', $field->code, $field->type));
        }

        if ($field->entity_type !== $entityType) {
            throw new InvalidArgumentException(sprintf('Field [%s] belongs to [%s], not to the [%s] end.', $field->code, $field->entity_type, $entityType));
        }

        if ($field->relationshipDefinition() instanceof CustomFieldRelationship) {
            throw new InvalidArgumentException(sprintf('Field [%s] already renders a relationship.', $field->code));
        }

        return $field;
    }

    private function createSlotField(FieldSlotData $slot, string $entityType, int|string|null $tenantId): CustomField
    {
        $attributes = [
            'code' => CodeGenerator::generateUniqueFieldCode($slot->name, $entityType, sectionId: $slot->sectionId),
            'name' => $slot->name,
            'type' => $slot->type,
            'entity_type' => $entityType,
            'active' => true,
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            $attributes['custom_field_section_id'] = $slot->sectionId ?? $this->defaultSection($entityType, $tenantId)->getKey();
        }

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $attributes[config('custom-fields.database.column_names.tenant_foreign_key')] = $tenantId;
        }

        return CustomFields::newCustomFieldModel()->newQuery()->create($attributes);
    }

    /**
     * A sectionless field never renders, because the activable scope asks for a section, so
     * a slot the caller placed nowhere lands in the entity's default one. An entity with no
     * section at all is the paired-field case: the form has nothing to offer there.
     */
    private function defaultSection(string $entityType, int|string|null $tenantId): CustomFieldSection
    {
        $attributes = ['entity_type' => $entityType, 'code' => self::DEFAULT_SECTION_CODE];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $attributes[(string) config('custom-fields.database.column_names.tenant_foreign_key')] = $tenantId;
        }

        return CustomFields::newSectionModel()
            ->newQuery()
            ->withoutGlobalScope(ActivableScope::class)
            ->firstOrCreate($attributes, [
                'name' => __('custom-fields::custom-fields.section.default_section_name'),
                'type' => CustomFieldSectionType::SECTION,
                'active' => true,
            ]);
    }
}
