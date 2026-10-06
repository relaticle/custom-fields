# Data Model

> Understanding the Custom Fields database architecture

## Architecture Overview

The Custom Fields plugin employs a **Hybrid Entity-Attribute-Value (EAV) with Type Polymorphism** design that balances flexibility with performance. Unlike traditional EAV models that suffer from type conversion overhead and poor query performance, this architecture uses typed storage columns and strategic indexing to maintain database-level optimizations while enabling dynamic field creation.

### Entity Relationships

<table>
<thead>
  <tr>
    <th>
      Parent
    </th>
    
    <th>
      Relationship
    </th>
    
    <th>
      Child
    </th>
    
    <th>
      Description
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      Entity (polymorphic)
    </td>
    
    <td>
      one-to-many
    </td>
    
    <td>
      <code>
        custom_field_sections
      </code>
    </td>
    
    <td>
      Each entity type has its own sections
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        custom_field_sections
      </code>
    </td>
    
    <td>
      one-to-many
    </td>
    
    <td>
      <code>
        custom_fields
      </code>
    </td>
    
    <td>
      Sections contain field definitions
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        custom_fields
      </code>
    </td>
    
    <td>
      one-to-many
    </td>
    
    <td>
      <code>
        custom_field_options
      </code>
    </td>
    
    <td>
      Select/checkbox fields have options
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        custom_fields
      </code>
    </td>
    
    <td>
      one-to-many
    </td>
    
    <td>
      <code>
        custom_field_values
      </code>
    </td>
    
    <td>
      Fields store values per entity instance
    </td>
  </tr>
  
  <tr>
    <td>
      Entity (polymorphic)
    </td>
    
    <td>
      one-to-many
    </td>
    
    <td>
      <code>
        custom_field_values
      </code>
    </td>
    
    <td>
      Entity instances have field values
    </td>
  </tr>
</tbody>
</table>

### Table Schemas

<tabs>
<tab label="Sections">
<table>
<thead>
  <tr>
    <th>
      Column
    </th>
    
    <th>
      Type
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
        id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Primary key
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        entity_type
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Polymorphic entity class
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        code
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Unique per entity type (+ tenant, when multi-tenancy is enabled)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        name
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Display name
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        type
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Section type
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        width
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Section layout width (<code>
        CustomFieldWidth
      </code>
      
       enum: 25/33/50/66/75/100). Requires the <code>
        UI_SECTION_WIDTH_CONTROL
      </code>
      
       feature.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        sort_order
      </code>
    </td>
    
    <td>
      int
    </td>
    
    <td>
      Display order
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        active
      </code>
    </td>
    
    <td>
      bool
    </td>
    
    <td>
      Enabled flag
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        system_defined
      </code>
    </td>
    
    <td>
      bool
    </td>
    
    <td>
      Protected from user deletion
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        settings
      </code>
    </td>
    
    <td>
      json
    </td>
    
    <td>
      Additional configuration
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        tenant_id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Optional multi-tenancy
    </td>
  </tr>
</tbody>
</table>
</tab>

