<?php

// ABOUTME: Builder for creating Filament form schemas from custom fields
// ABOUTME: Handles form generation with sections, validation, and field dependencies

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Schemas\Components\Grid;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldComponentFactory;
use Relaticle\CustomFields\Filament\Integration\Factories\SectionComponentFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;

class FormBuilder extends BaseBuilder
{
    private bool $withoutSections = false;

    public function build(): Grid
    {
        return FormContainer::make()
            ->forModel($this->explicitModel ?? null)
            ->withoutSections($this->withoutSections)
            ->only($this->only)
            ->except($this->except);
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

        $allFields = $this->getFilteredSections()->flatMap(fn (mixed $section) => $section->fields);
        $dependentFieldCodes = $this->getDependentFieldCodes($allFields);

        $createField = fn (CustomField $customField) => $fieldComponentFactory->create(
            $customField,
            $dependentFieldCodes,
            $allFields
        );

        if ($this->withoutSections) {
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
