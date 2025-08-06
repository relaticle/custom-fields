<?php

declare(strict_types=1);

// ABOUTME: Simplified builder for creating Filament import columns from custom fields
// ABOUTME: Provides clean API with integrated configuration and automatic value handling

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\FieldImportExportInterface;
use Relaticle\CustomFields\FieldTypes\FieldTypeManager;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Simplified ImporterBuilder with integrated logic.
 * 
 * This builder provides a clean, simple API for developers while handling
 * all the complexity of custom field imports internally.
 */
final class ImporterBuilder extends BaseBuilder
{
    private ?ImportColumnConfigurator $configurator = null;

    /**
     * Generate import columns for custom fields.
     * 
     * @return Collection<int, ImportColumn>
     */
    public function columns(): Collection
    {
        return $this->getFilteredSections()
            ->flatMap(fn ($section) => $section->fields)
            ->map(fn (CustomField $field) => $this->createColumn($field))
            ->values();
    }

    /**
     * Create an import column for a custom field.
     * 
     * This method creates a fully configured import column with all necessary
     * transformations, validations, and data handling.
     */
    private function createColumn(CustomField $field): ImportColumn
    {
        $column = ImportColumn::make('custom_fields_' . $field->code)
            ->label($field->name);

        // Use the unified configurator
        $this->getConfigurator()->configure($column, $field);

        return $column;
    }

    /**
     * Get the column configurator instance.
     */
    private function getConfigurator(): ImportColumnConfigurator
    {
        return $this->configurator ??= new ImportColumnConfigurator();
    }

    /**
     * Save custom field values from imported data.
     * 
     * This method intelligently handles both explicit data arrays and
     * data stored via ImportDataStorage during the import process.
     * 
     * @param  Model  $record  The model record to save custom fields for
     * @param  array<string, mixed>|null  $data  Optional import data
     * @param  Model|null  $tenant  Optional tenant for multi-tenancy
     */
    public function saveCustomFieldValues(Model $record, ?array $data = null, ?Model $tenant = null): void
    {
        // Get custom field data from storage or extract from provided data
        $customFieldsData = $data === null
            ? ImportDataStorage::pull($record)
            : $this->extractCustomFieldsData($data);

        if (empty($customFieldsData)) {
            return;
        }

        // Transform values based on field types
        $customFieldsData = $this->transformImportValues($record, $customFieldsData);

        // Save the custom fields
        $record->saveCustomFields($customFieldsData, $tenant);
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
     * Filter out custom fields from data to prevent SQL errors.
     * 
     * This method removes custom_fields_* keys from data that would
     * otherwise be passed to the model's fill() method.
     * 
     * @param  array<string, mixed>  $data  The import data to filter
     * @return array<string, mixed> Filtered data without custom fields
     */
    public function filterCustomFieldsFromData(array $data): array
    {
        return array_filter(
            $data,
            fn ($key): bool => !str_starts_with($key, 'custom_fields_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Transform import values based on field type definitions.
     * 
     * This method applies any field-specific transformations defined
     * by field types that implement FieldImportExportInterface.
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

            // Check if field type implements custom transformation
            $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($field->type);

            if ($fieldTypeInstance instanceof FieldImportExportInterface) {
                $transformed[$fieldCode] = $fieldTypeInstance->transformImportValue($value);
            } else {
                $transformed[$fieldCode] = $value;
            }
        }

        return $transformed;
    }

    /**
     * Get a simple one-line integration for developers.
     * 
     * This method provides the simplest possible integration:
     * Just call this in afterSave() hook.
     * 
     * @param  Model  $record  The saved record
     * @param  Model|null  $tenant  Optional tenant
     */
    public function handleAfterSave(Model $record, ?Model $tenant = null): void
    {
        $this->saveCustomFieldValues($record, null, $tenant);
    }
}