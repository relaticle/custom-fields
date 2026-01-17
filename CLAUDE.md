# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Custom Fields** is a Laravel/Filament plugin package that enables adding dynamic custom fields to any Eloquent model without database migrations.

### Key Features
- **21 Field Types** - Text, number, date, select, rich editor, file upload, and more
- **Conditional Visibility** - Show/hide fields based on other field values
- **Multi-tenancy** - Complete tenant isolation via TenantContextService
- **Filament Integration** - Forms, tables, infolists, and admin management interface
- **Import/Export** - Built-in CSV capabilities
- **Security** - Optional field encryption and type-safe validation

### Requirements
- PHP 8.3+
- Laravel 11.0+
- Filament 4.0+

### Package Details
- **Package Name**: `relaticle/custom-fields`
- **License**: AGPL-3.0
- **Documentation**: https://custom-fields.relaticle.com/

## Development Commands

### Testing

```bash
# Complete test suite (lint → refactor → PHPStan → type coverage → tests)
composer test

# Individual commands
composer test:pest          # Run all tests in parallel
composer test:arch          # Architecture tests only
composer test:types         # PHPStan static analysis (Level 5)
composer test:type-coverage # Type coverage (must be 100.0%)
composer test:lint          # Check code style (dry run)
composer test:refactor      # Check Rector rules (dry run)
composer test-coverage      # Tests with coverage report

# Running specific tests
vendor/bin/pest tests/path/to/test.php
vendor/bin/pest --filter="test name"
vendor/bin/pest --parallel   # Faster execution
vendor/bin/pest --dirty      # Only changed tests
vendor/bin/pest --retry      # Re-run failed tests
```

### Code Quality

```bash
# Auto-fix code style and apply Rector rules
composer lint

# Individual tools
rector                    # Apply automated refactoring
rector --dry-run         # Preview changes
pint                     # Format code
pint --test              # Check without changes
phpstan analyse          # Static analysis (Level 5)
```

### Frontend Build

```bash
# Development (watch mode)
npm run dev              # CSS and JS concurrently
npm run dev:styles       # CSS only
npm run dev:scripts      # JS only

# Production
npm run build            # CSS and JS
npm run build:styles     # CSS with PostCSS
npm run build:scripts    # JS with esbuild
```

## Architecture Overview

### Directory Structure

```
src/
├── Collections/              # FieldTypeCollection for type-safe collections
├── Concerns/                 # Shared traits
├── Console/Commands/         # Artisan commands (MakeFieldTypeCommand, etc.)
├── Contracts/                # 9 interfaces defining package contracts
├── Data/                     # 10 DTOs using Spatie Laravel Data
├── EntitySystem/             # Entity registration and discovery (5 classes)
├── Enums/                    # 9 type-safe enumerations
├── Exceptions/               # 4 custom exceptions
├── Facades/                  # CustomFields, CustomFieldsType, Entities
├── FeatureSystem/            # Feature flag management
├── FieldTypeSystem/          # Core field type architecture
│   ├── Definitions/          # 21 field type implementations
│   ├── BaseFieldType.php     # Abstract base class
│   ├── FieldSchema.php       # Builder for field configuration
│   └── FieldManager.php      # Field type registry
├── Filament/
│   ├── Integration/          # Filament component integration
│   │   ├── Base/             # Abstract component classes
│   │   ├── Builders/         # FormBuilder, TableBuilder, InfolistBuilder
│   │   ├── Components/       # Forms (14), Infolists (7), Tables (8)
│   │   ├── Factories/        # 9 component factories
│   │   └── Migrations/       # Field migration system
│   └── Management/           # Admin UI for managing custom fields
│       ├── Pages/            # CustomFieldsManagementPage
│       └── Schemas/          # FieldForm, SectionForm
├── Livewire/                 # Livewire components with Concerns/
├── Models/                   # Eloquent models
│   ├── CustomField.php       # Field definition
│   ├── CustomFieldValue.php  # Field values
│   ├── CustomFieldSection.php # Field groupings
│   ├── CustomFieldOption.php # Select/radio options
│   ├── Concerns/             # UsesCustomFields, HasFieldType, Activable
│   ├── Contracts/            # HasCustomFields interface
│   └── Scopes/               # TenantScope, ActivableScope, SortOrderScope
├── Providers/                # 4 service providers
├── Rules/                    # UniqueCustomFieldValue validation rule
├── Services/
│   ├── TenantContextService.php  # Multi-tenancy
│   ├── ValidationService.php     # Dynamic validation
│   ├── DefaultSectionService.php # Default sections
│   ├── Options/              # ComponentOptionsExtractor
│   ├── ValueResolver/        # 4 value resolution classes
│   └── Visibility/           # 3 visibility logic classes
└── Support/                  # CodeGenerator, SafeValueConverter, Utils
```

### Core Design Patterns

1. **Service Provider Architecture** - Field types, validation, entities registered via providers
2. **Factory Pattern** - Component creation via `FieldComponentFactory`, `ColumnFactory`, etc.
3. **Builder Pattern** - `FormBuilder`, `TableBuilder`, `InfolistBuilder` for complex UI
4. **Data Transfer Objects** - Type-safe structures using Spatie Laravel Data
5. **Repository/Service Pattern** - Business logic in services

