<?php

declare(strict_types=1);

// ABOUTME: Builder for creating Filament export columns from custom fields
// ABOUTME: Handles CSV/Excel export generation with visibility support

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\ValueResolvers;
use Relaticle\CustomFields\Filament\Integration\Factories\ExportColumnFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;

final class ExporterBuilder extends BaseBuilder
{
    /**
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     */
    public function columns(): Collection
    {
        $exportColumnFactory = app(ExportColumnFactory::class);
        $backendVisibilityService = app(BackendVisibilityService::class);

        // Get all fields for visibility evaluation
        $allFields = $this->getFilteredSections()->flatMap(fn ($section) => $section->fields);

        return $this->getFilteredSections()
            ->flatMap(fn ($section) => $section->fields)
            ->filter(fn (CustomField $field): bool => $field->settings->visible_in_list ?? true)
            ->map(function (CustomField $field) use ($exportColumnFactory, $backendVisibilityService, $allFields) {
                $column = $exportColumnFactory->create($field);

                // Wrap the existing state with visibility check
                return $column->state(function ($record) use ($field, $backendVisibilityService, $allFields) {
                    // Check visibility for this specific record
                    if (! $backendVisibilityService->isFieldVisible($record, $field, $allFields)) {
                        return null; // Don't export values for hidden fields
                    }

                    // Get the value resolver and resolve the value
                    $valueResolver = app(ValueResolvers::class);

                    return $valueResolver->resolve(
                        record: $record,
                        customField: $field,
                        exportable: true
                    );
                });
            })
            ->values();
    }
}
