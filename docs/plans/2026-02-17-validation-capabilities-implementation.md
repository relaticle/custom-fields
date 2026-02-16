# Validation Capabilities System — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the generic ValidationRule enum + repeater with field-type-owned validation capabilities on FieldSchema, including relative date support.

**Architecture:** Validation capabilities are self-contained classes (implementing `ValidationCapability`) that each own their admin UI, Filament component application, and Laravel rule generation. FieldSchema gains fluent methods (`->canHaveMinDate()`, etc.) to declare which capabilities a field type supports. The system auto-wires admin forms and end-user components.

**Tech Stack:** PHP 8.4, Laravel 12, Filament 5, Spatie Laravel Data, Pest 4

**Design doc:** `docs/plans/2026-02-17-validation-capabilities-design.md`

**Package root:** `/Users/manuk/.superset/worktrees/filament-demo/flute-packages/custom-fields`

**Run tests:** `cd /Users/manuk/.superset/worktrees/filament-demo/flute-packages/custom-fields && composer test`

**Run specific test:** `cd /Users/manuk/.superset/worktrees/filament-demo/flute-packages/custom-fields && vendor/bin/pest tests/path/File.php --filter="test name"`

**Run PHPStan:** `cd /Users/manuk/.superset/worktrees/filament-demo/flute-packages/custom-fields && composer test:phpstan`

---

## Phase 1: Foundation (Enums, Value Objects, Interface)

### Task 1: Create DateConstraintMode and DateUnit enums

**Files:**
- Create: `src/Enums/DateConstraintMode.php`
- Create: `src/Enums/DateUnit.php`
- Test: `tests/Unit/Enums/DateConstraintModeTest.php`
- Test: `tests/Unit/Enums/DateUnitTest.php`

**Step 1: Write tests**

```php
// tests/Unit/Enums/DateConstraintModeTest.php
it('has Absolute and Relative cases', function (): void {
    expect(DateConstraintMode::cases())->toHaveCount(2)
        ->and(DateConstraintMode::Absolute->value)->toBe('absolute')
        ->and(DateConstraintMode::Relative->value)->toBe('relative');
});

// tests/Unit/Enums/DateUnitTest.php
it('has Days, Weeks, Months, Years cases', function (): void {
    expect(DateUnit::cases())->toHaveCount(4);
});

it('provides labels for form selects', function (): void {
    expect(DateUnit::Days->getLabel())->toBe('Days');
});
```

**Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Enums/ -v`
Expected: FAIL — classes don't exist

**Step 3: Implement enums**

```php
// src/Enums/DateConstraintMode.php
enum DateConstraintMode: string
{
    case Absolute = 'absolute';
    case Relative = 'relative';
}

// src/Enums/DateUnit.php
enum DateUnit: string implements HasLabel
{
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Years = 'years';

    public function getLabel(): string
    {
        return match ($this) {
            self::Days => 'Days',
            self::Weeks => 'Weeks',
            self::Months => 'Months',
            self::Years => 'Years',
        };
    }
}
```

**Step 4: Run tests to verify they pass**

**Step 5: Commit**

```bash
git add src/Enums/DateConstraintMode.php src/Enums/DateUnit.php tests/Unit/Enums/
git commit -m "feat: add DateConstraintMode and DateUnit enums"
```

---

### Task 2: Create DateConstraintValue value object

**Files:**
- Create: `src/Data/DateConstraintValue.php`
- Test: `tests/Unit/Data/DateConstraintValueTest.php`

**Step 1: Write tests**

```php
// tests/Unit/Data/DateConstraintValueTest.php
use Carbon\Carbon;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Enums\DateConstraintMode;
use Relaticle\CustomFields\Enums\DateUnit;

it('resolves absolute dates', function (): void {
    $constraint = DateConstraintValue::from([
        'mode' => 'absolute',
        'absolute_value' => '2026-06-15',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-06-15');
});

it('resolves relative dates in days', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 7,
        'relative_unit' => 'days',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-24');
});

it('resolves relative dates in weeks', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 2,
        'relative_unit' => 'weeks',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-03-03');
});

it('resolves relative dates in months', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 3,
        'relative_unit' => 'months',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-05-17');
});

it('resolves relative dates in years', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 1,
        'relative_unit' => 'years',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2027-02-17');
});

