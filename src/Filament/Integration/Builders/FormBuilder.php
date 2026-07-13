<?php

// ABOUTME: Builder for creating Filament form schemas from custom fields
// ABOUTME: Handles form generation with sections, validation, and field dependencies

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Schemas\Components\Grid;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldComponentFactory;
use Relaticle\CustomFields\Filament\Integration\Factories\SectionComponentFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;

class FormBuilder extends BaseBuilder
{
    private ?bool $withoutSections = null;

    public function build(): Grid
    {
        $container = FormContainer::make()
            ->forModel($this->explicitModel ?? null)
            ->only($this->only)
            ->except($this->except);

        // Only set withoutSections if explicitly configured
        if ($this->withoutSections !== null) {
            $container->withoutSections($this->withoutSections);
        }

        return $container;
    }

    public function withoutSections(bool $withoutSections = true): static
    {
        $this->withoutSections = $withoutSections;

        return $this;
    }

    private function getDependentFieldCodes(Collection $fields): array
    {
        $visibilityLogic = app(CoreVisibilityLogicService::class);
        $dependentCodes = [];

        foreach ($fields as $field) {
            $dependentCodes = array_merge(
                $dependentCodes,
                $visibilityLogic->getDependentFields($field),
            );

            $validationRules = $field->validation_rules;
            if ($validationRules) {
                foreach (['min_date', 'max_date'] as $constraintKey) {
                    $constraint = $validationRules->get($constraintKey);
                    if (is_array($constraint) && ($constraint['anchor'] ?? null) === 'custom_field' && isset($constraint['field_reference'])) {
                        $dependentCodes[] = $constraint['field_reference'];
                    }
                }
            }
        }

        return array_unique($dependentCodes);
    }

    public function values(): Collection
    {
        $fieldComponentFactory = app(FieldComponentFactory::class);
        $sectionComponentFactory = app(SectionComponentFactory::class);

        // Use getAllFields() which handles sectionless mode properly
        $allFields = $this->getAllFields();
        $dependentFieldCodes = $this->getDependentFieldCodes($allFields);

        // Resolve record for visibility (null for create forms — fail-open)
        $record = isset($this->model) && $this->model->exists ? $this->model : null;

        $createField = fn (CustomField $customField) => $fieldComponentFactory->create(
            $customField,
            $dependentFieldCodes,
            $allFields,
            $record
        );

        // Return flat fields if sections are disabled or withoutSections is set
        $sectionsDisabled = ! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS);
        if ($this->withoutSections || $sectionsDisabled) {
            return $allFields->map($createField);
        }

        return $this->getFilteredSections()
            ->map(function (CustomFieldSection $section) use ($sectionComponentFactory, $createField, $allFields, $record) {
                $fields = $section->fields->map($createField);

                return $fields->isEmpty()
                    ? null
                    : $sectionComponentFactory->create($section, $allFields, $record)->schema($fields->toArray());
            })
            ->filter();
    }
}
