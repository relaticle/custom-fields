<?php

declare(strict_types=1);

// ABOUTME: Simplified factory for creating Filament import columns from custom fields
// ABOUTME: Delegates all configuration to the unified ImportColumnConfigurator

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Simplified factory for creating import columns.
 *
 * This factory has been dramatically simplified:
 * - No more dependency injection of multiple configurators
 * - Single unified configurator handles all field types
 * - Clean, simple API
 */
final class ImportColumnFactory
{
    private ImportColumnConfigurator $configurator;

    /**
     * Create a new factory instance.
     */
    public function __construct()
    {
        $this->configurator = app(ImportColumnConfigurator::class);
    }

    /**
     * Create an import column for a custom field.
     *
     * @param  CustomField  $customField  The custom field to create an import column for
     * @return ImportColumn The fully configured import column
     */
    public function create(CustomField $customField): ImportColumn
    {
        $column = ImportColumn::make('custom_fields_'.$customField->code)
            ->label($customField->name);

        // Let the unified configurator handle everything
        return $this->configurator->configure($column, $customField);
    }
}
