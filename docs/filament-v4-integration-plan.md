# Filament v4 Custom Fields Integration Plan

## Overview

This document outlines the authentic integration of custom fields with Filament v4, based on deep research of Filament's architecture and API.

## Key Findings

### 1. ImportColumn API
- **No `getCastStateUsing()` method**: ImportColumn only provides `castStateUsing()` as a setter
- **Single transformation point**: All transformations must be applied in one `castStateUsing()` call
- **Hook-based architecture**: Import process uses hooks (`beforeFill`, `afterFill`, etc.) for customization

### 2. Import Process Flow
```
1. resolveRecord() → Find or create model instance
2. beforeValidate() → Pre-validation hook
3. validate() → Run validation rules
4. afterValidate() → Post-validation hook
5. beforeFill() → Pre-fill hook (filter data here)
6. fillRecord() → Fill model with data
7. afterFill() → Post-fill hook (save relationships/custom fields here)
8. beforeSave() → Pre-save hook
9. saveRecord() → Save to database
10. afterSave() → Post-save hook
```

## Architecture Design

### 1. ImporterBuilder
The `ImporterBuilder` follows the builder pattern consistent with `ExporterBuilder`:

```php
class ImporterBuilder extends BaseBuilder
{
    // Generate import columns for custom fields
    public function columns(): Collection
    
    // Save custom field values (for use in afterFill hook)
    public function saveCustomFieldValues(Model $record, array $data, ?Model $tenant = null): void
    
    // Filter custom fields from data (for use in beforeFill hook)
    public function filterCustomFieldsFromData(array $data): array
}
```

### 2. ImportColumnFactory
Simplified factory that uses `FieldDataType` enum directly:

```php
class ImportColumnFactory
{
    // Create import column based on field's data type
    public function create(CustomField $field): ImportColumn
    
    // No registration system needed
    // Configuration determined by FieldDataType enum
}
```

### 3. Column Configurators
Three configurators based on data type:

- **BasicColumnConfigurator**: Handles STRING, TEXT, NUMERIC, FLOAT, BOOLEAN, DATE, DATE_TIME
- **SelectColumnConfigurator**: Handles SINGLE_CHOICE fields
- **MultiSelectColumnConfigurator**: Handles MULTI_CHOICE fields

## Integration Points

### 1. In Importer Classes

```php
class ProductImporter extends Importer
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
    
    protected function beforeFill(): void
    {
        // Filter out custom fields from model fill data
        $this->data = CustomFields::importer()
            ->filterCustomFieldsFromData($this->data);
    }
    
    protected function afterFill(): void
    {
        // Save custom field values
        CustomFields::importer()
            ->forModel($this->record)
            ->saveCustomFieldValues(
                record: $this->record,
                data: $this->originalData,
                tenant: filament()->getTenant()
            );
    }
}
```

### 2. Field Type Transformations

Field types can implement `FieldImportExportInterface` for custom import behavior:

```php
interface FieldImportExportInterface
{
    public function getImportExample(): ?string;
    public function configureImportColumn(ImportColumn $column): void;
    public function transformImportValue(mixed $value): mixed;
    public function transformExportValue(mixed $value): mixed;
}
```

## Key Improvements from Old System

### 1. Simplified Architecture
- **Before**: Complex registration system with hardcoded field types
- **After**: Dynamic resolution using FieldDataType enum

### 2. Consistent Builder Pattern
- **Before**: Action-based approach with CustomFieldsImporter
- **After**: Builder pattern matching ExporterBuilder

### 3. Better Separation of Concerns
- **Before**: Mixed responsibilities in single class
- **After**: Clear separation between column generation, data filtering, and value saving

### 4. Type Safety
- **Before**: String-based field type checking
- **After**: Enum-based data type checking

## Migration Path

For existing implementations using the old CustomFieldsImporter:

```php
// Old way
$importer = app(CustomFieldsImporter::class);
$columns = $importer->getColumns(Product::class);
$importer->saveCustomFieldValues($record, $data);

// New way
$columns = CustomFields::importer()
    ->forModel(Product::class)
    ->columns();
    
CustomFields::importer()
    ->forModel($record)
    ->saveCustomFieldValues($record, $data);
```

## Best Practices

1. **Always use hooks properly**:
   - Filter custom fields in `beforeFill()`
   - Save custom fields in `afterFill()`

2. **Handle visibility at the right time**:
   - Visibility checks can't be done during import column creation
   - Apply visibility rules when displaying/editing, not during import

3. **Use field data types for configuration**:
   - Let FieldDataType determine column behavior
   - Only override when field type implements FieldImportExportInterface

4. **Keep transformations simple**:
   - One `castStateUsing()` call per column
   - Combine all transformations in that single call

## Future Considerations

1. **Bulk import optimization**: Consider batch saving of custom field values
2. **Validation enhancement**: Add custom field validation that considers relationships between fields
3. **Import templates**: Generate Excel templates with custom field examples
4. **Progress tracking**: Show custom field import progress separately

## Conclusion

This integration provides a clean, maintainable way to import custom fields in Filament v4. By following Filament's architecture patterns and using the proper hooks, we achieve seamless integration without hacking around API limitations.