it('handles negative relative values for past dates', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => -30,
        'relative_unit' => 'days',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-01-18');
});

it('resolves zero relative value as today', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 0,
        'relative_unit' => 'days',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-17');
});

it('serializes to and from array', function (): void {
    $data = [
        'mode' => 'relative',
        'relative_value' => 7,
        'relative_unit' => 'days',
    ];

    $constraint = DateConstraintValue::from($data);
    $serialized = $constraint->toArray();

    expect($serialized['mode'])->toBe('relative')
        ->and($serialized['relative_value'])->toBe(7)
        ->and($serialized['relative_unit'])->toBe('days');
});
```

**Step 2: Run tests to verify they fail**

**Step 3: Implement DateConstraintValue**

```php
// src/Data/DateConstraintValue.php
namespace Relaticle\CustomFields\Data;

use Carbon\Carbon;
use Relaticle\CustomFields\Enums\DateConstraintMode;
use Relaticle\CustomFields\Enums\DateUnit;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class DateConstraintValue extends Data
{
    public function __construct(
        public DateConstraintMode $mode,
        public ?string $absoluteValue = null,
        public ?int $relativeValue = null,
        public ?DateUnit $relativeUnit = null,
    ) {}

    public function resolve(): Carbon
    {
        return match ($this->mode) {
            DateConstraintMode::Absolute => Carbon::parse($this->absoluteValue),
            DateConstraintMode::Relative => now()->add(
                $this->relativeUnit->value,
                $this->relativeValue ?? 0
            ),
        };
    }
}
```

**Step 4: Run tests to verify they pass**

**Step 5: Commit**

```bash
git add src/Data/DateConstraintValue.php tests/Unit/Data/
git commit -m "feat: add DateConstraintValue value object with relative date resolution"
```

---

### Task 3: Create ValidationCapability interface

**Files:**
- Create: `src/Contracts/ValidationCapability.php`
- Test: Architecture test coverage only (interface has no logic)

**Step 1: Implement the interface**

```php
// src/Contracts/ValidationCapability.php
namespace Relaticle\CustomFields\Contracts;

use Filament\Forms\Components\Field;

interface ValidationCapability
{
    /** Storage key in the validation_rules JSON column */
    public function key(): string;

    /** Human-readable label for the admin UI */
    public function label(): string;

    /** Filament form components for the admin Validation tab */
    public function formSchema(string $statePath): array;

    /** Apply this capability's value to a Filament form component (dual-effect) */
    public function applyToComponent(Field $component, mixed $value): void;

    /** Convert this capability's value to Laravel validation rules */
    public function toRules(mixed $value): array;
}
```

**Step 2: Commit**

```bash
git add src/Contracts/ValidationCapability.php
git commit -m "feat: add ValidationCapability interface"
```

---

## Phase 2: FieldSchema & FieldTypeData Integration

### Task 4: Add capability registration to FieldSchema

**Files:**
- Modify: `src/FieldTypeSystem/FieldSchema.php`
- Modify: `src/Data/FieldTypeData.php`
- Test: `tests/Unit/FieldTypeSystem/FieldSchemaCapabilitiesTest.php`

**Step 1: Write tests**

```php
// tests/Unit/FieldTypeSystem/FieldSchemaCapabilitiesTest.php
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;

it('registers capabilities via fluent methods', function (): void {
    $schema = FieldSchema::date()
        ->key('date')
        ->label('Date')
        ->canHaveMinDate()
        ->canHaveMaxDate();

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});

it('registers custom capabilities', function (): void {
    $schema = FieldSchema::text()
        ->key('custom')
        ->label('Custom')
        ->withValidationCapability(SomeTestCapability::class);

    expect($schema->getValidationCapabilities())->toHaveCount(1);
});

