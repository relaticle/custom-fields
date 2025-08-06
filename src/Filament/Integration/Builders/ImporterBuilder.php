<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\FieldImportExportInterface;
use Relaticle\CustomFields\FieldTypes\FieldTypeManager;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Models\CustomField;

final class ImporterBuilder extends BaseBuilder
{
    public function columns(): Collection
    {
        return $this->getFilteredSections()
            ->flatMap(fn ($section) => $section->fields)
            ->map(fn (CustomField $field) => $this->createColumn($field))
            ->values();
    }

    private function createColumn(CustomField $field): ImportColumn
    {
        $column = ImportColumn::make('custom_fields_' . $field->code)
            ->label($field->name);

        // Use the unified configurator
        app(ImportColumnConfigurator::class)->configure($column, $field);

        return $column;
    }

    public function saveValues(?Model $tenant = null): void
    {
        // Get custom field data from storage or extract from provided data
        $customFieldsData = ImportDataStorage::pull($this->model);

        if (empty($customFieldsData)) {
            return;
        }

        // Transform values based on field types
        $customFieldsData = $this->transformImportValues($customFieldsData);

        // Save the custom fields
        $this->model->saveCustomFields($customFieldsData, $tenant);
    }


    public function filterCustomFieldsFromData(array $data): array
    {
        return array_filter(
            $data,
            fn ($key): bool => ! str_starts_with($key, 'custom_fields_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function transformImportValues(array $customFieldsData): array
    {
        $transformed = [];
        $fieldTypeManager = app(FieldTypeManager::class);

        $fields = $this->getFilteredSections()->flatMap(fn ($section) => $section->fields)->keyBy('code');

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
}
