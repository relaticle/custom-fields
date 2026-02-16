# Validation Capabilities System

Replace the generic `ValidationRule` enum + repeater with field-type-owned validation capabilities declared on `FieldSchema`.

## Problem

The current validation system exposes raw Laravel validation rules (after, before, min, max, etc.) via a generic repeater UI. Users type string parameters into text inputs. This creates:

- Poor UX: users must know Laravel validation syntax
- No UI constraints: date pickers show all dates, validation only fails on submit
- No relative date support beyond `today`/`tomorrow`/`yesterday`
- A 100+ case enum (`ValidationRule`) where each field type uses ~3-5 rules

## Solution

Each field type declares **validation capabilities** on `FieldSchema`. Each capability is a self-contained class that owns its admin UI, Filament component application, and Laravel rule generation.

### FieldSchema API

```php
// DateFieldType::configure()
FieldSchema::date()
    ->key('date')
    ->label('Date')
    ->canHaveMinDate()
    ->canHaveMaxDate();

// NumberFieldType::configure()
FieldSchema::number()
    ->key('number')
    ->label('Number')
    ->canHaveMinValue()
    ->canHaveMaxValue()
    ->canBeIntegerOnly();

// TextFieldType::configure()
FieldSchema::text()
    ->key('text')
    ->label('Text')
    ->canHaveMinLength()
    ->canHaveMaxLength();
```

Capability methods are added to the existing `FieldSchema` fluent builder alongside `->searchable()`, `->filterable()`, etc.

### Capability Contract

```php
interface ValidationCapability
{
    public function key(): string;
    public function label(): string;
    public function formSchema(): array;
    public function applyToComponent(Field $component, mixed $value): void;
    public function toRules(mixed $value): array;
}
```

Each capability:
1. **`formSchema()`** — Returns Filament components for the admin Validation tab
2. **`applyToComponent()`** — Calls Filament's dual-effect methods (e.g., `->minDate()` sets UI constraint AND adds validation rule)
3. **`toRules()`** — Produces Laravel rules for non-Filament contexts (API, CSV import)

### Auto-wiring

The system automatically:
- Renders the Validation tab by iterating the field type's declared capabilities
- Applies capabilities to end-user form components via `AbstractFormComponent::configure()`
- Collects Laravel rules from capabilities for non-Filament validation

Field type authors just chain capability methods — no Data classes, no form component changes, no rule conversion logic.

## Capability Inventory

### 12 built-in capabilities

| Capability | Key | Field Types | Filament Method | Laravel Rule |
|---|---|---|---|---|
| `MinDate` | `min_date` | Date, DateTime | `->minDate()` | `after_or_equal:{date}` |
| `MaxDate` | `max_date` | Date, DateTime | `->maxDate()` | `before_or_equal:{date}` |
| `MinValue` | `min_value` | Number, Currency | `->minValue()` | `min:{value}` |
| `MaxValue` | `max_value` | Number, Currency | `->maxValue()` | `max:{value}` |
| `IntegerOnly` | `integer_only` | Number | `->integer()` | `integer` |
| `DecimalPlaces` | `decimal_places` | Currency | `->decimal()` | `decimal:0,{places}` |
| `MinLength` | `min_length` | Text, Textarea, Markdown, RichEditor, Link, Email, Phone | `->minLength()` | `min:{length}` |
| `MaxLength` | `max_length` | Text, Textarea, Markdown, RichEditor, Link, Email, Phone | `->maxLength()` | `max:{length}` |
| `MinSelections` | `min_selections` | MultiSelect, CheckboxList, TagsInput, Record | `->minItems()` | `min:{count}` |
| `MaxSelections` | `max_selections` | MultiSelect, CheckboxList, TagsInput, Record | `->maxItems()` | `max:{count}` |
| `AcceptedFileTypes` | `accepted_types` | FileUpload | `->acceptedFileTypes()` | `mimes:{types}` |
| `MaxFileSize` | `max_size_kb` | FileUpload | `->maxSize()` | `max:{kb}` |

### Field types with no capabilities

Toggle, Checkbox, Select, Radio, ToggleButtons, ColorPicker — Validation tab shows only the base-level "Required" toggle.

## Base-level Settings

`required` and `unique_per_entity_type` are handled by `AbstractFormComponent::configure()`, not by capabilities. Available for all field types.

## Storage

### Column

`validation_rules` — existing column, no schema change.

### Cast

`AsCollection::class` — null-safe, supports `->get()`, `->has()`, `->keys()`.

### Format

Old format (removed):
```json
[{"name": "required", "parameters": []}, {"name": "min", "parameters": [{"value": "5"}]}]
```

