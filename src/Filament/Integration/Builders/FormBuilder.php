<?php

// ABOUTME: Builder for creating Filament form schemas from custom fields
// ABOUTME: Handles form generation with sections, validation, and field dependencies

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Schemas\Components\Grid;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldComponentFactory;
use Relaticle\CustomFields\Models\CustomField;

class FormBuilder extends BaseBuilder
{
    public function build(): Grid
    {
        return FormContainer::make()
            ->forModel($this->explicitModel ?? null)
            ->only($this->only)
            ->except($this->except);
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

        $allFields = $this->getFilteredSections()->flatMap(fn (mixed $section) => $section->fields);
        $dependentFieldCodes = $this->getDependentFieldCodes($allFields);

        // Return fields directly without Section/Fieldset wrappers
        // This ensures the flat structure: custom_fields.{field_code}
        // Note: We skip section grouping to avoid nested paths like custom_fields.{section_code}.{field_code}
        // which causes issues with Filament v4's child schema nesting behavior.
        // Visual grouping can be added later using alternative methods if needed.
        return $allFields->map(
            fn (CustomField $customField) => $fieldComponentFactory->create(
                $customField,
                $dependentFieldCodes,
                $allFields
            )
        );
    }
}
