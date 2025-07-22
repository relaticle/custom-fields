<?php

declare(strict_types=1);

// ABOUTME: Builder for creating Filament table columns and filters from custom fields
// ABOUTME: Provides fluent API for generating table components with filtering support

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Illuminate\Support\Collection;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldColumnFactory;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldFilterFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Support\Utils;

final class TableBuilder extends BaseBuilder
{
    public function columns(): Collection
    {
        if (! Utils::isTableColumnsEnabled()) {
            return collect();
        }

        $fieldColumnFactory = app(FieldColumnFactory::class);
        $backendVisibilityService = app(BackendVisibilityService::class);

        // Get all fields for visibility evaluation
        $allFields = $this->getFilteredSections()->flatMap(fn ($section) => $section->fields);

        return $this->getFilteredSections()
            ->flatMap(fn ($section) => $section->fields)
            ->map(function (CustomField $field) use ($fieldColumnFactory, $backendVisibilityService, $allFields) {
                $column = $fieldColumnFactory->create($field);

                if (! method_exists($column, 'formatStateUsing')) {
                    return $column;
                }

                // Wrap the existing state with visibility check
                $column->formatStateUsing(function ($state, $record) use ($field, $backendVisibilityService, $allFields) {
                    if (! $backendVisibilityService->isFieldVisible($record, $field, $allFields)) {
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
        if (! Utils::isTableFiltersEnabled()) {
            return collect();
        }

        $fieldFilterFactory = app(FieldFilterFactory::class);

        return $this->getFilteredSections()
            ->flatMap(fn ($section) => $section->fields)
            ->filter(fn (CustomField $field): bool => $field->isFilterable())
            ->map(fn (CustomField $field) => $fieldFilterFactory->create($field))
            ->filter()
            ->values();
    }
}
