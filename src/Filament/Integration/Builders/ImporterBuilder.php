<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Models\CustomField;

class ImporterBuilder extends BaseBuilder
{
    public function columns(): Collection
    {
        return $this->getFilteredSections()
            ->flatMap(fn (mixed $section) => $section->fields)
            ->map(fn (CustomField $field): ImportColumn => $this->createColumn($field))
            ->values();
    }

    protected function createColumn(CustomField $field): ImportColumn
    {
        $column = ImportColumn::make('custom_fields_'.$field->code)
            ->label($field->name);

        // Use the unified configurator
        app(ImportColumnConfigurator::class)->configure($column, $field);

        return $column;
    }

    public function saveValues(?Model $tenant = null): void
    {
        // Get custom field data from storage or extract from provided data
        $customFieldsData = ImportDataStorage::pull($this->model);

        if ($customFieldsData === []) {
            return;
        }

        $this->model->saveCustomFields($customFieldsData, $tenant);
    }

    public function filterCustomFieldsFromData(array $data): array
    {
        return array_filter(
            $data,
            fn (string $key): bool => ! str_starts_with($key, 'custom_fields_'),
            ARRAY_FILTER_USE_KEY
        );
    }
}
