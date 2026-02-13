# Changelog

All notable changes to `custom-fields` will be documented in this file.

## 3.0.0 - 2026-01-XX

### Added
- **Record Field Type**: New field type for entity references with avatar support
- **Phone Input Component**: Country selector with validation via `propaganistas/laravel-phone`
- **Email/Phone Columns**: Table columns with clipboard support and improved display
- **RichTextColumn**: Better rich editor display in tables
- **Tags Filter**: Colored badges and filters for tags input
- **Upgrade Command**: Automated migration tool (`vendor/bin/custom-fields-upgrade`)
- **Optional Sections**: `SYSTEM_SECTIONS` feature flag for sectionless mode
- **Field Search**: Search functionality in custom fields management page
- **Field Deactivation**: Soft-disable non-system-defined fields and sections
- **Avatar Configuration**: Display options for entity references

### Changed
- **Filament 5 Required**: Upgraded from Filament 4 to Filament 5
- **Laravel 12 Required**: Upgraded from Laravel 11 to Laravel 12
- **Phone Field Format**: Now stores structured JSON with country codes instead of plain strings
- **Email Field Format**: Now stores JSON arrays to support multiple values

### Removed
- **Lookup Fields on Non-Record Types**: `lookup_type` setting removed from text, number, date, and other non-relational field types

### Migration
Run `vendor/bin/custom-fields-upgrade` to automatically migrate phone/email formats and lookup field settings.

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

## 1.0.0 - 2024-XX-XX

- Initial release