<tab label="Fields">
<table>
<thead>
  <tr>
    <th>
      Column
    </th>
    
    <th>
      Type
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
        id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Primary key
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        custom_field_section_id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Parent section
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        entity_type
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Polymorphic entity class
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        code
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Unique per entity type and section (+ tenant, when multi-tenancy is enabled) — not globally unique, so the same code can exist in two different sections. See <a href="/essentials/builder-scoping">
        Builder Scoping
      </a>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        name
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Display name
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        type
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Field type (text, number, etc.)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        lookup_type
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      For lookup fields
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        width
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Layout width
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        sort_order
      </code>
    </td>
    
    <td>
      int
    </td>
    
    <td>
      Display order
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        validation_rules
      </code>
    </td>
    
    <td>
      json
    </td>
    
    <td>
      Capability-driven validation config (e.g. <code>
        required
      </code>
      
      , <code>
        min_value
      </code>
      
      , <code>
        decimal_places
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        active
      </code>
    </td>
    
    <td>
      bool
    </td>
    
    <td>
      Enabled flag
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        system_defined
      </code>
    </td>
    
    <td>
      bool
    </td>
    
    <td>
      Protected from user deletion
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        settings
      </code>
    </td>
    
    <td>
      json
    </td>
    
    <td>
      Type-specific configuration
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        tenant_id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Optional multi-tenancy
    </td>
  </tr>
</tbody>
</table>
</tab>

<tab label="Options">
<table>
<thead>
  <tr>
    <th>
      Column
    </th>
    
    <th>
      Type
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
        id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Primary key
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        custom_field_id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Parent field
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        name
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Option label
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        sort_order
      </code>
    </td>
    
    <td>
      int
    </td>
    
    <td>
      Display order
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        settings
      </code>
    </td>
    
    <td>
      json
    </td>
    
    <td>
      Additional configuration
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        tenant_id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Optional multi-tenancy
    </td>
  </tr>
</tbody>
</table>
</tab>

<tab label="Values">
<table>
<thead>
  <tr>
    <th>
      Column
    </th>
    
    <th>
      Type
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
        id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Primary key
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        entity_type
      </code>
    </td>
    
    <td>
      string
    </td>
    
    <td>
      Polymorphic entity class
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        entity_id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Polymorphic entity ID
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        custom_field_id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Field definition
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        string_value
      </code>
    </td>
    
    <td>
      text
    </td>
    
    <td>
      String and file types, and single-choice types when option keys are strings
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        text_value
      </code>
    </td>
    
    <td>
      longtext
    </td>
    
    <td>
      Text types: text input, textarea, rich editor, markdown, color picker
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        boolean_value
      </code>
    </td>
    
    <td>
      bool
    </td>
    
    <td>
      Checkbox and toggle
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        integer_value
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Number, and single-choice types when option keys are integers
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        float_value
      </code>
    </td>
    
    <td>
      double
    </td>
    
    <td>
      Currency
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        date_value
      </code>
    </td>
    
    <td>
      date
    </td>
    
    <td>
      Date
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        datetime_value
      </code>
    </td>
    
    <td>
      datetime
    </td>
    
    <td>
      Date Time
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        json_value
      </code>
    </td>
    
    <td>
      json
    </td>
    
    <td>
      Every multi-choice type: email, phone, link, tags, multi-select, checkbox list, record
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        tenant_id
      </code>
    </td>
    
    <td>
      bigint
    </td>
    
    <td>
      Optional multi-tenancy
    </td>
  </tr>
</tbody>
</table>
</tab>
</tabs>

## How a Value Is Stored

Each record holds one row per field in `custom_field_values`. The row uses one typed column and leaves the others `null`.

### The write path

Every write goes through the same four steps, whether it comes from a Filament form, an import, or your own code.

1. `saveCustomFields()` or `saveCustomFieldValue()` on the model finds or creates the value row.
2. `CustomFieldValue::setValue()` picks the column from the field type's data type.
3. `SafeValueConverter::toDbSafe()` casts the value to that column's PHP type.
4. The field type's `normalize()` rewrites each string into its stored form.

```php
$company->saveCustomFieldValue($websiteField, ['https://Example.com/Pricing/']);

$company->getCustomFieldValue($websiteField);
// ['https://example.com/Pricing']
```

`saveCustomFields()` takes an array keyed by field code. A field whose code is missing from the array is saved as `null`.

### One column per data type

The field type's data type decides the column. The [field types table](/essentials/field-types#built-in-field-types) lists the data type of every built-in type.

<table>
<thead>
  <tr>
    <th>
      Data type
    </th>
    
    <th>
      Column
    </th>
    
    <th>
      PHP value read back
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      String, File
    </td>
    
    <td>
      <code>
        string_value
      </code>
    </td>
    
    <td>
      <code>
        string
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Text
    </td>
    
    <td>
      <code>
        text_value
      </code>
    </td>
    
    <td>
      <code>
        string
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Numeric
    </td>
    
    <td>
      <code>
        integer_value
      </code>
    </td>
    
    <td>
      <code>
        int
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Float
    </td>
    
    <td>
      <code>
        float_value
      </code>
    </td>
    
    <td>
      <code>
        float
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Boolean
    </td>
    
    <td>
      <code>
        boolean_value
      </code>
    </td>
    
    <td>
      <code>
        bool
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Date
    </td>
    
    <td>
      <code>
        date_value
      </code>
    </td>
    
    <td>
      date instance
    </td>
  </tr>
  
  <tr>
    <td>
      DateTime
    </td>
    
    <td>
      <code>
        datetime_value
      </code>
    </td>
    
    <td>
      date-time instance
    </td>
  </tr>
  
  <tr>
    <td>
      Single-choice
    </td>
    
    <td>
      <code>
        integer_value
      </code>
      
      , or <code>
        string_value
      </code>
      
       when option keys are strings
    </td>
    
    <td>
      option key
    </td>
  </tr>
  
  <tr>
    <td>
      Multi-choice
    </td>
    
    <td>
      <code>
        json_value
      </code>
    </td>
    
    <td>
      <code>
        array
      </code>
    </td>
  </tr>
</tbody>
</table>

Email, phone and link are multi-choice types. They are stored as a JSON list in `json_value`, even when the field allows one value.

### The stored form of each type

Normalization runs on strings only. For a list it runs on each item, drops an item that becomes empty, and collapses items that become equal. A single value that becomes empty is stored as `null`.

<table>
<thead>
  <tr>
    <th>
      Type
    </th>
    
    <th>
      Typed
    </th>
    
    <th>
      Stored
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      Phone
    </td>
    
    <td>
      <code>
        +1 (415) 555-0100
      </code>
    </td>
    
    <td>
      <code>
        +14155550100
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Phone with an extension
    </td>
    
    <td>
      <code>
        +1 415 555 0100 ext. 12
      </code>
    </td>
    
    <td>
      <code>
        +14155550100;ext=12
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Phone without a leading <code>
        +
      </code>
    </td>
    
    <td>
      <code>
        415 555 0100
      </code>
    </td>
    
    <td>
      <code>
        415 555 0100
      </code>
      
       (trimmed, not parsed)
    </td>
  </tr>
  
  <tr>
    <td>
      Link
    </td>
    
    <td>
      <code>
        https://Example.com/Pricing/
      </code>
    </td>
    
    <td>
      <code>
        https://example.com/Pricing
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Link without a scheme
    </td>
    
    <td>
      <code>
        Acme.com/Path/
      </code>
    </td>
    
    <td>
      <code>
        acme.com/Path
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Email
    </td>
    
    <td>
      <code>
        Jane@Example.com
      </code>
    </td>
    
    <td>
      <code>
        Jane@Example.com
      </code>
      
       (as typed)
    </td>
  </tr>
  
  <tr>
    <td>
      Every other type
    </td>
    
    <td>
      as typed
    </td>
    
    <td>
      as typed
    </td>
  </tr>
</tbody>
</table>

A link keeps its scheme because `http://` and `https://` can open different pages.

A date-time value is stored exactly as it is given. The package converts no timezone, so pass every value in one timezone, such as UTC.

### Comparing values

`UniqueCustomFieldValue` compares stored forms. It asks the field type for `equivalentValues()`, the list of stored forms that count as the same value, and rejects a value when another record holds any of them.

For a link, that list is the normalized value plus the value with `https://`, with `http://`, and with no scheme. A stored `https://acme.com/pricing` blocks a typed `acme.com/pricing`.

Normalize a value the same way before you compare it in your own code:

```php
use Relaticle\CustomFields\Facades\CustomFieldsType;

$stored = CustomFieldsType::getFieldTypeInstance($field->type)->normalize($typed, $field);
```

### Reading and querying

`getCustomFieldValue()` returns the value of the typed column, decrypted when the field is encrypted. Call the `withCustomFieldValues()` scope first when you read many records, so the values load in one query.

To filter, query the typed column of the field. `CustomField::getValueColumn()` returns its name.

```php
// A scalar field
Company::whereHas('customFieldValues', fn ($query) => $query
    ->where('custom_field_id', $revenueField->getKey())
    ->where($revenueField->getValueColumn(), '>=', 1_000_000));

// A multi-choice field: compare against the stored form
Company::whereHas('customFieldValues', fn ($query) => $query
    ->where('custom_field_id', $websiteField->getKey())
    ->whereJsonContains('json_value', 'https://example.com/pricing'));
```

An encrypted field cannot be filtered or sorted in SQL, because the column holds ciphertext.

### Values stored by an older version

Normalization on every write path starts in 3.13. The package rewrites no existing row, so a value saved earlier keeps its old spelling until it is saved again. The [3.13 upgrade notes](https://github.com/relaticle/custom-fields/blob/3.x/UPGRADING.md) describe how to normalize old rows.

## Design Philosophy

### Type-Safe Flexibility

The schema uses multiple typed columns in `custom_field_values` rather than a single text column. This eliminates costly type conversions, enables native database sorting/filtering, and maintains data integrity through database-level constraints. When you store an integer, it's actually stored as an integer—not a string that needs parsing.

### Hierarchical Organization

Fields are organized into sections, providing logical grouping essential for complex forms. This two-level hierarchy supports progressive disclosure in UIs and administrative organization without adding complexity to simple use cases.

### Performance-First Indexing

Strategic composite indexes optimize the most common query patterns: entity lookup, field discovery, and polymorphic joins. The schema is designed for the queries you'll actually run, not theoretical completeness.

## Why This Schema Design

**Polymorphic Flexibility**: Any model can have custom fields without tight coupling or migration dependencies. Add custom fields to `Product`, `User`, `Order`—anything implementing the `HasCustomFields` interface.

**Multi-Tenant Isolation**: Optional tenant awareness is built into the core schema, not bolted on later. When enabled, all data is automatically isolated between tenants while maintaining query performance.

**Extensible Field Types**: Field types are pluggable through a clean interface. The `settings` JSON column provides unlimited extension points without schema changes.

**Efficient Querying**: Unlike traditional EAV models, this design supports efficient filtering and sorting on custom field values using native database types and proper indexing strategies.

## Performance Considerations

This schema excels with complex forms, multi-tenant applications, and admin interfaces requiring dynamic field management. The typed storage and strategic indexing make it suitable for production applications with significant data volumes.

Consider the performance implications for sparse data (many NULL values) and plan custom queries for complex cross-field reporting needs. The architecture prioritizes the common case: efficient field definition, value storage/retrieval, and entity-centric queries.

## Multi-Tenancy Support

When enabled, `tenant_id` is included in all unique constraints and automatically filtered through model scopes. This ensures complete data isolation while maintaining query performance through proper indexing.