New format:
```json
{"required": true, "min_length": 5}
```

Date with relative value:
```json
{
    "required": true,
    "min_date": {"mode": "relative", "value": 7, "unit": "days"},
    "max_date": {"mode": "absolute", "value": "2026-12-31"}
}
```

Each capability reads/writes its own key from the collection.

## Date Constraint Value Object

```php
class DateConstraintValue extends Data
{
    public DateConstraintMode $mode;        // Absolute | Relative
    public ?string $absoluteValue = null;   // Y-m-d
    public ?int $relativeValue = null;      // e.g., 7
    public ?DateUnit $relativeUnit = null;  // Days | Weeks | Months | Years

    public function resolve(): Carbon { ... }
}
```

### Enums

- `DateConstraintMode` — `Absolute`, `Relative`
- `DateUnit` — `Days`, `Weeks`, `Months`, `Years`

### Admin UI Component

`DateConstraintField` — reusable Filament form component. Toggle between absolute (date picker) and relative (number input + unit select). Direction is implicit: min_date is future-relative, max_date can be either.

## Extensibility

Third-party developers can create custom capabilities:

```php
class MyCustomCapability implements ValidationCapability
{
    public function key(): string { return 'my_rule'; }
    public function label(): string { return 'My Rule'; }
    public function formSchema(): array { return [...]; }
    public function applyToComponent(Field $component, mixed $value): void { ... }
    public function toRules(mixed $value): array { return [...]; }
}
```

Register on a custom field type:
```php
FieldSchema::text()
    ->key('my-field')
    ->withValidationCapability(MyCustomCapability::class);
```

## What Gets Removed

- `ValidationRule` enum (entire file, 100+ cases)
- `ValidationRuleData` DTO
- `CustomFieldValidationComponent` (generic repeater)
- `ValidationService::convertUserRulesToValidatorFormat()`
- All parameter validation/normalization/help-text methods
- Translation keys for validation rule labels/descriptions

## What Gets Modified

- `FieldSchema` — add capability methods, remove `availableValidationRules()`/`defaultValidationRules()`
- `FieldTypeData` — carry registered capabilities instead of validation rule list
- `CustomField` model — change `validation_rules` cast from `DataCollection` to `AsCollection`
- `ValidationService` — simplify to handle base-level rules + iterate capabilities for rule generation
- `AbstractFormComponent::configure()` — iterate capabilities, call `applyToComponent()`
- All 22 field type definitions — replace `availableValidationRules([...])` with capability methods
- `FieldForm` (management form) — Validation tab renders capability form schemas

## Upgrade Path

### Data Migration

New `MigrateValidationRulesFormatStep` in existing `UpgradeCommand`:

1. Read each field's `type` + old `[{name, parameters}]` format
2. Convert to new `{key: value}` format based on field type context:
   - `{"name": "required"}` -> `{"required": true}`
   - `{"name": "min", "parameters": [{"value": "5"}]}` on text -> `{"min_length": 5}`
   - `{"name": "min", "parameters": [{"value": "5"}]}` on number -> `{"min_value": 5.0}`
   - `{"name": "after", "parameters": [{"value": "today"}]}` -> `{"min_date": {"mode": "relative", "value": 0, "unit": "days"}}`
   - `{"name": "after", "parameters": [{"value": "2026-01-01"}]}` -> `{"min_date": {"mode": "absolute", "value": "2026-01-01"}}`
3. Unmappable rules (dropped format rules: alpha, starts_with, etc.) logged as warnings, discarded
4. Dry-run and skip support per existing pattern

### Schema Migration

None required. Same column, new format.

### Forward Compatibility

- New capability = new key in JSON. Fields without it have no key — capability returns null.
- Removed capability = orphaned key in JSON, never read, harmless.

## Decisions Log

| Decision | Choice | Rationale |
|---|---|---|
| Validation ownership | Field types via capabilities | Better UX, type-safe, Filament-native |
| Relative date input | Structured number + unit | User-friendly, error-proof |
| Relative date reference | Always today | Simplicity, covers 95% of cases |
| Time units | Days, weeks, months, years | Practical coverage |
| Settings classes | None — capabilities handle typing | Less boilerplate, capabilities are self-contained |
| Storage column | Keep `validation_rules` | Zero schema migration, clear separation |
| Column cast | `AsCollection` | Null-safe, method chaining |
| Text format rules | Dropped | Rarely used, aggressive simplification |
| Scope | All 22 field types at once | Clean v3 cut, no hybrid state |
| Base-level settings | `required`, `unique_per_entity_type` | Universal, handled by base class |
