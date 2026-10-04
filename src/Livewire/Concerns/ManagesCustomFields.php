<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Forms\Components\DateConstraintField;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\Relationships\UpdateRelationshipDefinition;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Support\CodeGenerator;
use Relaticle\CustomFields\Support\SettingsMerger;

trait ManagesCustomFields
{
    /**
     * The field form is a slide-over tall enough to push the save button below the fold, so
     * the keyboard has to be able to submit it. The attributes land on the modal's own form
     * element, which is what carries the wire:submit handler.
     *
     * @return array<string, string>
     */
    protected function submitsOnMetaEnter(): array
    {
        return [
            'x-on:keydown.meta.enter.prevent' => '$el.requestSubmit()',
            'x-on:keydown.ctrl.enter.prevent' => '$el.requestSubmit()',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFieldData(array $data, string $entityType, int|string|null $sectionId = null): array
    {
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $data[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
        }

        if (FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE) && blank($data['code'] ?? null)) {
            $data['code'] = CodeGenerator::generateUniqueFieldCode($data['name'], $entityType, sectionId: $sectionId);
        }

        $result = [
            ...$data,
            'entity_type' => $entityType,
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS) && $sectionId !== null) {
            $result['custom_field_section_id'] = $sectionId;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeField(array $data): CustomField
    {
        $data = DateConstraintField::sanitizeValidationRules($data);
        $relationship = $this->pullRelationshipData($data);

        $options = collect(Arr::wrap($data['options'] ?? []))
            ->filter()
            ->values()
            ->map(function (array $option, int $index): array {
                $option['sort_order'] = $index;

                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $option[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
                }

                return $option;
            });

        unset($data['options']);

        // A record field with no definition points nowhere, so the field and the definition
        // are one write: a rejected definition takes the field with it.
        return DB::transaction(function () use ($data, $options, $relationship): CustomField {
            $customField = CustomFields::newCustomFieldModel()->create($data);

            $customField->options()->createMany($options);

            if ($relationship !== null) {
                $this->defineRelationship($customField, $relationship);
            }

            return $customField;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    protected function updateField(CustomField $field, array $data): void
    {
        $data = DateConstraintField::sanitizeValidationRules($data);
        $relationship = $this->pullRelationshipData($data);

        if (isset($data['settings'])) {
            $data['settings'] = SettingsMerger::merge($field->settings->toArray(), $data['settings']);
        }

        DB::transaction(function () use ($field, $data, $relationship): void {
            $field->update($data);

            $definition = $field->relationshipDefinition();

            if ($relationship === null || ! $definition instanceof CustomFieldRelationship) {
                return;
            }

            app(UpdateRelationshipDefinition::class)->execute(
                $definition,
                $definition->orientCardinality($field, $this->resolvedCardinality(
                    $relationship,
                    $definition->orientCardinality($field, $definition->cardinality),
                )),
                keepFirst: ($relationship['keep_first'] ?? false) === true,
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldFormState(CustomField $field): array
    {
        return [
            ...$field->toArray(),
            'options' => $field->options->toArray(),
            'relationship' => FieldForm::relationshipState($field),
        ];
    }

    /**
     * A copy of a record field needs a definition of its own: the field row carries no target,
     * and a record field without one points nowhere. The copy is always unpaired.
     */
    protected function copyRelationship(CustomField $field, CustomField $copy): void
    {
        $definition = $field->relationshipDefinition();
        $targetEntityType = $field->targetEntityType();

        if (! $definition instanceof CustomFieldRelationship || $targetEntityType === null) {
            return;
        }

        // The copy renders the from end of its own definition, so a source that reads the to
        // end hands over the cardinality the way it sees it, not the way it is stored.
        $this->defineRelationship($copy, [
            'target_entity_type' => $targetEntityType,
            'cardinality' => $definition->orientCardinality($field, $definition->cardinality)->value,
            'is_symmetric' => $definition->is_symmetric,
        ]);
    }

    /**
     * The record configuration is form state, never a column, so it leaves the payload before
     * the field row is written.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function pullRelationshipData(array &$data): ?array
    {
        $relationship = $data['relationship'] ?? null;

        unset($data['relationship']);

        if (! is_array($relationship)) {
            return null;
        }

        return filled($relationship['cardinality'] ?? null) || array_key_exists('allow_multiple', $relationship)
            ? $relationship
            : null;
    }

    /**
     * The cardinality the submitted configuration asks for, as the field's own end reads it.
     *
     * The paired face names it outright. The one-way face asks only how many records this
     * field holds, which is one end of the answer: the other end keeps the constraint it
     * already had, or a save that merely renamed the field would free the end the move
     * confirmation is read from.
     *
     * @param  array<string, mixed>  $relationship
     * @param  ?RelationshipCardinality  $current  what the field holds today, as its own end
     *                                             reads it; a field being created holds nothing
     */
    private function resolvedCardinality(array $relationship, ?RelationshipCardinality $current): RelationshipCardinality
    {
        if (filled($relationship['cardinality'] ?? null)) {
            return RelationshipCardinality::from((string) $relationship['cardinality']);
        }

        return ($current ?? RelationshipCardinality::ManyToOne)
            ->fromSideHolds(($relationship['allow_multiple'] ?? false) === true);
    }

    /**
     * @param  array<string, mixed>  $relationship
     */
    private function defineRelationship(CustomField $field, array $relationship): void
    {
        $fromEntityType = $this->resolveEntityType((string) $field->entity_type);
        $toEntityType = $this->resolveEntityType((string) $relationship['target_entity_type']);
        $isSymmetric = ($relationship['is_symmetric'] ?? false) === true && $fromEntityType === $toEntityType;
        $pairedName = trim((string) ($relationship['paired_field_name'] ?? ''));

        app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: CodeGenerator::generateUniqueRelationshipCode($field->code),
            fromEntityType: $fromEntityType,
            toEntityType: $toEntityType,
            cardinality: $this->resolvedCardinality($relationship, null),
            isSymmetric: $isSymmetric,
            fromField: new FieldSlotData(name: $field->name, fieldId: $field->getKey()),
            toField: $isSymmetric || $pairedName === ''
                ? null
                : new FieldSlotData(
                    name: $pairedName,
                    sectionId: $relationship['paired_section_id'] ?? null,
                    type: (string) $field->type,
                ),
        ));
    }

    /**
     * Links carry morph classes, and an entity is registered under that same alias, so both
     * ends go through the registry rather than through whatever string the form held.
     */
    private function resolveEntityType(string $entityType): string
    {
        return Entities::getEntity($entityType)?->getAlias() ?? $entityType;
    }
}