### Field Type System

Field types extend `BaseFieldType` and implement `configure()` returning a `FieldSchema`:

```php
class TextFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('text')
            ->label('Text')
            ->icon('mdi-form-textbox')
            ->formComponent(TextInputComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(TextEntry::class)
            ->encryptable()
            ->supportsUniqueConstraint()
            ->priority(10)
            ->availableValidationRules([
                ValidationRule::REQUIRED,
                ValidationRule::MIN,
                ValidationRule::MAX,
            ]);
    }
}
```

**FieldSchema Factory Methods:**
- `FieldSchema::text()` - Text-based fields
- `FieldSchema::string()` - Short string fields
- `FieldSchema::numeric()` - Number fields
- `FieldSchema::boolean()` - Boolean fields
- `FieldSchema::date()` / `dateTime()` - Date fields
- `FieldSchema::singleChoice()` - Select, radio
- `FieldSchema::multiChoice()` - Multi-select, checkboxes

**Accessing Field Type Data:**
```php
$fieldType->getData()->key           // string
$fieldType->getData()->formComponent // string|Closure|null
$fieldType->getData()->searchable    // bool
```

### Available Field Types (21)

Text inputs: `text`, `textarea`, `email`, `phone`, `link`
Rich content: `rich_editor`, `markdown_editor`
Numbers: `number`, `currency`
Dates: `date`, `datetime`
Booleans: `checkbox`, `toggle`
Choice: `select`, `multi_select`, `radio`, `checkbox_list`, `toggle_buttons`, `tags_input`
Other: `color_picker`, `file_upload`

### Enums

- **FieldDataType** - TEXT, STRING, NUMERIC, FLOAT, DATE, DATETIME, BOOLEAN, SINGLE_CHOICE, MULTI_CHOICE
- **CustomFieldWidth** - 25%, 50%, 75%, 100%
- **CustomFieldsFeature** - Feature flags for conditional features
- **ValidationRule** - REQUIRED, MIN, MAX, EMAIL, URL, UNIQUE, etc.
- **VisibilityMode** - Show/hide modes
- **VisibilityOperator** - Comparison operators for conditions
- **VisibilityLogic** - AND/OR logic

### DTOs (Data/)

- `CustomFieldData` - Complete field configuration
- `CustomFieldSettingsData` - Field settings
- `FieldTypeData` - Field type metadata
- `ValidationRuleData` - Validation configuration
- `VisibilityData` / `VisibilityConditionData` - Visibility rules
- `CustomFieldSectionData` / `CustomFieldSectionSettingsData` - Sections
- `CustomFieldOptionSettingsData` - Option settings
- `EntityConfigurationData` - Entity config

## Testing

Tests use Pest PHP with parallel execution and custom expectations.

**Test Structure:**
```
tests/
├── Datasets/              # Test data providers
├── Feature/
│   ├── Admin/Pages/       # Management page tests
│   ├── Integration/       # Resource integration tests
│   ├── Models/            # Model tests
│   └── Imports/           # Import feature tests
├── Fixtures/              # Test models, resources, providers
└── database/              # Test factories and migrations
```

**Custom Expectations:**
- `toHaveCustomFieldValue()` - Assert field values
- `toHaveValidationError()` - Check validation errors

**Environment:**
- SQLite in-memory database
- RefreshDatabase trait on all tests
- Parallel execution by default

## Type Coverage Requirements

**MANDATORY: 100% Type Coverage**

- All methods must have complete type declarations
- All closure parameters must be typed: `fn (Builder $query) => ...`
- Use `@pest-ignore-type` ONLY for framework limitations (Livewire public properties)

**Livewire Exception:**
```php
// Livewire public properties cannot use PHP type declarations
/** @var int|string */
public $fieldId; // @pest-ignore-type
```

## Quick Start

### 1. Add Plugin to Panel

```php
use Relaticle\CustomFields\CustomFieldsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        CustomFieldsPlugin::make(),
    ]);
}
```

### 2. Configure Model

```php
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;

class Post extends Model implements HasCustomFields
{
    use UsesCustomFields;
}
```

### 3. Add to Resource Form

```php
use Relaticle\CustomFields\Facades\CustomFields;

public function form(Schema $schema): Form
{
    return $schema->components([
        // Your fields...
        CustomFields::form()->forSchema($schema)->build()
    ]);
}
```

## Creating New Field Types

```bash
php artisan make:field-type MyFieldType
```

Or manually:

1. Create class in `src/FieldTypeSystem/Definitions/` extending `BaseFieldType`
2. Implement `configure()` method returning `FieldSchema`
3. Register in `FieldManager::DEFAULT_FIELD_TYPES`
4. Create tests in `tests/Feature/`

## Resources

- **Documentation**: https://custom-fields.relaticle.com/
- **Installation**: https://custom-fields.relaticle.com/installation
- **Configuration**: https://custom-fields.relaticle.com/essentials/configuration
