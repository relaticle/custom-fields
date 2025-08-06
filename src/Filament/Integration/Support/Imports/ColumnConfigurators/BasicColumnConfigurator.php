<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators;

use Carbon\Carbon;
use Exception;
use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\Contracts\FieldImportExportInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\FieldTypes\FieldTypeManager;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Configures basic columns based on the custom field type.
 */
final class BasicColumnConfigurator implements ColumnConfiguratorInterface
{
    /**
     * Configure a basic import column based on a custom field.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     */
    public function configure(ImportColumn $column, CustomField $customField): void
    {
        // Check if field type implements import/export interface
        $fieldTypeManager = app(FieldTypeManager::class);
        $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($customField->type);

        if ($fieldTypeInstance instanceof FieldImportExportInterface) {
            // Let the field type configure itself
            $fieldTypeInstance->configureImportColumn($column);

            // Set example if provided
            $example = $fieldTypeInstance->getImportExample();
            if ($example !== null) {
                $column->example($example);
            }
            
            // Apply transformation if field type implements it
            $column->castStateUsing(function ($state) use ($fieldTypeInstance) {
                if ($state === null || $state === '') {
                    return null;
                }
                return $fieldTypeInstance->transformImportValue($state);
            });

            return;
        }

        // Apply default configuration based on data type
        match ($customField->typeData->dataType) {
            FieldDataType::NUMERIC, FieldDataType::FLOAT => $column->numeric(),
            FieldDataType::BOOLEAN => $column->boolean(),
            FieldDataType::DATE => $this->configureDateColumn($column),
            FieldDataType::DATE_TIME => $this->configureDateTimeColumn($column),
            default => $this->setExampleValue($column, $customField),
        };
    }

    /**
     * Configure a date column with proper parsing.
     */
    private function configureDateColumn(ImportColumn $column): void
    {
        $column->castStateUsing(function ($state): ?string {
            if (blank($state)) {
                return null;
            }

            try {
                return Carbon::parse($state)->format('Y-m-d');
            } catch (Exception) {
                return null;
            }
        });
    }

    /**
     * Configure a datetime column with proper parsing.
     */
    private function configureDateTimeColumn(ImportColumn $column): void
    {
        $column->castStateUsing(function ($state): ?string {
            if (blank($state)) {
                return null;
            }

            try {
                return Carbon::parse($state)->format('Y-m-d H:i:s');
            } catch (Exception) {
                return null;
            }
        });
    }

    /**
     * Set example values for a column based on the field's data type.
     *
     * @param  ImportColumn  $column  The column to set example for
     * @param  CustomField  $customField  The custom field to extract example values from
     */
    private function setExampleValue(ImportColumn $column, CustomField $customField): void
    {
        // Generate appropriate example values based on data type
        $example = match ($customField->typeData->dataType) {
            FieldDataType::STRING => 'Sample text',
            FieldDataType::TEXT => 'Sample longer text with multiple lines',
            FieldDataType::NUMERIC => '42',
            FieldDataType::FLOAT => '99.99',
            FieldDataType::BOOLEAN => 'Yes',
            FieldDataType::DATE => now()->format('Y-m-d'),
            FieldDataType::DATE_TIME => now()->format('Y-m-d H:i:s'),
            FieldDataType::SINGLE_CHOICE => 'Option 1',
            FieldDataType::MULTI_CHOICE => 'Option 1, Option 2',
        };

        $column->example($example);
    }
}
