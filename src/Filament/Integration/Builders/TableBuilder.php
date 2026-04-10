<?php

declare(strict_types=1);

// ABOUTME: Builder for creating Filament table columns and filters from custom fields
// ABOUTME: Provides fluent API for generating table components with filtering support

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Closure;
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

        // Get all fields using the most efficient method
        $allFields = $this->getAllFields();

        return $allFields
            ->filter(fn (CustomField $field): bool => $field->typeData->tableColumn !== null)
            ->map(function (CustomField $field) use ($fieldColumnFactory, $backendVisibilityService, $allFields) {
                $column = $fieldColumnFactory->create($field);

                if (! method_exists($column, 'formatStateUsing')) {
                    return $column;
                }

                $existingFormatter = (fn (): ?Closure => $this->formatStateUsing)->call($column); // @phpstan-ignore property.notFound

                $column->formatStateUsing(function (mixed $state, mixed $record) use ($field, $backendVisibilityService, $allFields, $existingFormatter, $column): mixed {
                    if (! $backendVisibilityService->isFieldVisible($record, $field, $allFields)) {
                        return null;
                    }

                    if ($existingFormatter) {
                        return $column->evaluate($existingFormatter, [
                            'state' => $state,
                            'record' => $record,
                            'column' => $column,
                        ]);
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

        return $this->getAllFields()
            ->filter(fn (CustomField $field): bool => $field->isFilterable() && $field->typeData->tableFilter !== null)
            ->map(fn (CustomField $field) => $fieldFilterFactory->create($field))
            ->filter()
            ->values();
    }
}
