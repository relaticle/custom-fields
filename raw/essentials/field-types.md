# Field Types

> Built-in field types and how to create custom ones

Custom Fields comes with 20+ built-in field types and allows you to create your own custom field types for specialized functionality.

## Built-in Field Types

Custom Fields includes 20+ pre-configured field types:

<table>
<thead>
  <tr>
    <th>
      Field Type
    </th>
    
    <th>
      Key
    </th>
    
    <th>
      Data Type
    </th>
    
    <th>
      Description
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <strong>
        Text Input
      </strong>
    </td>
    
    <td>
      <code>
        text
      </code>
    </td>
    
    <td>
      Text
    </td>
    
    <td>
      Basic text input with validation
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Email
      </strong>
    </td>
    
    <td>
      <code>
        email
      </code>
    </td>
    
    <td>
      Multi-choice
    </td>
    
    <td>
      Email input with validation
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Phone
      </strong>
    </td>
    
    <td>
      <code>
        phone
      </code>
    </td>
    
    <td>
      Multi-choice
    </td>
    
    <td>
      Phone number input
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Link
      </strong>
    </td>
    
    <td>
      <code>
        link
      </code>
    </td>
    
    <td>
      Multi-choice
    </td>
    
    <td>
      URL validation and formatting
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Textarea
      </strong>
    </td>
    
    <td>
      <code>
        textarea
      </code>
    </td>
    
    <td>
      Text
    </td>
    
    <td>
      Multi-line text input
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Rich Editor
      </strong>
    </td>
    
    <td>
      <code>
        rich-editor
      </code>
    </td>
    
    <td>
      Text
    </td>
    
    <td>
      WYSIWYG editor with formatting
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Markdown Editor
      </strong>
    </td>
    
    <td>
      <code>
        markdown-editor
      </code>
    </td>
    
    <td>
      Text
    </td>
    
    <td>
      Markdown syntax with preview
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Number
      </strong>
    </td>
    
    <td>
      <code>
        number
      </code>
    </td>
    
    <td>
      Numeric
    </td>
    
    <td>
      Numeric input with min/max validation
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Currency
      </strong>
    </td>
    
    <td>
      <code>
        currency
      </code>
    </td>
    
    <td>
      Float
    </td>
    
    <td>
      Currency formatting with locale support
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Tags Input
      </strong>
    </td>
    
    <td>
      <code>
        tags-input
      </code>
    </td>
    
    <td>
      Multi-choice
    </td>
    
    <td>
      Multiple tags with autocomplete
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Select
      </strong>
    </td>
    
    <td>
      <code>
        select
      </code>
    </td>
    
    <td>
      Single-choice
    </td>
    
    <td>
      Single selection dropdown
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Multi-Select
      </strong>
    </td>
    
    <td>
      <code>
        multi-select
      </code>
    </td>
    
    <td>
      Multi-choice
    </td>
    
    <td>
      Multiple selections
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Radio
      </strong>
    </td>
    
    <td>
      <code>
        radio
      </code>
    </td>
    
    <td>
      Single-choice
    </td>
    
    <td>
      Single choice radio buttons
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Checkbox
      </strong>
    </td>
    
    <td>
      <code>
        checkbox
      </code>
    </td>
    
    <td>
      Boolean
    </td>
    
    <td>
      Simple true/false toggle
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Checkbox List
      </strong>
    </td>
    
    <td>
      <code>
        checkbox-list
      </code>
    </td>
    
    <td>
      Multi-choice
    </td>
    
    <td>
      Multiple checkboxes
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Toggle
      </strong>
    </td>
    
    <td>
      <code>
        toggle
      </code>
    </td>
    
    <td>
      Boolean
    </td>
    
    <td>
      Switch-style toggle
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Toggle Buttons
      </strong>
    </td>
    
    <td>
      <code>
        toggle-buttons
      </code>
    </td>
    
    <td>
      Single-choice
    </td>
    
    <td>
      Button group selection
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Date
      </strong>
    </td>
    
    <td>
      <code>
        date
      </code>
    </td>
    
    <td>
      Date
    </td>
    
    <td>
      Date picker
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Date Time
      </strong>
    </td>
    
    <td>
      <code>
        date-time
      </code>
    </td>
    
    <td>
      DateTime
    </td>
    
    <td>
      Date and time picker
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Color Picker
      </strong>
    </td>
    
    <td>
      <code>
        color-picker
      </code>
    </td>
    
    <td>
      Text
    </td>
    
    <td>
      Visual color selection
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        File Upload
      </strong>
    </td>
    
    <td>
      <code>
        file-upload
      </code>
    </td>
    
    <td>
      String
    </td>
    
    <td>
      File upload with validation
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Record
      </strong>
    </td>
    
    <td>
      <code>
        record
      </code>
    </td>
    
    <td>
      Multi-choice
    </td>
    
    <td>
      Polymorphic model lookup
    </td>
  </tr>
