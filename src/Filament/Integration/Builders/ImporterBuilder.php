<?php

declare(strict_types=1);

// ABOUTME: Builder for creating Filament import columns from custom fields
// ABOUTME: Handles CSV/Excel import generation with visibility and transformation support

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\FieldImportExportInterface;
use Relaticle\CustomFields\FieldTypes\FieldTypeManager;
use Relaticle\CustomFields\Filament\Integration\Factories\ImportColumnFactory;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ValueConverters\ValueConverterInterface;
use Relaticle\CustomFields\Models\CustomField;

final class ImporterBuilder extends BaseBuilder
{
    /**
     * Generate import columns for custom fields.
     *
     * @return Collection<int, ImportColumn>
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     */
    public function columns(): Collection
    {
        $importColumnFactory = app(ImportColumnFactory::class);

        return $this->getFilteredSections()
            ->flatMap(fn ($section) => $section->fields)
            ->map(function (CustomField $field) use ($importColumnFactory) {
                // Create the import column using the factory
                return $importColumnFactory->create($field);
            })
            ->values();
    }

    /**
     * Save custom field values from imported data.
     *
     * This method can work in two modes:
     * 1. With explicit data array (traditional approach)
     * 2. Without data array (uses ImportDataStorage)
     *
     * @param  Model  $record  The model record to save custom fields for
     * @param  array<string, mixed>|null  $data  Optional import data, if null uses ImportDataStorage
     * @param  Model|null  $tenant  Optional tenant for multi-tenancy support
     */
    public function saveCustomFieldValues(Model $record, ?array $data = null, ?Model $tenant = null): void
    {
        // If no data provided, get from ImportDataStorage
        if ($data === null) {
            $customFieldsData = ImportDataStorage::pull($record);
        } else {
            // Extract custom fields from provided data
            $customFieldsData = $this->extractCustomFieldsData($data);
        }

        if ($customFieldsData !== []) {
            // Transform values based on field types
            $customFieldsData = $this->transformImportValues($record, $customFieldsData);

            // Apply value converter for additional processing
            $valueConverter = app(ValueConverterInterface::class);
            $customFieldsData = $valueConverter->convertValues($record, $customFieldsData, $tenant);

            // Save the custom fields
            $record->saveCustomFields($customFieldsData, $tenant);
        }
    }

    /**
     * Extract custom fields data from import data.
     *
     * @param  array<string, mixed>  $data  The import data
     * @return array<string, mixed> Custom fields data
     */
    public function extractCustomFieldsData(array $data): array
    {
        $customFieldsData = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'custom_fields_')) {
                $fieldCode = str_replace('custom_fields_', '', $key);
                $customFieldsData[$fieldCode] = $value;
            }
        }

        return $customFieldsData;
    }

    /**
     * Filter out custom fields from the data that will be used to fill the model.
     *
     * @param  array<string, mixed>  $data  The import data to filter
     * @return array<string, mixed> Filtered data without custom fields
     */
    public function filterCustomFieldsFromData(array $data): array
    {
        return array_filter(
            $data,
            fn ($key): bool => ! str_starts_with($key, 'custom_fields_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Transform import values based on field type definitions.
     *
     * @param  Model  $record  The model record
     * @param  array<string, mixed>  $customFieldsData  The custom fields data
     * @return array<string, mixed> Transformed data
     */
    private function transformImportValues(Model $record, array $customFieldsData): array
    {
        $transformed = [];
        $fieldTypeManager = app(FieldTypeManager::class);

        // Get all fields for this model
        $fields = $this->getFilteredSections()
            ->flatMap(fn ($section) => $section->fields)
            ->keyBy('code');

        foreach ($customFieldsData as $fieldCode => $value) {
            $field = $fields->get($fieldCode);

            if ($field === null) {
                continue;
            }

            $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($field->type);

            if ($fieldTypeInstance instanceof FieldImportExportInterface) {
                $transformed[$fieldCode] = $fieldTypeInstance->transformImportValue($value);
            } else {
                $transformed[$fieldCode] = $value;
            }
        }

        return $transformed;
    }
}
