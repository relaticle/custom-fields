<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Relaticle\CustomFields\Facades\CustomFields;

/**
 * Complete example of a Filament v4 importer with custom fields integration.
 * 
 * This example demonstrates the proper way to integrate custom fields
 * into a Filament v4 importer using the modernized ImporterBuilder.
 */
class CompleteProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    /**
     * Define import columns including custom fields.
     */
    public static function getColumns(): array
    {
        // Standard model columns
        $standardColumns = [
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
                
            ImportColumn::make('description')
                ->rules(['nullable', 'string'])
                ->example('Product description'),
                
            ImportColumn::make('stock_quantity')
                ->numeric()
                ->rules(['required', 'integer', 'min:0'])
                ->example('100'),
                
            ImportColumn::make('is_active')
                ->boolean()
                ->rules(['boolean'])
                ->example('yes'),
        ];
        
        // Add custom field columns using the ImporterBuilder
        // The spread operator (...) converts the Collection to an array
        $customFieldColumns = [
            ...CustomFields::importer()
                ->forModel(static::getModel())
                ->columns()
        ];
        
        // Combine standard and custom field columns
        return array_merge($standardColumns, $customFieldColumns);
    }

    /**
     * Resolve the record - find existing or create new.
     * 
     * This method is called to determine if we're updating an existing
     * record or creating a new one.
     */
    public function resolveRecord(): ?Product
    {
        // Try to find existing product by SKU
        if (isset($this->data['sku'])) {
            return Product::firstOrNew([
                'sku' => $this->data['sku'],
            ]);
        }
        
        // Create new product if SKU not provided
        return new Product();
    }

    /**
     * Hook called before filling the model with data.
     * 
     * Use this to prepare or filter the data before it's used
     * to fill the model attributes.
     */
    protected function beforeFill(): void
    {
        // Filter out custom field data so it doesn't try to fill
        // non-existent model attributes
        $this->data = CustomFields::importer()
            ->filterCustomFieldsFromData($this->data);
            
        // You can also perform other data preparations here
        // For example, normalize the SKU to uppercase
        if (isset($this->data['sku'])) {
            $this->data['sku'] = strtoupper($this->data['sku']);
        }
    }

    /**
     * Hook called after the model has been filled with data.
     * 
     * Use this to handle related data, custom fields, or other
     * operations that require the model to be filled first.
     */
    protected function afterFill(): void
    {
        // Save custom field values
        // Note: We use $this->originalData which contains all the import data
        // including custom fields before they were filtered out
        CustomFields::importer()
            ->forModel($this->record)
            ->saveCustomFieldValues(
                record: $this->record,
                data: $this->originalData,
                tenant: filament()->getTenant() // null if not using multi-tenancy
            );
            
        // You can also handle other relationships here
        // For example, associate with categories if provided
        if (isset($this->originalData['category_ids'])) {
            $categoryIds = explode(',', $this->originalData['category_ids']);
            $this->record->categories()->sync($categoryIds);
        }
    }

    /**
     * Customize the completion notification message.
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

    /**
     * Optional: Validate the entire row before processing.
     * 
     * This is called before resolveRecord() and can be used
     * for complex validation that spans multiple columns.
     */
    protected function beforeValidate(): void
    {
        // Example: Ensure price is greater than cost if both are provided
        if (isset($this->data['price']) && isset($this->data['cost'])) {
            if ($this->data['price'] <= $this->data['cost']) {
                $this->fail('Price must be greater than cost');
            }
        }
    }

    /**
     * Optional: Hook called before saving the record.
     * 
     * Use this for last-minute modifications or validations.
     */
    protected function beforeSave(): void
    {
        // Example: Generate a slug from the name if not provided
        if (empty($this->record->slug)) {
            $this->record->slug = str($this->record->name)->slug();
        }
        
        // Example: Set default values
        $this->record->import_batch = $this->import->id;
        $this->record->imported_at = now();
    }

    /**
     * Optional: Hook called after the record is saved.
     * 
     * Use this for operations that require the record to have an ID.
     */
    protected function afterSave(): void
    {
        // Example: Create audit log entry
        activity()
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->withProperties([
                'import_id' => $this->import->id,
                'row_number' => $this->rowNumber,
            ])
            ->log('Product imported');
            
        // Example: Dispatch job for further processing
        // ProcessImportedProduct::dispatch($this->record);
    }

    /**
     * Optional: Hook called only when creating new records.
     */
    protected function beforeCreate(): void
    {
        // Set initial status for new products
        $this->record->status = 'draft';
        $this->record->created_by = auth()->id();
    }

    /**
     * Optional: Hook called only when updating existing records.
     */
    protected function beforeUpdate(): void
    {
        // Track who updated the record
        $this->record->updated_by = auth()->id();
        
        // Log what changed
        $changes = $this->record->getDirty();
        if (!empty($changes)) {
            logger()->info('Product updated via import', [
                'product_id' => $this->record->id,
                'changes' => $changes,
                'import_id' => $this->import->id,
            ]);
        }
    }
}