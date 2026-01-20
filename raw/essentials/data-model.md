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
      Unique identifier
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
      Unique identifier
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
      Laravel validation rules
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
      string
    </td>
    
    <td>
      For text fields
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        text_value
      </code>
    </td>
    
    <td>
      text
    </td>
    
    <td>
      For textarea fields
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
      For checkbox fields
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
      For integer fields
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
      For decimal fields
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
      For date fields
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
      For datetime fields
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
      For complex/array fields
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
