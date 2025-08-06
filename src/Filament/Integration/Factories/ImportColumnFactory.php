<?php

declare(strict_types=1);

// ABOUTME: Factory for creating Filament import columns from custom fields
// ABOUTME: Uses FieldDataType enum to determine appropriate column configuration

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\Data\ValidationRuleData;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators\BasicColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators\MultiSelectColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators\SelectColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Factory for creating import columns based on custom field types.
 */
final readonly class ImportColumnFactory
{
    /**
     * Constructor with dependency injection for configurators.
     */
    public function __construct(
        private SelectColumnConfigurator $selectColumnConfigurator,
        private MultiSelectColumnConfigurator $multiSelectColumnConfigurator,
        private BasicColumnConfigurator $basicColumnConfigurator,
    ) {}

    /**
     * Create an import column for a custom field.
     *
     * @param CustomField $customField The custom field to create an import column for
     * @return ImportColumn The created import column
     * @throws \Exception
     */
    public function create(CustomField $customField): ImportColumn
    {
        $column = ImportColumn::make('custom_fields_' . $customField->code)
            ->label($customField->name);

        // Configure the column based on the field's data type
        $this->configureColumn($column, $customField);

        // Apply validation rules
        $this->applyValidationRules($column, $customField);

        // CRITICAL: Use fillRecordUsing to prevent SQL errors
        // Without this, Filament tries to set the value as a model attribute
        // which causes "column not found" SQL errors
        $column->fillRecordUsing(function ($state, $record) use ($customField) {
            // Store the value in our temporary storage
            // This prevents Filament from trying to set it as a model attribute
            ImportDataStorage::set($record, $customField->code, $state);
        });

        return $column;
    }

    /**
     * Configure a column based on the field's data type.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     */
    private function configureColumn(ImportColumn $column, CustomField $customField): void
    {
        // Get the data type from the custom field
        $dataType = $customField->typeData->dataType;

        // Select the appropriate configurator based on data type
        $configurator = match ($dataType) {
            FieldDataType::SINGLE_CHOICE => $this->selectColumnConfigurator,
            FieldDataType::MULTI_CHOICE => $this->multiSelectColumnConfigurator,
            default => $this->basicColumnConfigurator,
        };

        // Apply the configuration
        $configurator->configure($column, $customField);
    }

    /**
     * Apply validation rules to a column.
     *
     * @param  ImportColumn  $column  The column to apply validation rules to
     * @param  CustomField  $customField  The custom field containing validation rules
     */
    private function applyValidationRules(ImportColumn $column, CustomField $customField): void
    {
        $rules = $customField->validation_rules->toCollection()
            ->map(
                fn (ValidationRuleData $rule): string => $rule->parameters === []
                    ? $rule->name
                    : $rule->name . ':' . implode(',', $rule->parameters)
            )
            ->filter()
            ->toArray();

        if ($rules !== []) {
            $column->rules($rules);
        }
    }
}
