# Configuration

> Configure all aspects of the Custom Fields package

## Overriding Models

The Custom Fields package allows you to replace the default models with your own implementations.

### Registering Custom Models

Register your custom models using the `CustomFields` class:

```php
use Relaticle\CustomFields\CustomFields;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        CustomFields::useCustomFieldModel(YourCustomField::class);
        CustomFields::useValueModel(YourCustomFieldValue::class);
        CustomFields::useOptionModel(YourCustomFieldOption::class);
        CustomFields::useSectionModel(YourCustomFieldSection::class);
    }
}
```

## Date Display Formats

By default, date and date-time fields use different formats depending on context (forms use `Y-m-d`, tables use `M j, Y`, etc.). You can set a single consistent format across all Filament components.

### Via Plugin (recommended)

```php
use Relaticle\CustomFields\CustomFieldsPlugin;

CustomFieldsPlugin::make()
    ->dateDisplayFormat('m/d/Y')
    ->dateTimeDisplayFormat('m/d/Y h:i A')
```

### Via Facade

```php
use Relaticle\CustomFields\CustomFields;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        CustomFields::useDateDisplayFormat('m/d/Y');
        CustomFields::useDateTimeDisplayFormat('m/d/Y h:i A');
    }
}
```

The configured format applies to:

<table>
<thead>
  <tr>
    <th>
      Component
    </th>
    
    <th>
      Without config
    </th>
    
    <th>
      With config
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      Form date pickers
    </td>
    
    <td>
      <code>
        Y-m-d
      </code>
      
       / <code>
        Y-m-d H:i:s
      </code>
    </td>
    
    <td>
      Your format
    </td>
  </tr>
  
  <tr>
    <td>
      Table columns
    </td>
    
    <td>
      <code>
        M j, Y
      </code>
      
       / <code>
        M j, Y H:i
      </code>
    </td>
    
    <td>
      Your format
    </td>
  </tr>
  
  <tr>
    <td>
      Infolist entries
    </td>
    
    <td>
      <code>
        Y-m-d
      </code>
      
       / <code>
        Y-m-d H:i:s
      </code>
    </td>
    
    <td>
      Your format
    </td>
  </tr>
  
  <tr>
    <td>
      Validation messages
    </td>
    
    <td>
      <code>
        M j, Y
      </code>
    </td>
    
    <td>
      Your format
    </td>
  </tr>
</tbody>
</table>

<alert type="info">

This only affects how dates are **displayed**. Storage formats (`Y-m-d` and `Y-m-d H:i:s`) remain unchanged.

</alert>

Pass `null` to reset back to component defaults:

```php
CustomFields::useDateDisplayFormat(null);
```

## Configuration File

The configuration file (`config/custom-fields.php`) allows you to customize all aspects of the Custom Fields package. It uses modern fluent configurators for type safety and better IDE support.

### Entity Configuration

Configure which models can have custom fields:

```php
'entity_configuration' => EntityConfigurator::configure()
    ->discover(app_path('Models'))  // Auto-discover models in this path
    ->cache(true),                  // Enable caching for performance
```

### Field Types Configuration

Control which field types are available:

```php
'field_type_configuration' => FieldTypeConfigurator::configure()
    ->enabled([])                   // Empty = all enabled
    ->disabled(['file-upload'])     // Disable specific field types
    ->discover(true)                // Auto-discover custom field types
    ->cache(enabled: false, ttl: 3400),
```

### Features Configuration

Configure package features using the enum-based system:

```php
'features' => FeatureConfigurator::configure()
    ->enable(
        CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
        CustomFieldsFeature::FIELD_ENCRYPTION,
        CustomFieldsFeature::FIELD_OPTION_COLORS,
        CustomFieldsFeature::UI_TABLE_COLUMNS,
        CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS,
        CustomFieldsFeature::UI_TABLE_FILTERS,
        CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE
    )
    ->disable(
        CustomFieldsFeature::SYSTEM_MULTI_TENANCY
    ),
```

### Management Interface

Configure the custom fields management page:

```php
'management' => [
    'slug' => 'custom-fields',      // URL slug
    'navigation_sort' => -1,        // Navigation sort order
    'navigation_group' => true,     // Group in navigation
    'cluster' => null,              // Optional cluster assignment
],
```

### Database Configuration

Customize table names and paths:

```php
'database' => [
    'migrations_path' => database_path('custom-fields'),
    'table_names' => [
        'custom_field_sections' => 'custom_field_sections',
        'custom_fields' => 'custom_fields',
        'custom_field_values' => 'custom_field_values',
        'custom_field_options' => 'custom_field_options',
    ],
    'column_names' => [
        'tenant_foreign_key' => 'tenant_id',
    ],
],
```

## Available Features

The package supports these features that can be enabled/disabled:

