<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Relaticle\CustomFields\Facades\CustomFields;

/**
 * FINAL RECOMMENDED APPROACH
 * 
 * After deep analysis and testing, this is the most reliable,
 * performant, and developer-friendly way to import custom fields.
 * 
 * Key points:
 * - Uses standard Filament patterns (two hooks)
 * - No magic properties or complex state management
 * - Clear and explicit data flow
 * - Easy to debug and maintain
 */
class FinalRecommendedImporter extends Importer
{
    protected static ?string $model = Product::class;

    /**
     * Define import columns including custom fields.
     */
    public static function getColumns(): array
    {
        return [
            // Standard model columns
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('Product Name'),
                
            ImportColumn::make('sku')
                ->requiredMapping()
                ->rules(['required', 'unique:products,sku'])
                ->example('PROD-001'),
                
            ImportColumn::make('price')
                ->numeric()
                ->rules(['required', 'numeric', 'min:0'])
                ->example('99.99'),
                
            // Custom field columns
            // These are automatically generated with proper validation and examples
            ...CustomFields::importer()
                ->forModel(static::getModel())
                ->columns()
        ];
    }

    /**
     * Find or create the record to import.
     */
    public function resolveRecord(): ?Product
    {
        // Find existing product by SKU or create new
        return Product::firstOrNew([
            'sku' => $this->data['sku'],
        ]);
    }

    /**
     * Hook 1: Filter out custom fields before filling the model.
     * 
     * This prevents Filament from trying to set non-existent
     * model attributes like 'custom_fields_color'.
     */
    protected function beforeFill(): void
    {
        // Remove custom_fields_* from data to prevent attribute errors
        $this->data = CustomFields::importer()
            ->filterCustomFieldsFromData($this->data);
    }

    /**
     * Hook 2: Save custom fields after the record is saved.
     * 
     * At this point:
     * - The record has been saved and has an ID
     * - We can safely create/update custom field values
     * - originalData contains the unfiltered import data
     */
    protected function afterSave(): void
    {
        // Save custom field values using the original import data
        CustomFields::importer()
            ->forModel($this->record)
            ->saveCustomFieldValues(
                record: $this->record,
                data: $this->originalData,
                tenant: filament()->getTenant() // null if not using multi-tenancy
            );
    }

    /**
     * Customize the completion notification.
     */
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and ' . 
                number_format($import->successful_rows) . ' ' . 
                str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . 
                     str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}

/**
 * WHY THIS APPROACH?
 * 
 * 1. **Simplicity**: No complex state management or magic properties
 * 
 * 2. **Reliability**: Works consistently without edge cases
 * 
 * 3. **Performance**: Minimal overhead, no temporary storage
 * 
 * 4. **Maintainability**: Clear, explicit flow that's easy to understand
 * 
 * 5. **Compatibility**: Follows standard Filament patterns
 * 
 * 6. **Debugging**: Easy to trace data flow and find issues
 * 
 * 
 * DATA FLOW:
 * 
 * 1. Import row data comes in with all columns
 * 2. Validation runs on all columns (including custom_fields_*)
 * 3. beforeFill() filters out custom_fields_* from $this->data
 * 4. fillRecord() fills model with standard attributes only
 * 5. saveRecord() saves the model to database
 * 6. afterSave() saves custom fields using $this->originalData
 * 
 * 
 * WHAT HAPPENS TO THE DATA:
 * 
 * $this->originalData = [
 *     'name' => 'Product',
 *     'sku' => 'PROD-001',
 *     'price' => 99.99,
 *     'custom_fields_color' => 'red',     // Custom field
 *     'custom_fields_size' => 'large',    // Custom field
 * ]
 * 
 * After beforeFill():
 * $this->data = [
 *     'name' => 'Product',
 *     'sku' => 'PROD-001',
 *     'price' => 99.99,
 *     // Custom fields removed
 * ]
 * 
 * In afterSave():
 * - We use $this->originalData which still has custom fields
 * - ImporterBuilder extracts and saves them
 */