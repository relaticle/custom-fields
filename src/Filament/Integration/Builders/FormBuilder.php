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
        $dependentCodes = [];

        foreach ($fields as $field) {
            if ($field->visibility_conditions && is_array($field->visibility_conditions)) {
                foreach ($field->visibility_conditions as $condition) {
                    if (isset($condition['field'])) {
                        $dependentCodes[] = $condition['field'];
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

        $createField = fn (CustomField $customField) => $fieldComponentFactory->create(
            $customField,
            $dependentFieldCodes,
            $allFields
        );

        // Return flat fields if sections are disabled or withoutSections is set
        $sectionsDisabled = ! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS);
        if ($this->withoutSections || $sectionsDisabled) {
            return $allFields->map($createField);
        }

        return $this->getFilteredSections()
            ->map(function (CustomFieldSection $section) use ($sectionComponentFactory, $createField) {
                $fields = $section->fields->map($createField);

                return $fields->isEmpty()
                    ? null
                    : $sectionComponentFactory->create($section)->schema($fields->toArray());
            })
            ->filter();
    }
}