<table>
<thead>
  <tr>
    <th>
      Feature
    </th>
    
    <th>
      Description
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        FIELD_CONDITIONAL_VISIBILITY
      </code>
    </td>
    
    <td>
      Show/hide fields based on conditions
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        FIELD_ENCRYPTION
      </code>
    </td>
    
    <td>
      Encrypt sensitive field values
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        FIELD_OPTION_COLORS
      </code>
    </td>
    
    <td>
      Color-coded options for select fields
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        FIELD_CODE_AUTO_GENERATE
      </code>
    </td>
    
    <td>
      Auto-generate field codes from names
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        FIELD_MULTI_VALUE
      </code>
    </td>
    
    <td>
      Allow multiple values per field
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        FIELD_UNIQUE_VALUE
      </code>
    </td>
    
    <td>
      Enforce unique constraint per entity type
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        FIELD_VALIDATION_RULES
      </code>
    </td>
    
    <td>
      Enable validation rule configuration
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UI_TABLE_COLUMNS
      </code>
    </td>
    
    <td>
      Show custom fields as table columns
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UI_TOGGLEABLE_COLUMNS
      </code>
    </td>
    
    <td>
      Allow users to toggle column visibility
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UI_TOGGLEABLE_COLUMNS_HIDDEN_DEFAULT
      </code>
    </td>
    
    <td>
      Hide toggleable columns by default
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UI_TABLE_FILTERS
      </code>
    </td>
    
    <td>
      Enable filtering by custom field values
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UI_FIELD_WIDTH_CONTROL
      </code>
    </td>
    
    <td>
      Custom field width per field
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UI_SECTION_WIDTH_CONTROL
      </code>
    </td>
    
    <td>
      Section-level layout width (25/33/50/66/75/100)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        SYSTEM_MANAGEMENT_INTERFACE
      </code>
    </td>
    
    <td>
      Enable the management interface
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        SYSTEM_MULTI_TENANCY
      </code>
    </td>
    
    <td>
      Enable multi-tenant isolation
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        SYSTEM_SECTIONS
      </code>
    </td>
    
    <td>
      Enable field grouping in sections
    </td>
  </tr>
</tbody>
</table>

<alert type="info">

If your custom models include tenant-specific scoping logic, you'll need to register a [custom tenant resolver](#custom-tenant-resolution) to ensure validation works correctly.

</alert>

<alert type="info">

`UI_SECTION_WIDTH_CONTROL` is disabled by default. Enable it by adding `CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL` to the `->enable(...)` list in your published config. Once enabled, each `SECTION` and `FIELDSET` (not headless sections) can render at a fraction of the row width using the same `CustomFieldWidth` enum used for field-level width. Section widths don't need to sum to 12 — the grid wraps, and sections stack full-width on mobile.

Both section and field widths are fractions of the standard 12-column custom fields grid — `50%` renders as a 6-of-12 column span. If you embed custom fields inside a grid with a different column count, the visual fraction follows that grid instead.

</alert>

## Configuration Examples

### Restricting Field Types

Limit available field types in production:

```php
'field_type_configuration' => FieldTypeConfigurator::configure()
    ->enabled([
        'text',
        'textarea',
        'number',
        'select',
        'checkbox',
        'date',
    ])
    ->disabled([
        'rich-editor',      // Disable rich content editors
        'markdown-editor',  // Disable markdown editor
        'file-upload',      // Disable file uploads
    ]),
```

### Performance Configuration

Optimize for production:

```php
'entity_configuration' => EntityConfigurator::configure()
    ->discover(app_path('Models'))
    ->cache(env('CUSTOM_FIELDS_CACHE', true)),  // Enable caching

'field_type_configuration' => FieldTypeConfigurator::configure()
    ->cache(enabled: true, ttl: 3600),          // Cache field types
```

### Multi-Tenancy Setup

Enable tenant isolation:

```php
'features' => FeatureConfigurator::configure()
    ->enable(
        CustomFieldsFeature::SYSTEM_MULTI_TENANCY,
        // ... other features
    ),

'database' => [
    // ... other config
    'column_names' => [
        'tenant_foreign_key' => 'tenant_id',  // Your tenant foreign key
    ],
],
```

#### Custom Tenant Resolution

If you've extended the `CustomField` or `CustomFieldSection` models with custom tenant handling (e.g., custom global scopes), register a tenant resolver to ensure validation and queries respect your custom logic:

```php
use Relaticle\CustomFields\CustomFields;

// In your AppServiceProvider or plugin boot method
CustomFields::resolveTenantUsing(fn() => auth()->user()?->company_id);
```

<alert type="success">

The custom resolver takes priority over Filament's built-in tenancy, giving you complete control over tenant resolution.

</alert>

**Common Patterns:**

```php
// Auth-based tenancy
CustomFields::resolveTenantUsing(fn() => auth()->user()?->company_id);

// Header-based (APIs)
CustomFields::resolveTenantUsing(fn() => request()->header('X-Tenant-ID'));

// Session-based
CustomFields::resolveTenantUsing(fn() => session('current_tenant_id'));
```

<alert type="success">

Need help? Check that your resolver returns the correct tenant ID using `TenantContextService::getCurrentTenantId()` in your application.

</alert>

## Best Practices

### Performance Optimization

1. **Enable Caching**: Always enable caching in production
2. **Limit Discovery**: Only discover models you need
3. **Restrict Field Types**: Only enable field types you use

### Security Considerations

1. **Disable Unused Features**: Turn off features you don't need
2. **Restrict Field Types**: Disable potentially unsafe field types like rich editors
3. **Enable Multi-Tenancy**: Always enable in multi-tenant applications

### Development vs Production

Use environment variables for flexible configuration:

```php
'entity_configuration' => EntityConfigurator::configure()
    ->discover(app_path('Models'))
    ->cache(env('CUSTOM_FIELDS_CACHE', !app()->isLocal())),

'field_type_configuration' => FieldTypeConfigurator::configure()
    ->cache(enabled: env('CUSTOM_FIELDS_CACHE_TYPES', true)),
```