it('carries capabilities through to FieldTypeData', function (): void {
    $schema = FieldSchema::text()
        ->key('text')
        ->label('Text')
        ->canHaveMinLength()
        ->canHaveMaxLength();

    $data = $schema->data();

    expect($data->validationCapabilities)->toHaveCount(2);
});
```

**Step 2: Run tests to verify they fail**

**Step 3: Add capability properties and methods to FieldSchema**

Add to `FieldSchema`:
- `private array $validationCapabilities = []` property
- `canHaveMinDate()`, `canHaveMaxDate()`, `canHaveMinValue()`, `canHaveMaxValue()`, `canBeIntegerOnly()`, `canHaveDecimalPlaces()`, `canHaveMinLength()`, `canHaveMaxLength()`, `canHaveMinSelections()`, `canHaveMaxSelections()`, `canHaveAcceptedFileTypes()`, `canHaveMaxFileSize()` fluent methods — each pushes a capability class-string to the array
- `withValidationCapability(string $capabilityClass)` for custom capabilities
- `getValidationCapabilities(): array` getter

Add to `FieldTypeData`:
- `public array $validationCapabilities = []` property
- Update `FieldSchema::data()` to pass capabilities

**Step 4: Run tests to verify they pass**

**Step 5: Commit**

```bash
git add src/FieldTypeSystem/FieldSchema.php src/Data/FieldTypeData.php tests/Unit/FieldTypeSystem/
git commit -m "feat: add validation capability registration to FieldSchema"
```

---

## Phase 3: Built-in Capability Classes

### Task 5: Implement simple numeric capabilities (MinValue, MaxValue, IntegerOnly, DecimalPlaces)

**Files:**
- Create: `src/Validation/Capabilities/MinValueCapability.php`
- Create: `src/Validation/Capabilities/MaxValueCapability.php`
- Create: `src/Validation/Capabilities/IntegerOnlyCapability.php`
- Create: `src/Validation/Capabilities/DecimalPlacesCapability.php`
- Test: `tests/Unit/Validation/Capabilities/NumericCapabilitiesTest.php`

**Step 1: Write tests**

Test each capability's `key()`, `formSchema()`, `toRules()`, and `applyToComponent()`:

```php
it('MinValueCapability produces correct rules', function (): void {
    $capability = new MinValueCapability;
    expect($capability->toRules(5.0))->toBe(['min:5'])
        ->and($capability->toRules(null))->toBe([])
        ->and($capability->key())->toBe('min_value');
});

it('MaxValueCapability produces correct rules', function (): void {
    $capability = new MaxValueCapability;
    expect($capability->toRules(100.0))->toBe(['max:100'])
        ->and($capability->toRules(null))->toBe([]);
});

it('IntegerOnlyCapability produces correct rules', function (): void {
    $capability = new IntegerOnlyCapability;
    expect($capability->toRules(true))->toBe(['integer'])
        ->and($capability->toRules(false))->toBe([]);
});

it('DecimalPlacesCapability produces correct rules', function (): void {
    $capability = new DecimalPlacesCapability;
    expect($capability->toRules(2))->toBe(['decimal:0,2'])
        ->and($capability->toRules(null))->toBe([]);
});
```

**Step 2: Run tests to verify they fail**

**Step 3: Implement the four capability classes**

Each follows the same pattern — implement `ValidationCapability`. `formSchema()` returns a `TextInput::make()->numeric()`. `applyToComponent()` calls `->minValue()` / `->maxValue()` / `->integer()`. `toRules()` returns the equivalent Laravel rule string.

**Step 4: Run tests to verify they pass**

**Step 5: Commit**

```bash
git add src/Validation/Capabilities/ tests/Unit/Validation/
git commit -m "feat: add numeric validation capabilities (min/max value, integer, decimal)"
```

---

### Task 6: Implement text length capabilities (MinLength, MaxLength)

**Files:**
- Create: `src/Validation/Capabilities/MinLengthCapability.php`
- Create: `src/Validation/Capabilities/MaxLengthCapability.php`
- Test: `tests/Unit/Validation/Capabilities/TextCapabilitiesTest.php`

Same pattern as Task 5. `applyToComponent()` calls `->minLength()` / `->maxLength()`. `toRules()` returns `min:{n}` / `max:{n}`.

**Commit:** `feat: add text length validation capabilities`

---

### Task 7: Implement selection capabilities (MinSelections, MaxSelections)

**Files:**
- Create: `src/Validation/Capabilities/MinSelectionsCapability.php`
- Create: `src/Validation/Capabilities/MaxSelectionsCapability.php`
- Test: `tests/Unit/Validation/Capabilities/SelectionCapabilitiesTest.php`

Same pattern. `applyToComponent()` calls `->minItems()` / `->maxItems()`. `toRules()` returns `min:{n}` / `max:{n}`.

**Commit:** `feat: add selection count validation capabilities`

---

### Task 8: Implement file capabilities (AcceptedFileTypes, MaxFileSize)

**Files:**
- Create: `src/Validation/Capabilities/AcceptedFileTypesCapability.php`
- Create: `src/Validation/Capabilities/MaxFileSizeCapability.php`
- Test: `tests/Unit/Validation/Capabilities/FileCapabilitiesTest.php`

`AcceptedFileTypes`: `formSchema()` returns a TagsInput. `applyToComponent()` calls `->acceptedFileTypes()`. `toRules()` returns `mimes:{types}`.

`MaxFileSize`: `formSchema()` returns a TextInput (KB). `applyToComponent()` calls `->maxSize()`. `toRules()` returns `max:{kb}`.

**Commit:** `feat: add file validation capabilities`

---

### Task 9: Implement date capabilities (MinDate, MaxDate) — the complex ones

**Files:**
- Create: `src/Validation/Capabilities/MinDateCapability.php`
- Create: `src/Validation/Capabilities/MaxDateCapability.php`
- Create: `src/Filament/Management/Forms/Components/DateConstraintField.php`
- Test: `tests/Unit/Validation/Capabilities/DateCapabilitiesTest.php`
- Test: `tests/Feature/Filament/Components/DateConstraintFieldTest.php`

**Step 1: Write tests for capabilities**

```php
use Carbon\Carbon;
use Relaticle\CustomFields\Validation\Capabilities\MinDateCapability;

