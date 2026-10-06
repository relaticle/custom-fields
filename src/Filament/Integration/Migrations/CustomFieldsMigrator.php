<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Migrations;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\CustomFieldData;
use Relaticle\CustomFields\Data\CustomFieldOptionSettingsData;
use Relaticle\CustomFields\Data\CustomFieldSectionData;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Exceptions\CustomFieldAlreadyExistsException;
use Relaticle\CustomFields\Exceptions\CustomFieldDoesNotExistException;
use Relaticle\CustomFields\Exceptions\FieldTypeNotOptionableException;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Support\CodeGenerator;
use Throwable;

final class CustomFieldsMigrator
{
    private int|string|null $tenantId = null;

    private ?string $targetEntityType = null;

    private ?RelationshipCardinality $cardinality = null;

    private CustomFieldData $customFieldData;

    private ?CustomField $customField = null;

    public function setTenantId(int|string|null $tenantId = null): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * @param  class-string  $model
     */
    public function find(string $model, string $code): CustomFieldsMigrator
    {
        $this->customField = CustomFields::newCustomFieldModel()
            ->query()
            ->forMorphEntity((Entities::getEntity($model)?->getAlias()) ?? $model)
            ->where('code', $code)
            ->firstOrFail();

        $this->customFieldData = CustomFieldData::from($this->customField);

        return $this;
    }

    /**
     * @param  class-string  $model
     */
    public function new(
        string $model,
        CustomFieldData $fieldData
    ): CustomFieldsMigrator {
        $entityType = (Entities::getEntity($model)?->getAlias()) ?? $model;
        $fieldData->entityType = $entityType;

        // Only set section entityType when sections are enabled
        if ($fieldData->section instanceof CustomFieldSectionData && FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            $fieldData->section->entityType = $entityType;
        }

        $this->customFieldData = $fieldData;

        return $this;
    }

    /**
     * @param  array<int|string, mixed>  $options
     *
     * @throws FieldTypeNotOptionableException
     */
    public function options(array $options): CustomFieldsMigrator
    {
        if (! $this->isCustomFieldTypeOptionable()) {
            throw new FieldTypeNotOptionableException;
        }

        $this->customFieldData->options = $options;

        return $this;
    }

    /**
     * Point a record field at another entity. The field becomes the single slot of a one-way
     * relationship definition, created with the field. Without a cardinality, allow_multiple
     * on the field data picks it, exactly as the 4.0 upgrade step does.
     *
     * @param  class-string  $model
     *
     * @throws FieldTypeNotOptionableException
     */
    public function lookupType(string $model, ?RelationshipCardinality $cardinality = null): CustomFieldsMigrator
    {
        if (! $this->isCustomFieldTypeOptionable()) {
            throw new FieldTypeNotOptionableException;
        }

        $this->targetEntityType = (Entities::getEntity($model)?->getAlias()) ?? $model;
        $this->cardinality = $cardinality;

        return $this;
    }