</tbody>
</table>

## Creating Custom Field Types

You have flexible options for extending Custom Fields:

- **Create entirely new field types** for specialized functionality
- **Extend existing built-in field types** to add custom behavior
- **Replace built-in field types** by creating custom ones with the same key

This allows you to customize any field type behavior while maintaining compatibility with the existing system.

### Generate a Field Type

Use the Artisan command to create a new field type:

```bash
php artisan make:field-type StarRating
```

This creates a complete field type class in `app/Filament/FieldTypes/`.

### Field Type Structure

Custom field types extend `BaseFieldType` and use the `FieldSchema` system:

```php
<?php

namespace App\Filament\FieldTypes;

use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Validation\Capabilities\MaxValueCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinValueCapability;

class StarRatingFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::numeric()
            ->key('acme-star-rating')
            ->label('Star Rating')
            ->icon('heroicon-o-star')
            ->formComponent(function (CustomField $customField) {
                return Select::make($customField->getFieldName())
                    ->options([
                        1 => '⭐ Poor',
                        2 => '⭐⭐ Fair',
                        3 => '⭐⭐⭐ Good',
                        4 => '⭐⭐⭐⭐ Very Good',
                        5 => '⭐⭐⭐⭐⭐ Excellent',
                    ])
                    ->native(false);
            })
            ->tableColumn(function (CustomField $customField) {
                return TextColumn::make($customField->getFieldName())
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'No rating';
                        return str_repeat('⭐', (int) $state) . " ($state/5)";
                    });
            })
            ->infolistEntry(function (CustomField $customField) {
                return TextEntry::make($customField->getFieldName())
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'No rating provided';
                        $rating = (int) $state;
                        $labels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very Good', 5 => 'Excellent'];
                        return str_repeat('⭐', $rating) . " - {$labels[$rating]} ($rating/5)";
                    });
            })
            ->priority(45)
            ->withValidationCapabilities(
                MinValueCapability::class,
                MaxValueCapability::class,
            );
    }
}
```

### Register Your Field Type

Register your custom field type in your Filament Panel Provider:

```php
<?php

namespace App\Providers\Filament;

use App\Filament\FieldTypes\StarRatingFieldType;
use Filament\Panel;
use Filament\PanelProvider;
use Relaticle\CustomFields\CustomFieldsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                CustomFieldsPlugin::make()
                    ->registerFieldTypes([
                        StarRatingFieldType::class,
                    ]),
            ]);
    }
}
```

### Extending and Replacing Field Types

You have additional flexibility with custom field types:

- **Replace built-in field types**: Create a custom field type with the same key as a built-in type (e.g., `file-upload`) to completely override it with your own implementation and behavior

This allows you to customize or completely replace any built-in field type behavior while maintaining system compatibility.

## FieldSchema API

The `FieldSchema` class provides a fluent API for configuring field types. Choose the appropriate factory method based on your field's data type:

### Data Type Factory Methods

```php
// Text-based fields
FieldSchema::text()       // For TEXT data type (long text)
FieldSchema::string()     // For STRING data type (short text)

// Numeric fields
FieldSchema::numeric()    // For NUMERIC data type (integers)
FieldSchema::float()      // For FLOAT data type (decimals)

// Date/time fields
FieldSchema::date()       // For DATE data type
FieldSchema::dateTime()   // For DATE_TIME data type

// Boolean fields
FieldSchema::boolean()    // For BOOLEAN data type

// Choice fields
FieldSchema::singleChoice()  // For SINGLE_CHOICE data type
FieldSchema::multiChoice()   // For MULTI_CHOICE data type
```

