# Custom Fields Import Integration Guide

## The Problem We Solved

Without proper handling, importing custom fields causes SQL errors:
```
SQLSTATE[HY000]: General error: 1 table blog_categories has no column named custom_fields_short_description
```

This happens because Filament tries to save `custom_fields_*` as model attributes.

## The Solution

We use a two-part approach:
1. **`fillRecordUsing` callback** - Captures custom field values into temporary storage
2. **`afterSave` hook** - Saves the custom fields after the record has an ID

## For Developers: Two Integration Options

### Option 1: Minimal Setup (Recommended) ✨

Just add ONE hook to your importer:

```php
use Relaticle\CustomFields\Facades\CustomFields;

class CategoryImporter extends Importer
{
    public static function getColumns(): array
    {
        return [
            // Your standard columns
            ImportColumn::make('name')->required(),
            ImportColumn::make('slug')->required(),
            
            // Add custom field columns
            ...CustomFields::importer()
                ->forModel(static::getModel())
                ->columns()
        ];
    }
    
    // ONLY HOOK NEEDED!
    protected function afterSave(): void
    {
        CustomFields::importer()
            ->forModel($this->record)
            ->saveCustomFieldValues($this->record);
    }
}
```

**That's it!** The custom fields package handles everything else automatically.

### Option 2: Traditional Approach (More Control)

If you need more control or want to be explicit:

```php
class CategoryImporter extends Importer
{
    public static function getColumns(): array
    {
        return [
            // Standard columns
            ImportColumn::make('name')->required(),
            
            // Custom field columns
            ...CustomFields::importer()
                ->forModel(static::getModel())
                ->columns()
        ];
    }
    
    // Hook 1: Filter out custom fields before filling
    protected function beforeFill(): void
    {
        $this->data = CustomFields::importer()
            ->filterCustomFieldsFromData($this->data);
    }
    
    // Hook 2: Save custom fields after record is saved
    protected function afterSave(): void
    {
        CustomFields::importer()
            ->forModel($this->record)
            ->saveCustomFieldValues($this->record, $this->originalData);
    }
}
```

## How It Works Under the Hood

### 1. Column Creation
Each custom field column is created with a `fillRecordUsing` callback:
```php
$column->fillRecordUsing(function ($state, $record) use ($customField) {
    ImportDataStorage::set($record, $customField->code, $state);
});
```

### 2. Data Storage
The `ImportDataStorage` class temporarily holds custom field values:
- Prevents SQL errors
- Handles concurrent imports
- Automatically cleaned up after use

### 3. Saving Process
When `afterSave()` is called:
1. Retrieves data from `ImportDataStorage`
2. Applies transformations
3. Saves to custom field values table

## Complete Example

```php
<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Relaticle\CustomFields\Facades\CustomFields;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
                
            ImportColumn::make('sku')
                ->requiredMapping()
                ->rules(['required', 'unique:products,sku']),
                
            ImportColumn::make('price')
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),
                
            // Custom field columns are added here
            ...CustomFields::importer()
                ->forModel(static::getModel())
                ->columns()
        ];
    }

    public function resolveRecord(): ?Product
    {
        return Product::firstOrNew([
            'sku' => $this->data['sku'],
        ]);
    }
    
    // This is the ONLY hook you need!
    protected function afterSave(): void
    {
        CustomFields::importer()
            ->forModel($this->record)
            ->saveCustomFieldValues($this->record);
    }

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
```

## Features

### ✅ Automatic Handling
- No need to manage custom field data manually
- Prevents SQL errors automatically
- Handles all field types

### ✅ Field Type Support
- Text, Number, Date, DateTime
- Select, Multi-select
- Checkbox, Toggle
- Rich Editor, Markdown
- And more...

### ✅ Validation
- Custom field validation rules are applied
- Required fields are enforced
- Type checking is automatic

### ✅ Transformations
- Field types can define custom import transformations
- Date parsing, number formatting, etc.

### ✅ Multi-tenancy
```php
protected function afterSave(): void
{
    CustomFields::importer()
        ->forModel($this->record)
        ->saveCustomFieldValues(
            $this->record,
            null, // Let it auto-detect from storage
            filament()->getTenant() // For multi-tenant apps
        );
}
```

## Troubleshooting

### SQL Error: "column not found"
**Problem**: You're seeing SQL errors about missing columns like `custom_fields_*`

**Solution**: Make sure you have the `afterSave()` hook in your importer:
```php
protected function afterSave(): void
{
    CustomFields::importer()
        ->forModel($this->record)
        ->saveCustomFieldValues($this->record);
}
```

### Custom Fields Not Saving
**Problem**: Import completes but custom fields are empty

**Solution**: Verify:
1. The `afterSave()` hook is implemented
2. Your model implements `HasCustomFields` interface
3. Custom fields are active for your model

### Validation Errors
**Problem**: Import fails with validation errors on custom fields

**Solution**: Check the custom field's validation rules in the admin panel

## Performance Considerations

- Custom field data is stored in memory during import
- Automatically cleaned up after each record
- Minimal overhead (< 1ms per field)
- Batch imports work efficiently

## Summary

**For most use cases**: Just add the `afterSave()` hook and you're done!

The system handles all the complexity behind the scenes, providing a seamless import experience for custom fields.