it('MinDateCapability produces after_or_equal rule for absolute date', function (): void {
    $capability = new MinDateCapability;
    $value = ['mode' => 'absolute', 'absolute_value' => '2026-06-15'];

    expect($capability->toRules($value))->toBe(['after_or_equal:2026-06-15']);
});

it('MinDateCapability produces after_or_equal rule for relative date', function (): void {
    Carbon::setTestNow('2026-02-17');

    $capability = new MinDateCapability;
    $value = ['mode' => 'relative', 'relative_value' => 7, 'relative_unit' => 'days'];

    expect($capability->toRules($value))->toBe(['after_or_equal:2026-02-24']);
});

it('MinDateCapability returns empty rules for null', function (): void {
    $capability = new MinDateCapability;
    expect($capability->toRules(null))->toBe([]);
});

it('MaxDateCapability produces before_or_equal rule', function (): void {
    $capability = new MaxDateCapability;
    $value = ['mode' => 'absolute', 'absolute_value' => '2026-12-31'];

    expect($capability->toRules($value))->toBe(['before_or_equal:2026-12-31']);
});
```

**Step 2: Run tests to verify they fail**

**Step 3: Implement MinDateCapability and MaxDateCapability**

These hydrate `DateConstraintValue::from($value)`, call `->resolve()`, and produce `after_or_equal:{date}` / `before_or_equal:{date}`. `applyToComponent()` calls `->minDate(fn () => ...)` / `->maxDate(fn () => ...)`.

**Step 4: Implement DateConstraintField**

A Filament form component (extends `Filament\Forms\Components\Field` or wraps a Group/Fieldset) that renders:
- A `ToggleButtons` or `Select` for mode (Absolute / Relative)
- When Absolute: a `DatePicker` for the date value
- When Relative: a `TextInput` (numeric, positive int) + `Select` for unit (Days/Weeks/Months/Years)
- Uses `->live()` on the mode toggle so conditional fields react

The component stores its value as the `DateConstraintValue` array structure.

**Step 5: Run all tests**

**Step 6: Commit**

```bash
git add src/Validation/Capabilities/MinDateCapability.php src/Validation/Capabilities/MaxDateCapability.php src/Filament/Management/Forms/Components/DateConstraintField.php tests/
git commit -m "feat: add date validation capabilities with relative date support"
```

---

## Phase 4: Wiring (Model, AbstractFormComponent, ValidationService)

### Task 10: Change model cast and update ValidationService

**Files:**
- Modify: `src/Models/CustomField.php` — change `validation_rules` cast to `AsCollection`
- Modify: `src/Services/ValidationService.php` — simplify to iterate capabilities
- Test: `tests/Unit/Services/ValidationServiceCapabilitiesTest.php`

**Step 1: Write tests**

```php
it('collects rules from capabilities for a date field', function (): void {
    $field = CustomField::factory()->ofType('date')->create([
        'validation_rules' => [
            'required' => true,
            'min_date' => ['mode' => 'absolute', 'absolute_value' => '2026-01-01'],
        ],
    ]);

    $service = app(ValidationService::class);
    $rules = $service->getValidationRules($field);

    expect($rules)->toContain('required')
        ->toContain('after_or_equal:2026-01-01')
        ->toContain('date');  // from DatabaseFieldConstraints
});

