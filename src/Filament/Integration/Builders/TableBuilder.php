<?php

declare(strict_types=1);

// ABOUTME: Builder for creating Filament table columns and filters from custom fields
// ABOUTME: Provides fluent API for generating table components with filtering support

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Illuminate\Support\Collection;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldColumnFactory;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldFilterFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;

final class TableBuilder extends BaseBuilder
{
    public function columns(): Collection
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::UI_TABLE_COLUMNS)) {
            return collect();
        }

        $fieldColumnFactory = app(FieldColumnFactory::class);
        $backendVisibilityService = app(BackendVisibilityService::class);

        // Get all fields for visibility evaluation
        $allFields = $this->getFilteredSections()->flatMap(fn (mixed $section): Collection => $section->fields);
        $relationName = $this->getRelationName();

        return $this->getFilteredSections()
            ->flatMap(fn (mixed $section): Collection => $section->fields)
            ->filter(fn (CustomField $field): bool => $field->typeData->tableColumn !== null)
            ->map(function (CustomField $field) use ($fieldColumnFactory, $backendVisibilityService, $allFields, $relationName) {
                $column = $fieldColumnFactory->create($field, $relationName);

                if (! method_exists($column, 'formatStateUsing')) {
                    return $column;
                }

                // Wrap the existing state with visibility check
                $column->formatStateUsing(function (mixed $state, mixed $record) use ($field, $backendVisibilityService, $allFields, $relationName): mixed {
                    // When using relations, resolve the related record for visibility check
                    $targetRecord = $relationName !== null ? data_get($record, $relationName) : $record;
                    
                    if ($targetRecord === null) {
                        return null;
                    }

                    if (! $backendVisibilityService->isFieldVisible($targetRecord, $field, $allFields)) {
                        return null; // Return null or empty value when field should be hidden
                    }

                    return $state;
                });

                return $column;
            })
            ->values();
    }

    public function filters(): Collection
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::UI_TABLE_FILTERS)) {
            return collect();
        }

        $fieldFilterFactory = app(FieldFilterFactory::class);
        $relationName = $this->getRelationName();

        return $this->getFilteredSections()
            ->flatMap(fn (mixed $section): Collection => $section->fields)
            ->filter(fn (CustomField $field): bool => $field->isFilterable() && $field->typeData->tableFilter !== null)
            ->map(fn (CustomField $field) => $fieldFilterFactory->create($field, $relationName))
            ->filter()
            ->values();
    }
}