    /**
     * @throws CustomFieldAlreadyExistsException
     * @throws Exception|Throwable
     */
    public function create(): CustomField
    {
        if (
            $this->isCustomFieldExists(
                $this->customFieldData->entityType,
                $this->customFieldData->code,
                $this->tenantId
            )
        ) {
            throw CustomFieldAlreadyExistsException::whenAdding(
                $this->customFieldData->code
            );
        }

        try {
            DB::beginTransaction();

            $data = $this->customFieldData
                ->except('section', 'options')
                ->toArray();

            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                $data[config('custom-fields.database.column_names.tenant_foreign_key')] =
                    $this->tenantId;
            }

            // Only create/update section when sections are enabled
            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS) && $this->customFieldData->section instanceof CustomFieldSectionData) {
                $sectionData = $this->customFieldData->section->toArray();
                $sectionAttributes = [
                    'entity_type' => $this->customFieldData->entityType,
                    'code' => $this->customFieldData->section->code,
                ];

                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $sectionData[config('custom-fields.database.column_names.tenant_foreign_key')] = $this->tenantId;
                    $sectionAttributes[config('custom-fields.database.column_names.tenant_foreign_key')] = $this->tenantId;
                }

                $section = CustomFields::newSectionModel()->updateOrCreate(
                    $sectionAttributes,
                    $sectionData
                );

                $data['custom_field_section_id'] = $section->getKey();
            }

            $customField = CustomFields::newCustomFieldModel()
                ->query()
                ->create($data);

            if (
                $this->isCustomFieldTypeOptionable() &&
                ($this->customFieldData->options !== null &&
                    $this->customFieldData->options !== [])
            ) {
                $this->createOptions(
                    $customField,
                    $this->customFieldData->options
                );
            }

            if ($this->targetEntityType !== null) {
                $this->defineRelationship($customField);
            }

            DB::commit();

            return $customField;
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws CustomFieldDoesNotExistException|Throwable
     */
    public function update(array $data): void
    {
        if (! $this->customField->exists) {
            throw CustomFieldDoesNotExistException::whenUpdating(
                $this->customFieldData->code
            );
        }

        if (array_key_exists('lookup_type', $data)) {
            throw new InvalidArgumentException('The ends of a relationship are locked after it is created.');
        }

        try {
            DB::beginTransaction();

            // Create a new CustomFieldData instance with the updated data
            $existingData = $this->customFieldData->toArray();
            $mergedData = array_merge($existingData, $data);
            $this->customFieldData = CustomFieldData::from($mergedData);

            $updateData = $this->customFieldData->toArray();

            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                $updateData[config('custom-fields.database.column_names.tenant_foreign_key')] = $this->tenantId;
            }

            $this->customField->update($updateData);

            if (
                $this->isCustomFieldTypeOptionable() &&
                ($this->customFieldData->options !== null &&
                    $this->customFieldData->options !== [])
            ) {
                $this->customField->options()->delete();
                $this->createOptions(
                    $this->customField,
                    $this->customFieldData->options
                );
            }

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @throws CustomFieldDoesNotExistException
     */
    public function delete(): void
    {
        if (! $this->customField instanceof CustomField) {
            throw CustomFieldDoesNotExistException::whenDeleting(
                $this->customFieldData->code
            );
        }

        $this->customField->delete();
    }

    /**
     * @throws CustomFieldDoesNotExistException
     */
    public function activate(): void
    {
        if (! $this->customField instanceof CustomField) {
            throw CustomFieldDoesNotExistException::whenActivating(
                $this->customFieldData->code
            );
        }

        if ($this->customField->isActive()) {
            return;
        }

        $this->customField->activate();
    }

    /**
     * @throws CustomFieldDoesNotExistException
     */
    public function deactivate(): void
    {
        if (! $this->customField instanceof CustomField) {
            throw CustomFieldDoesNotExistException::whenDeactivating(
                $this->customFieldData->code
            );
        }

        if (! $this->customField->isActive()) {
            return;
        }

        $this->customField->deactivate();
    }

    /**
     * The migrator stamps its own tenant on every row it writes, so the definition service
     * gets that tenant as its context rather than whatever the ambient one happens to be.
     */
    private function defineRelationship(CustomField $customField): void
    {
        $data = new RelationshipDefinitionData(
            code: CodeGenerator::generateUniqueRelationshipCode($customField->code),
            fromEntityType: (string) $customField->entity_type,
            toEntityType: (string) $this->targetEntityType,
            cardinality: $this->cardinality ?? $this->cardinalityFromSettings(),
            fromField: new FieldSlotData(name: $customField->name, fieldId: $customField->getKey()),
        );

        $define = fn (): CustomFieldRelationship => app(CreateRelationshipDefinition::class)->execute($data);

        $this->tenantId === null
            ? $define()
            : TenantContextService::withTenant($this->tenantId, $define);
    }

    private function cardinalityFromSettings(): RelationshipCardinality
    {
        return $this->customFieldData->settings?->allow_multiple === true
            ? RelationshipCardinality::ManyToMany
            : RelationshipCardinality::ManyToOne;
    }

    /**
     * @param  array<int|string, mixed>  $options
     */
    private function createOptions(
        CustomField $customField,
        array $options
    ): void {
        $customField->options()->createMany(
            collect($options)
                ->map(function (mixed $value, mixed $key): array {
                    $data = [
                        'name' => $value,
                        'sort_order' => $key,
                    ];

                    if (is_array($value)) {
                        $this->assertOptionIsSettable($value);

                        $data['name'] = $value['name'];
                        $data['settings'] = CustomFieldOptionSettingsData::from(Arr::except($value, 'name'));
                    }

                    if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                        $data[config(
                            'custom-fields.database.column_names.tenant_foreign_key'
                        )] = $this->tenantId;
                    }

                    return $data;
                })
                ->toArray()
        );
    }

    private function isCustomFieldExists(
        string $model,
        string $code,
        int|string|null $tenantId = null
    ): bool {
        return CustomFields::newCustomFieldModel()
            ->query()
            ->forMorphEntity($model)
            ->where('code', $code)
            ->when(
                FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY) && $tenantId,
                fn (mixed $query) => $query->where(
                    config('custom-fields.database.column_names.tenant_foreign_key'),
                    $tenantId
                )
            )
            ->exists();
    }

    /**
     * @param  array<mixed, mixed>  $option
     */
    private function assertOptionIsSettable(array $option): void
    {
        $code = $this->customFieldData->code;

        if (! isset($option['name']) || ! is_string($option['name'])) {
            throw new InvalidArgumentException(sprintf('Every option array on [%s] must carry a name.', $code));
        }

        $unknownKeys = array_diff(
            array_keys($option),
            ['name', ...array_keys(CustomFieldOptionSettingsData::empty())],
        );

        if ($unknownKeys !== []) {
            throw new InvalidArgumentException(
                sprintf('Option [%s] on [%s] carries unknown keys: ', $option['name'], $code).implode(', ', $unknownKeys).'.'
            );
        }

        if (! isset($option['category'])) {
            return;
        }

        if (CustomFieldsType::getFieldType($this->customFieldData->type)?->carriesOptionCategories !== true) {
            throw new InvalidArgumentException(
                sprintf('Option [%s] carries a category, but the options of [%s] are not workflow states.', $option['name'], $code)
            );
        }
    }

    private function isCustomFieldTypeOptionable(): bool
    {
        return CustomFieldsType::getFieldType($this->customFieldData->type)->dataType->isChoiceField();
    }
}