it('returns required rule when required is true', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => ['required' => true],
    ]);

    $service = app(ValidationService::class);
    expect($service->isRequired($field))->toBeTrue();
});

it('returns false for required when not set', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [],
    ]);

    $service = app(ValidationService::class);
    expect($service->isRequired($field))->toBeFalse();
});
```

**Step 2: Run tests to verify they fail**

**Step 3: Implement changes**

- `CustomField` model: change `'validation_rules'` cast from `DataCollection::class.':'.ValidationRuleData::class` to `AsCollection::class`
- `ValidationService::isRequired()`: check `$customField->validation_rules->get('required', false)`
- `ValidationService::getValidationRules()`: iterate field type's capabilities, call `toRules()` for each with the stored value from `validation_rules->get($capability->key())`
- Keep `getDatabaseValidationRules()` and `getTypeSpecificRules()` (unique_per_entity_type)

**Step 4: Run tests to verify they pass**

**Step 5: Commit**

```bash
git add src/Models/CustomField.php src/Services/ValidationService.php tests/Unit/Services/
git commit -m "refactor: update model cast and ValidationService to use capabilities"
```

---

### Task 11: Wire capabilities into AbstractFormComponent

**Files:**
- Modify: `src/Filament/Integration/Base/AbstractFormComponent.php`
- Test: `tests/Feature/Integration/Resources/Pages/CreateRecordTest.php` (existing tests should still pass)

**Step 1: Modify `configure()` method**

In `AbstractFormComponent::configure()`, after the existing `->required()` and `->rules()` setup, add capability application:

```php
// Iterate field type's capabilities and apply stored values
$fieldTypeData = $customField->typeData;
foreach ($fieldTypeData->validationCapabilities as $capabilityClass) {
    $capability = app($capabilityClass);
    $value = $customField->validation_rules->get($capability->key());
    if ($value !== null) {
        $capability->applyToComponent($component, $value);
    }
}
```

**Step 2: Run existing integration tests to verify nothing breaks**

Run: `vendor/bin/pest tests/Feature/Integration/ -v`

**Step 3: Commit**

```bash
git add src/Filament/Integration/Base/AbstractFormComponent.php
git commit -m "feat: wire validation capabilities into form component rendering"
```

---

## Phase 5: Admin UI (Management Form)

### Task 12: Replace Validation tab with capability-rendered form

**Files:**
- Modify: `src/Filament/Management/Schemas/FieldForm.php` — replace validation tab content
- Modify or remove: `src/Filament/Management/Forms/Components/CustomFieldValidationComponent.php`
- Test: `tests/Feature/Admin/Pages/CustomFieldsValidationTest.php` (update existing tests)

**Step 1: Update FieldForm validation tab**

Replace the `CustomFieldValidationComponent` repeater with a dynamic section that:
1. Always shows a `Toggle::make('validation_rules.required')->label('Required')`
2. Reads the current field type's registered capabilities from FieldManager
3. Renders each capability's `formSchema('validation_rules')` conditionally when `type` matches

This uses the same pattern as `getTypeSettingsSchema()` — iterate registered field types, inject their capability schemas visible when `type === fieldTypeKey`.

**Step 2: Update existing validation tests**

The existing tests in `CustomFieldsValidationTest.php` create fields with `->withValidation(['required'])`. The factory needs updating to produce the new format. Update the `CustomField` factory's `withValidation()` state to write `{'required': true}` format.

**Step 3: Run tests**

Run: `vendor/bin/pest tests/Feature/Admin/ -v`

**Step 4: Commit**

```bash
git add src/Filament/Management/ tests/Feature/Admin/
git commit -m "feat: replace validation tab with capability-rendered settings"
```

---

## Phase 6: Update All Field Type Definitions

### Task 13: Update all 22 field type definitions

**Files:**
- Modify: All files in `src/FieldTypeSystem/Definitions/`

**Step 1: Replace `availableValidationRules([...])` with capability methods**

For each field type, replace the `->availableValidationRules([...])` call with the appropriate capability methods per the design doc mapping:

- **Date, DateTime**: `->canHaveMinDate()->canHaveMaxDate()`
- **Number**: `->canHaveMinValue()->canHaveMaxValue()->canBeIntegerOnly()`
- **Currency**: `->canHaveMinValue()->canHaveMaxValue()->canHaveDecimalPlaces()`
- **Text, Textarea, MarkdownEditor, RichEditor, Link**: `->canHaveMinLength()->canHaveMaxLength()`
- **Email, Phone**: `->canHaveMinLength()->canHaveMaxLength()`
- **MultiSelect, CheckboxList, TagsInput**: `->canHaveMinSelections()->canHaveMaxSelections()`
- **Record**: `->canHaveMinSelections()->canHaveMaxSelections()`
- **FileUpload**: `->canHaveAcceptedFileTypes()->canHaveMaxFileSize()`
- **Toggle, Checkbox, Select, Radio, ToggleButtons, ColorPicker**: Remove `availableValidationRules()` entirely (no capabilities)

**Step 2: Run all tests**

Run: `composer test`

**Step 3: Commit**

```bash
git add src/FieldTypeSystem/Definitions/
git commit -m "refactor: update all field type definitions to use validation capabilities"
```

---

## Phase 7: Upgrade Step

### Task 14: Add MigrateValidationRulesFormatStep

**Files:**
- Create: `src/Console/Commands/Upgrade/Steps/MigrateValidationRulesFormatStep.php`
- Modify: `src/Console/Commands/UpgradeCommand.php` — register new step
- Test: `tests/Feature/Commands/MigrateValidationRulesFormatStepTest.php`

**Step 1: Write tests**

```php
it('converts required rule to new format', function (): void {
    $field = CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => [['name' => 'required', 'parameters' => []]],
    ]);

    artisan('custom-fields:upgrade', ['--force' => true, '--skip' => 'lookup-fields,email-format,phone-format,clean-multivalue-rules,validate-schema,clear-caches']);

    $field->refresh();
    expect($field->validation_rules->get('required'))->toBeTrue();
});

