# Changelog

All notable changes to `custom-fields` will be documented in this file.

## 2.0.0 - 2025-XX-XX

### Added
- **Fluent Builder API**: New `CustomFields::form()`, `CustomFields::table()`, `CustomFields::infolist()` builders
- **Field Type Configurator**: Fluent configuration for enabling/disabling field types
- **BaseFieldType with FieldSchema**: Cleaner field type definitions with less boilerplate
- **Settings JSON Column**: Added to `custom_field_options` table

### Changed
- **Namespace Changes**: `InteractsWithCustomFields` trait moved to `Relaticle\CustomFields\Concerns`
- **Filament 4 Required**: Upgraded from Filament 3 to Filament 4

### Deprecated
- Component-based integration (`CustomFieldsComponent::make()`) in favor of builder API

### Migration
See the [upgrade guide](/custom-fields/v2/getting-started/upgrade-guide) for detailed migration steps from v1.

## 1.0.0 - 2024-XX-XX

- Initial release