### Configuration Methods

```php
->key('field-key')                    // Unique field identifier
->label('Field Label')                // Display name
->icon('heroicon-o-star')            // Icon for field type
->priority(50)                       // Sort order (lower = first)
->formComponent($component)          // Form field component
->tableColumn($column)               // Table column component
->tableFilter($filter)               // Table filter component
->infolistEntry($entry)             // Read-only display component
->withValidationCapabilities(...)   // Validation capabilities users can configure per field
->defaultValidationRules($rules)    // Validation rules always applied to this field type
->searchable()                      // Enable globally-searchable in tables (default: true)
->sortable()                        // Enable column sorting in tables (default: true)
->filterable()                      // Enable table filter for this field type (default: false)
->encryptable()                     // Allow users to enable encryption for this field
```

<callout type="info">

A field type needs **both** `filterable()` and `tableFilter()` for filters to appear in tables. `filterable()` enables the capability; `tableFilter()` provides the filter component.

</callout>

### Settings

Add type-specific configuration options that appear in the field editor when your field type is selected. Settings are stored per custom field instance.

`withSettings()` takes two arguments:

1. A [Spatie Laravel Data](https://spatie.be/docs/laravel-data/v4/introduction) class defining the settings structure
2. An array of Filament form components (or a `Closure` returning them) for the settings UI

```php
use Spatie\LaravelData\Data;
use Filament\Forms\Components\TextInput;

class CurrencySettings extends Data
{
    public function __construct(
        public string $currencySymbol = '$',
        public int $decimalPlaces = 2,
    ) {}
}

// In your field type's configure() method:
return FieldSchema::float()
    ->key('acme-currency')
    ->label('Currency')
    ->withSettings(CurrencySettings::class, [
        TextInput::make('currency_symbol')
            ->label('Currency Symbol')
            ->default('$'),
        TextInput::make('decimal_places')
            ->label('Decimal Places')
            ->numeric()
            ->default(2),
    ]);
```

The settings form components are automatically shown/hidden based on the selected field type. Access stored settings via `$customField->settings` in your component closures.

### Multi-Value & Constraints

Control whether users can store multiple values in a single field and enforce uniqueness.

```php
->supportsMultiValue()
->supportsUniqueConstraint()
->defaultItemValidationRules($rules)
->requiresLookupType()
```

**supportsMultiValue()** -- Shows an "Allow Multiple Values" toggle in the field editor. When enabled by the user, the field accepts multiple values (e.g., multiple emails or phone numbers) and a "Max Values" input appears. Used by Email, Phone, Link, and Record field types.

**supportsUniqueConstraint()** -- Shows a "Unique per Entity Type" toggle in the field editor. When enabled by the user, a `UniqueCustomFieldValue` validation rule is applied to prevent duplicate values across records of the same entity type. Used by Email, Phone, Link, Text, Textarea, and Number field types.

**defaultItemValidationRules(array $rules)** -- Validation rules automatically applied to **each individual item** in a multi-value field. These are not user-configurable -- they are hardcoded per field type. Only available for `MULTI_CHOICE` data types; throws `InvalidArgumentException` otherwise.

**requiresLookupType()** -- Replaces the user-defined options UI with an entity type selector. The field stores references to records of the selected entity type instead of static option values. Import/export treats these as entity references. Currently used by the Record field type.

Example -- the Email field type combines these to support multiple unique emails with per-item validation:

```php
return FieldSchema::multiChoice()
    ->key('email')
    ->supportsMultiValue()
    ->supportsUniqueConstraint()
    ->withArbitraryValues()
    ->withoutUserOptions()
    ->defaultItemValidationRules(['email', 'max:254']);
```

### Import Customization

Control how field values appear in import templates and how raw import data is transformed before storage.

```php
->importExample('99.99')
->importTransformer(function (mixed $state): ?float {
    // Transform raw import value into the correct storage format
})
```

**importExample(string $example)** -- Displayed as a sample value in the import template UI, helping users understand the expected format for this field type.

**importTransformer(Closure $transformer)** -- Receives the raw value from the import file and returns the transformed value for storage. Without a transformer, the raw value is stored as-is. The closure signature is `function (mixed $state): mixed`.

Example -- the Currency field type strips formatting characters on import:

```php
return FieldSchema::float()
    ->key('currency')
    ->importExample('99.99')
    ->importTransformer(function (mixed $state): ?float {
        if (blank($state)) {
            return null;
        }

        if (is_string($state)) {
            $state = preg_replace('/[^0-9.-]/', '', $state);
        }

        return round(floatval($state), 2);
    });
```

### Component Types

You can define components in two ways:

#### 1. Class References (Simple)

For basic components, reference Filament classes directly:

```php
->formComponent(TextInput::class)
->tableColumn(TextColumn::class)
->infolistEntry(TextEntry::class)
```

#### 2. Closures (Flexible)

For customized components, use closures that return configured components:

```php
->formComponent(function (CustomField $customField) {
    return TextInput::make($customField->getFieldName())
        ->label($customField->name)
        ->maxLength(255);
})
```

### Choice Fields and Options

Choice fields (select, radio, checkboxes) can handle options in different ways:

#### User-Defined Options (Default)

Users define their own options when creating fields:

```php
return FieldSchema::singleChoice()
    ->key('priority-level')
    ->formComponent(function (CustomField $customField) {
        return Select::make($customField->getFieldName());
        // Options automatically applied by the system
    });
```

#### Built-in Options

Field type provides predefined options:

```php
return FieldSchema::singleChoice()
    ->key('priority-level')
    ->withoutUserOptions()  // Disable user options
    ->formComponent(function (CustomField $customField) {
        return Select::make($customField->getFieldName())
            ->options([
                1 => 'Low',
                2 => 'Medium',
                3 => 'High',
                4 => 'Critical',
            ]);
    });
```

#### Arbitrary Values

Accept both predefined and new user-typed values:

```php
return FieldSchema::multiChoice()
    ->key('product-tags')
    ->withArbitraryValues()  // Allow new values
    ->formComponent(function (CustomField $customField) {
        return TagsInput::make($customField->getFieldName());
    });
```

## Data Types

Custom Fields supports these data types for storage optimization and validation compatibility:

```php
enum FieldDataType: string
{
    case STRING = 'string';          // Short text, URLs, identifiers
    case TEXT = 'text';              // Long text, rich content, markdown
    case NUMERIC = 'numeric';        // Integers, counts
    case FLOAT = 'float';            // Decimal numbers, currency
    case DATE = 'date';              // Date only
    case DATE_TIME = 'date_time';    // Date with time
    case BOOLEAN = 'boolean';        // True/false, checkboxes, toggles
    case SINGLE_CHOICE = 'single_choice';  // Select, radio buttons
    case MULTI_CHOICE = 'multi_choice';    // Multi-select, checkbox lists, tags
}
```

## Best Practices

1. **Use Existing Filament Components**: Build on Filament's components like `Select`, `TextInput`, etc.
2. **Follow Naming Conventions**:

  - Use `kebab-case` for keys (e.g., `star-rating`, `country-select`)
  - **Use a project prefix** for custom types (e.g., `acme-star-rating`) to avoid conflicts with built-in types
  - Only skip the prefix when intentionally replacing a built-in type
3. **Choose the Right Data Type**: Select the data type that matches how your field's values should be stored
4. **Use Closures for Flexibility**: For complex components, use closure-based definitions
5. **Test Your Components**: Ensure your field type works in forms, tables, and infolists
6. **Consider Validation**: Only allow validation rules that make sense for your field type

## Field Type Priority

Field types are ordered by priority (lower numbers appear first):

- **10-20**: Common text fields
- **30-40**: Selection fields
- **50-60**: Specialized fields
- **70+**: Advanced fields

## Troubleshooting

### Field Type Not Appearing

If your custom field type doesn't appear in the dropdown:

1. Ensure your field type class extends `BaseFieldType`
2. Verify the field type is registered in your panel provider
3. Clear Laravel's cache: `php artisan cache:clear`
4. Check that the `configure()` method returns a valid `FieldSchema`

### Components Not Rendering

If your components don't render correctly:

1. Verify you're using `$customField->getFieldName()` for field names
2. For closure-based components, ensure closures return valid Filament components
3. Test with simple components first before adding complexity
4. Check that you're importing all necessary Filament component classes

Your custom field type will now appear in the field type dropdown when creating new custom fields!