it('converts min rule to min_length for text fields', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [['name' => 'min', 'parameters' => [['value' => '5']]]],
    ]);

    // Run migration step...
    $field->refresh();
    expect($field->validation_rules->get('min_length'))->toBe(5);
});

it('converts min rule to min_value for number fields', function (): void {
    $field = CustomField::factory()->ofType('number')->create([
        'validation_rules' => [['name' => 'min', 'parameters' => [['value' => '10']]]],
    ]);

    // Run migration step...
    $field->refresh();
    expect($field->validation_rules->get('min_value'))->toBe(10.0);
});

it('converts after rule with absolute date to min_date', function (): void {
    $field = CustomField::factory()->ofType('date')->create([
        'validation_rules' => [['name' => 'after', 'parameters' => [['value' => '2026-01-01']]]],
    ]);

    // Run migration step...
    $field->refresh();
    $minDate = $field->validation_rules->get('min_date');
    expect($minDate['mode'])->toBe('absolute')
        ->and($minDate['absolute_value'])->toBe('2026-01-01');
});

it('converts after rule with today to relative min_date', function (): void {
    $field = CustomField::factory()->ofType('date')->create([
        'validation_rules' => [['name' => 'after', 'parameters' => [['value' => 'today']]]],
    ]);

    // Run migration step...
    $field->refresh();
    $minDate = $field->validation_rules->get('min_date');
    expect($minDate['mode'])->toBe('relative')
        ->and($minDate['relative_value'])->toBe(0)
        ->and($minDate['relative_unit'])->toBe('days');
});

it('discards unmappable rules with warning', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [
            ['name' => 'required', 'parameters' => []],
            ['name' => 'alpha', 'parameters' => []],
        ],
    ]);

    // Run migration step...
    $field->refresh();
    expect($field->validation_rules->get('required'))->toBeTrue()
        ->and($field->validation_rules->has('alpha'))->toBeFalse();
});

