# Date Validation Redesign

## Context

The custom-fields package needs a complete redesign of date validation for `DateFieldType` and `DateTimeFieldType`. The current implementation (on unmerged 3.x branch) only supports relative-to-now constraints with a poor UX (3 raw fields: value/unit/direction). This design introduces multiple anchor types and a preset-driven UI.

## Requirements

- Four anchor types: Today, Fixed Date, Another Custom Field, Record Creation Date
- Cross-field references limited to custom fields on the same entity_type
- Full data model and UI delivered in one pass
- No backward compatibility needed (3.x branch not merged)

## Data Model

### New Enums

**`DateAnchor`**
- `Today` -- resolves to `now()->startOfDay()` (date) or `now()` (datetime)
- `FixedDate` -- a specific absolute date
- `CustomField` -- another custom field's current value
- `RecordCreated` -- host record's `created_at`

**`DateOffsetDirection`** (replaces `DateDirection`)
- `Before` -- subtract offset from anchor
- `After` -- add offset to anchor

**`DateUnit`** -- unchanged (Days, Weeks, Months, Quarters, Years)

### DTO: `DateConstraintValue`

```php
#[MapName(SnakeCaseMapper::class)]
final class DateConstraintValue extends Data
{
    public function __construct(
        public DateAnchor $anchor,
        public int $offset = 0,
        public DateUnit $offsetUnit = DateUnit::Days,
        public DateOffsetDirection $offsetDirection = DateOffsetDirection::After,
        public ?string $fixedDate = null,
        public ?string $fieldReference = null,
    ) {}

    public function resolve(?Closure $getCallback = null, ?Model $record = null): Carbon
    {
        $base = match ($this->anchor) {
            DateAnchor::Today => now()->startOfDay(),
            DateAnchor::FixedDate => Carbon::parse($this->fixedDate),
            DateAnchor::CustomField => Carbon::parse($getCallback("custom_fields.{$this->fieldReference}")),
            DateAnchor::RecordCreated => $record?->created_at ?? now(),
        };

        return $this->offsetDirection === DateOffsetDirection::Before
            ? $base->sub($this->offsetUnit->value, $this->offset)
            : $base->add($this->offsetUnit->value, $this->offset);
    }
}
```

### Stored JSON Examples

Future dates only (min_date):
```json
{"anchor": "today", "offset": 0, "offset_unit": "days", "offset_direction": "after"}
```

After Start Date field + 7 days (min_date):
```json
{"anchor": "custom_field", "offset": 7, "offset_unit": "days", "offset_direction": "after", "field_reference": "start_date"}
```

Before fixed date (max_date):
```json
{"anchor": "fixed_date", "offset": 0, "offset_unit": "days", "offset_direction": "before", "fixed_date": "2026-01-01"}
```

Within 30 days of record creation (max_date):
```json
{"anchor": "record_created", "offset": 30, "offset_unit": "days", "offset_direction": "after"}
```

## UI Design

### Config UI (DateConstraintField)

Single dropdown with presets + progressive disclosure + preview.

**Minimum Date dropdown options:**
- No restriction
- Today or later (preset: anchor=today, offset=0)
- Offset from today... (reveals: [offset] [unit] [direction] today)
- After another field... (reveals: [field select] + [offset] [unit])
- After record creation date... (reveals: + [offset] [unit])
- Fixed date... (reveals: [date picker])

**Maximum Date dropdown options:**
- No restriction
- Today or earlier (preset: anchor=today, offset=0)
- Offset from today... (reveals: [offset] [unit] [direction] today)
- Before another field... (reveals: [field select] + [offset] [unit])
- Before record creation date... (reveals: + [offset] [unit])
- Fixed date... (reveals: [date picker])

**"Another field" dropdown:** Dynamically populated with other date/date-time custom fields on the same entity_type, excluding the current field. Label = field name, value = field code.

**Preview line** (always visible when restriction is set):
- Today: "On or after Mar 25, 2026"
- Field ref: "On or after [Start Date] + 7 days"
- Record created: "Within 30 days of record creation"
- Fixed: "On or after Jan 1, 2026"

### Circular Reference Detection

At save time, if a field has a CustomField anchor, traverse the reference chain to detect cycles. Simple depth-limited traversal (max depth = total date fields on entity). Show validation error if circular.

## Runtime / Capability Changes

### applyToComponent

Capabilities pass `$get` and `$record` to `resolve()`:
```php
$component->minDate(fn (Get $get, ?Model $record = null) =>
    $constraintValue->resolve(
        getCallback: fn (string $path) => $get($path),
        record: $record,
    )
);
```

For CustomField anchors, the referenced field must be marked `->live()` so date picker updates reactively.

### Validation Rules

New `DateConstraintRule` (implements `ValidationRule`) replaces static string rules for dynamic anchors:

```php
class DateConstraintRule implements ValidationRule
{
    public function __construct(
        private DateConstraintValue $constraint,
        private string $comparison, // 'after_or_equal' | 'before_or_equal'
        private ?array $formData = null,
        private ?Model $record = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $boundary = $this->constraint->resolve(
            getCallback: fn (string $path) => data_get($this->formData, $path),
            record: $this->record,
        );
        // Compare $value against $boundary, call $fail() if invalid
    }
}
```

This fixes the current bug where `resolve()` runs at mount time instead of submit time.

### Error Messages

Context-aware messages:
- Today: "The {field} must be on or after {resolved date}."
- Field ref: "The {field} must be on or after {referenced field name}."
- Record created: "The {field} must be within {offset} {unit} of the record creation date."
- Fixed: "The {field} must be on or after {fixed date}."

## Files Changed

| File | Change |
|---|---|
| `src/Data/DateConstraintValue.php` | Rewrite -- new DTO with anchor system |
| `src/Enums/DateDirection.php` | Delete -- replaced by DateOffsetDirection |
| `src/Enums/DateOffsetDirection.php` | New |
| `src/Enums/DateAnchor.php` | New |
| `src/Enums/DateUnit.php` | Unchanged |
| `src/Validation/Capabilities/MinDateCapability.php` | Modify -- new resolve signature, DateConstraintRule |
| `src/Validation/Capabilities/MaxDateCapability.php` | Modify -- same |
| `src/Validation/Rules/DateConstraintRule.php` | New |
| `src/Filament/Management/Forms/Components/DateConstraintField.php` | Rewrite -- preset dropdown UI |
| `src/Filament/Integration/Base/AbstractFormComponent.php` | Modify -- pass $get/$record, handle reactive refs |

### Not Changing

- CustomField model (no schema changes)
- CustomFieldValue model (storage unchanged)
- DateFieldType / DateTimeFieldType (same capabilities)
- ValidationService (interface unchanged)
- custom_field_values table (no migration)
