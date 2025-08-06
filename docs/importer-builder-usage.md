# ImporterBuilder Usage Guide

## Overview

The modernized `ImporterBuilder` provides a fluent interface for creating Filament import columns from custom fields. It follows the same architecture as the `ExporterBuilder` and integrates with the new field type system.

## Basic Usage

```php
use Relaticle\CustomFields\Filament\Integration\Builders\ImporterBuilder;
use App\Models\Product;

// In your Filament Importer class
public function getColumns(): array
{
    $standardColumns = [
        ImportColumn::make('name')
            ->required(),
        ImportColumn::make('price')
            ->numeric()
            ->required(),
        // ... other standard columns
    ];
    
    // Add custom field columns
    $customFieldColumns = ImporterBuilder::make()
        ->forModel(Product::class)
        ->columns()
        ->toArray();
    
    return array_merge($standardColumns, $customFieldColumns);
}
```

## Filtering Fields

### Only Include Specific Fields

```php
$customFieldColumns = ImporterBuilder::make()
    ->forModel(Product::class)
    ->only(['color', 'size', 'material'])
    ->columns();
```

### Exclude Specific Fields

```php
$customFieldColumns = ImporterBuilder::make()
    ->forModel(Product::class)
    ->except(['internal_notes', 'admin_only_field'])
    ->columns();
```

## Handling Import Data

In your importer's `afterFill()` method:

```php
use Relaticle\CustomFields\Filament\Integration\Builders\ImporterBuilder;

protected function afterFill(): void
{
    $importerBuilder = ImporterBuilder::make()
        ->forModel($this->record);
    
    // Save custom field values
    $importerBuilder->saveCustomFieldValues(
        record: $this->record,
        data: $this->data,
        tenant: filament()->getTenant() // Optional for multi-tenancy
    );
}
```

## Filtering Data Before Model Fill

In your importer's `beforeFill()` method:

```php
protected function beforeFill(): void
{
    $importerBuilder = ImporterBuilder::make();
    
    // Remove custom fields from data before filling the model
    $this->data = $importerBuilder->filterCustomFieldsFromData($this->data);
}
```

## Complete Importer Example

```php
namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Relaticle\CustomFields\Filament\Integration\Builders\ImporterBuilder;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;
    
    private ImporterBuilder $importerBuilder;
    
    public function __construct()
    {
        $this->importerBuilder = ImporterBuilder::make();
    }

    public static function getColumns(): array
    {
        $standardColumns = [
            ImportColumn::make('name')
                ->required()
                ->label('Product Name'),
                
            ImportColumn::make('sku')
                ->required()
                ->label('SKU')
                ->rules(['unique:products,sku']),
                
            ImportColumn::make('price')
                ->numeric()
                ->required()
                ->label('Price'),
                
            ImportColumn::make('description')
                ->label('Description'),
        ];
        
        // Add custom field columns
        $customFieldColumns = ImporterBuilder::make()
            ->forModel(Product::class)
            ->columns()
            ->toArray();
        
        return array_merge($standardColumns, $customFieldColumns);
    }

    public function resolveRecord(): ?Product
    {
        // Find or create the product
        return Product::firstOrNew([
            'sku' => $this->data['sku'],
        ]);
    }
    
    protected function beforeFill(): void
    {
        // Filter out custom fields before filling the model
        $this->data = $this->importerBuilder->filterCustomFieldsFromData($this->data);
    }
    
    protected function afterFill(): void
    {
        // Save custom field values after the model is filled
        $this->importerBuilder
            ->forModel($this->record)
            ->saveCustomFieldValues(
                record: $this->record,
                data: $this->originalData,
                tenant: filament()->getTenant()
            );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
```

## Features

### Dynamic Field Type Resolution
The ImporterBuilder automatically resolves field types using the `FieldTypeManager`, eliminating the need for hardcoded field type lists.

### Visibility Support
Fields with visibility conditions are automatically handled during import. Hidden fields won't have their values imported.

### Value Transformation
Field types that implement `FieldImportExportInterface` can provide custom import value transformations:

```php
class CurrencyFieldType implements FieldImportExportInterface
{
    public function transformImportValue(mixed $value): mixed
    {
        if (is_string($value)) {
            // Remove currency symbols and formatting
            $value = preg_replace('/[^0-9.-]/', '', $value);
        }
        
        return round(floatval($value), 2);
    }
}
```

### Validation Rules
Validation rules defined on custom fields are automatically applied to import columns.

### Multi-tenancy Support
The builder supports multi-tenant applications through the optional `$tenant` parameter in `saveCustomFieldValues()`.

## Migration from Old System

If you're migrating from the old `CustomFieldsImporter` action:

**Old way:**
```php
use Relaticle\CustomFields\Filament\Integration\Actions\Imports\CustomFieldsImporter;

$importer = app(CustomFieldsImporter::class);
$columns = $importer->getColumns(Product::class);
```

**New way:**
```php
use Relaticle\CustomFields\Filament\Integration\Builders\ImporterBuilder;

$columns = ImporterBuilder::make()
    ->forModel(Product::class)
    ->columns();
```

The new builder pattern provides better consistency with the ExporterBuilder and more flexibility for customization.