it('handles null validation_rules gracefully', function (): void {
    $field = CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => null,
    ]);

    // Run migration step... should not error
    $field->refresh();
    expect(true)->toBeTrue(); // no exception
});
```

**Step 2: Run tests to verify they fail**

**Step 3: Implement MigrateValidationRulesFormatStep**

The step:
1. Queries all custom fields with non-null `validation_rules`
2. Detects format — if already an object (new format), skip. If an array of `{name, parameters}`, convert.
3. For each rule, maps based on field type:
   - `required` -> `{'required': true}`
   - `min` on text/textarea/markdown/richEditor/link/email/phone -> `{'min_length': int}`
   - `min` on number/currency -> `{'min_value': float}`
   - `min` on multi_select/checkbox-list/tags-input/record -> `{'min_selections': int}`
   - `max` -> same pattern
   - `after` / `after_or_equal` -> `{'min_date': DateConstraintValue}`
   - `before` / `before_or_equal` -> `{'max_date': DateConstraintValue}`
   - `integer` -> `{'integer_only': true}`
   - `decimal` -> `{'decimal_places': int}`
   - `mimes` / `mimetypes` -> `{'accepted_types': array}`
   - `file` + `max` on FileUpload -> `{'max_size_kb': int}`
   - Everything else -> logged as warning, discarded

**Step 4: Register in UpgradeCommand**

Add `'migrate-validation-format' => MigrateValidationRulesFormatStep::class` to `STEPS` constant.

**Step 5: Run tests**

**Step 6: Commit**

```bash
git add src/Console/Commands/Upgrade/Steps/MigrateValidationRulesFormatStep.php src/Console/Commands/UpgradeCommand.php tests/Feature/Commands/
git commit -m "feat: add upgrade step to migrate validation rules to capability format"
```

---

## Phase 8: Cleanup

### Task 15: Remove old validation system

**Files:**
- Remove: `src/Enums/ValidationRule.php`
- Remove: `src/Data/ValidationRuleData.php`
- Remove: `src/Filament/Management/Forms/Components/CustomFieldValidationComponent.php` (if not already removed)
- Modify: `src/Services/ValidationService.php` — remove `convertUserRulesToValidatorFormat()` and related methods
- Modify: `src/FieldTypeSystem/FieldSchema.php` — remove `availableValidationRules()`, `defaultValidationRules()`, `defaultItemValidationRules()`, and related properties/getters
- Modify: `src/Data/FieldTypeData.php` — remove old `validationRules` property (the one that held enum cases)
- Update: `tests/Datasets/ValidationRulesDataset.php` — rewrite for new format
- Update: `tests/Pest.php` — update `toHaveValidationRule` expectation to work with new format
- Update: Any tests referencing `ValidationRule` enum

**Step 1: Search for all references to removed classes**

Run grep for `ValidationRule`, `ValidationRuleData`, `CustomFieldValidationComponent` across `src/` and `tests/`. Update or remove every reference.

**Step 2: Remove files and update references**

**Step 3: Run full test suite**

Run: `composer test`

**Step 4: Run PHPStan**

Run: `composer test:phpstan`

Fix any type errors from removed classes.

**Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 6: Commit**

```bash
git add -A
git commit -m "refactor: remove legacy ValidationRule enum and generic validation repeater"
```

---

## Phase 9: Final Verification

### Task 16: End-to-end smoke test

**Step 1: Run full test suite**

Run: `composer test`
Expected: All tests pass.

**Step 2: Run PHPStan**

Run: `composer test:phpstan`
Expected: No errors at level 8.

**Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: No formatting issues.

**Step 4: Manual smoke test in demo app**

1. Navigate to the demo app's admin panel
2. Create a new date custom field
3. Set min_date to "7 days from now" (relative)
4. Set max_date to an absolute date
5. Verify the Validation tab shows the new capability UI
6. Verify the date picker on the end-user form constrains to the configured range
7. Verify validation errors appear when submitting out-of-range dates

**Step 5: Final commit if any fixes needed**

---

## Summary

| Phase | Tasks | Estimated Commits |
|---|---|---|
| 1. Foundation | 1-3 | 3 |
| 2. FieldSchema Integration | 4 | 1 |
| 3. Capability Classes | 5-9 | 5 |
| 4. Wiring | 10-11 | 2 |
| 5. Admin UI | 12 | 1 |
| 6. Field Type Updates | 13 | 1 |
| 7. Upgrade Step | 14 | 1 |
| 8. Cleanup | 15 | 1 |
| 9. Verification | 16 | 0-1 |
| **Total** | **16 tasks** | **~15 commits** |
