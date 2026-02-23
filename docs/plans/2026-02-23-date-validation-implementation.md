# Date Validation Redesign — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the 3-field relative date validation UI with an anchor-based system supporting Today, Fixed Date, Custom Field references, and Record Created anchors — with a preset-driven dropdown UI.

**Architecture:** Flat DTO (`DateConstraintValue`) with a `DateAnchor` enum driving resolution. New `DateConstraintRule` for submit-time validation. Progressive disclosure UI via `DateConstraintField`. The `ValidationCapability` contract's `toRules` return type widens from `array<int, string>` to `array<int, mixed>` to support rule objects.

**Tech Stack:** PHP 8.4, Spatie Laravel Data, Filament 5, Pest 4

**Design doc:** `docs/plans/2026-02-23-date-validation-redesign.md`

---

### Task 1: Create DateAnchor and DateOffsetDirection enums

**Files:**
- Create: `src/Enums/DateAnchor.php`
- Create: `src/Enums/DateOffsetDirection.php`
- Delete: `src/Enums/DateDirection.php`
- Test: `tests/Unit/Enums/DateAnchorTest.php`

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Enums/DateAnchorTest.php
declare(strict_types=1);

use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;

it('DateAnchor has correct cases and values', function (): void {
    expect(DateAnchor::Today->value)->toBe('today')
        ->and(DateAnchor::FixedDate->value)->toBe('fixed_date')
        ->and(DateAnchor::CustomField->value)->toBe('custom_field')
        ->and(DateAnchor::RecordCreated->value)->toBe('record_created');
});

it('DateAnchor implements HasLabel', function (): void {
    expect(DateAnchor::Today->getLabel())->toBe('Today')
        ->and(DateAnchor::FixedDate->getLabel())->toBe('Fixed Date')
        ->and(DateAnchor::CustomField->getLabel())->toBe('Another Field')
        ->and(DateAnchor::RecordCreated->getLabel())->toBe('Record Creation Date');
});

it('DateOffsetDirection has correct cases and values', function (): void {
    expect(DateOffsetDirection::Before->value)->toBe('before')
        ->and(DateOffsetDirection::After->value)->toBe('after');
});

it('DateOffsetDirection implements HasLabel', function (): void {
    expect(DateOffsetDirection::Before->getLabel())->toBe('Before')
        ->and(DateOffsetDirection::After->getLabel())->toBe('After');
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Enums/DateAnchorTest.php`
Expected: FAIL — classes not found

**Step 3: Create the enums**

```php
<?php
// src/Enums/DateAnchor.php
declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum DateAnchor: string implements HasLabel
{
    case Today = 'today';
    case FixedDate = 'fixed_date';
    case CustomField = 'custom_field';
    case RecordCreated = 'record_created';

    public function getLabel(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::FixedDate => 'Fixed Date',
            self::CustomField => 'Another Field',
            self::RecordCreated => 'Record Creation Date',
        };
    }
}
```

```php
<?php
// src/Enums/DateOffsetDirection.php
declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum DateOffsetDirection: string implements HasLabel
{
    case Before = 'before';
    case After = 'after';

    public function getLabel(): string
    {
        return match ($this) {
            self::Before => 'Before',
            self::After => 'After',
        };
    }
}
```

**Step 4: Delete `src/Enums/DateDirection.php`**

Run: `rm src/Enums/DateDirection.php`

**Step 5: Run tests to verify they pass**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Enums/DateAnchorTest.php`
Expected: PASS

**Step 6: Delete old enum test if it exists**

Check for and remove any test file for `DateDirection`. The `DateUnitTest.php` should still pass unchanged.

**Step 7: Commit**

```bash
git add -A && git commit -m "feat: add DateAnchor and DateOffsetDirection enums, remove DateDirection"
```

---

### Task 2: Rewrite DateConstraintValue DTO

**Files:**
- Modify: `src/Data/DateConstraintValue.php` (full rewrite)
- Modify: `tests/Unit/Data/DateConstraintValueTest.php` (full rewrite)

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Data/DateConstraintValueTest.php
declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-17 10:30:00');
});

// --- Today anchor ---

it('resolves today anchor with zero offset', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-17');
});

it('resolves today anchor with forward offset in days', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 7,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-24');
});

it('resolves today anchor with backward offset in days', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 30,
        'offset_unit' => 'days',
        'offset_direction' => 'before',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-01-18');
});

it('resolves today anchor with weeks', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 2,
        'offset_unit' => 'weeks',
        'offset_direction' => 'after',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-03-03');
});

it('resolves today anchor with months backward', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 3,
        'offset_unit' => 'months',
        'offset_direction' => 'before',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2025-11-17');
});

it('resolves today anchor with quarters', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 1,
        'offset_unit' => 'quarters',
        'offset_direction' => 'after',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-05-17');
});

it('resolves today anchor with years backward', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 18,
        'offset_unit' => 'years',
        'offset_direction' => 'before',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2008-02-17');
});

it('resolves today anchor to start of day', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    expect($constraint->resolve()->format('H:i:s'))->toBe('00:00:00');
});

// --- Fixed date anchor ---

it('resolves fixed date anchor with zero offset', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'fixed_date',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'fixed_date' => '2026-01-01',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-01-01');
});

it('resolves fixed date anchor with offset', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'fixed_date',
        'offset' => 7,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'fixed_date' => '2026-01-01',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-01-08');
});

// --- Custom field anchor ---

it('resolves custom field anchor via get callback', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'custom_field',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'field_reference' => 'start_date',
    ]);

    $getCallback = fn (string $path): string => match ($path) {
        'custom_fields.start_date' => '2026-03-01',
        default => throw new RuntimeException("Unexpected path: {$path}"),
    };

    expect($constraint->resolve(getCallback: $getCallback)->format('Y-m-d'))->toBe('2026-03-01');
});

it('resolves custom field anchor with offset', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'custom_field',
        'offset' => 7,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'field_reference' => 'start_date',
    ]);

    $getCallback = fn (string $path): string => '2026-03-01';

    expect($constraint->resolve(getCallback: $getCallback)->format('Y-m-d'))->toBe('2026-03-08');
});

it('falls back to today when custom field value is null', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'custom_field',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'field_reference' => 'start_date',
    ]);

    $getCallback = fn (string $path): ?string => null;

    expect($constraint->resolve(getCallback: $getCallback)->format('Y-m-d'))->toBe('2026-02-17');
});

// --- Record created anchor ---

it('resolves record created anchor', function (): void {
    $record = Mockery::mock(Model::class);
    $record->shouldReceive('getAttribute')->with('created_at')->andReturn(Carbon::parse('2026-01-15'));

    $constraint = DateConstraintValue::from([
        'anchor' => 'record_created',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    expect($constraint->resolve(record: $record)->format('Y-m-d'))->toBe('2026-01-15');
});

it('resolves record created anchor with offset', function (): void {
    $record = Mockery::mock(Model::class);
    $record->shouldReceive('getAttribute')->with('created_at')->andReturn(Carbon::parse('2026-01-15'));

    $constraint = DateConstraintValue::from([
        'anchor' => 'record_created',
        'offset' => 30,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    expect($constraint->resolve(record: $record)->format('Y-m-d'))->toBe('2026-02-14');
});

it('falls back to today when record is null for record_created anchor', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'record_created',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-17');
});

// --- Serialization ---

it('serializes to and from array', function (): void {
    $data = [
        'anchor' => 'today',
        'offset' => 7,
        'offset_unit' => 'days',
        'offset_direction' => 'before',
    ];

    $constraint = DateConstraintValue::from($data);
    $serialized = $constraint->toArray();

    expect($serialized['anchor'])->toBe('today')
        ->and($serialized['offset'])->toBe(7)
        ->and($serialized['offset_unit'])->toBe('days')
        ->and($serialized['offset_direction'])->toBe('before');
});

it('serializes with fixed_date', function (): void {
    $data = [
        'anchor' => 'fixed_date',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'fixed_date' => '2026-06-15',
    ];

    $constraint = DateConstraintValue::from($data);
    $serialized = $constraint->toArray();

    expect($serialized['fixed_date'])->toBe('2026-06-15');
});

it('serializes with field_reference', function (): void {
    $data = [
        'anchor' => 'custom_field',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'field_reference' => 'start_date',
    ];

    $constraint = DateConstraintValue::from($data);
    $serialized = $constraint->toArray();

    expect($serialized['field_reference'])->toBe('start_date');
});

it('uses default values for offset and direction', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
    ]);

    expect($constraint->offset)->toBe(0)
        ->and($constraint->offsetUnit)->toBe(DateUnit::Days)
        ->and($constraint->offsetDirection)->toBe(DateOffsetDirection::After);
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Data/DateConstraintValueTest.php`
Expected: FAIL

**Step 3: Rewrite the DTO**

```php
<?php
// src/Data/DateConstraintValue.php
declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

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
            DateAnchor::FixedDate => Carbon::parse($this->fixedDate)->startOfDay(),
            DateAnchor::CustomField => $this->resolveCustomField($getCallback),
            DateAnchor::RecordCreated => $record?->created_at?->copy() ?? now()->startOfDay(),
        };

        if ($this->offset === 0) {
            return $base;
        }

        return $this->offsetDirection === DateOffsetDirection::Before
            ? $base->sub($this->offsetUnit->value, $this->offset)
            : $base->add($this->offsetUnit->value, $this->offset);
    }

    private function resolveCustomField(?Closure $getCallback): Carbon
    {
        $value = $getCallback
            ? $getCallback("custom_fields.{$this->fieldReference}")
            : null;

        if ($value === null || $value === '') {
            return now()->startOfDay();
        }

        return Carbon::parse($value)->startOfDay();
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Data/DateConstraintValueTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add -A && git commit -m "feat: rewrite DateConstraintValue with anchor-based resolution"
```

---

### Task 3: Create DateConstraintRule validation rule

**Files:**
- Create: `src/Validation/Rules/DateConstraintRule.php`
- Create: `tests/Unit/Validation/Rules/DateConstraintRuleTest.php`

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Validation/Rules/DateConstraintRuleTest.php
declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Validation\Rules\DateConstraintRule;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-17');
});

it('passes when date is after min date (today anchor)', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    $rule = new DateConstraintRule($constraint, 'after_or_equal');
    $failed = false;

    $rule->validate('field', '2026-02-17', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('fails when date is before min date (today anchor)', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    $rule = new DateConstraintRule($constraint, 'after_or_equal');
    $failed = false;

    $rule->validate('field', '2026-02-16', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('passes when date is before max date (today anchor)', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    $rule = new DateConstraintRule($constraint, 'before_or_equal');
    $failed = false;

    $rule->validate('field', '2026-02-17', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('fails when date is after max date (today anchor)', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    $rule = new DateConstraintRule($constraint, 'before_or_equal');
    $failed = false;

    $rule->validate('field', '2026-02-18', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('resolves custom field anchor from form data', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'custom_field',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'field_reference' => 'start_date',
    ]);

    $rule = new DateConstraintRule(
        constraint: $constraint,
        comparison: 'after_or_equal',
        formData: ['custom_fields' => ['start_date' => '2026-03-01']],
    );

    $failed = false;
    $rule->validate('field', '2026-03-01', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('resolves fixed date anchor', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'fixed_date',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        'fixed_date' => '2026-01-01',
    ]);

    $rule = new DateConstraintRule($constraint, 'after_or_equal');
    $failed = false;

    $rule->validate('field', '2025-12-31', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('skips validation when value is null', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    $rule = new DateConstraintRule($constraint, 'after_or_equal');
    $failed = false;

    $rule->validate('field', null, function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Validation/Rules/DateConstraintRuleTest.php`
Expected: FAIL

**Step 3: Create the rule class**

```php
<?php
// src/Validation/Rules/DateConstraintRule.php
declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\DateConstraintValue;

final readonly class DateConstraintRule implements ValidationRule
{
    /**
     * @param  array<string, mixed>|null  $formData
     */
    public function __construct(
        private DateConstraintValue $constraint,
        private string $comparison,
        private ?array $formData = null,
        private ?Model $record = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $boundary = $this->constraint->resolve(
            getCallback: fn (string $path): mixed => data_get($this->formData, $path),
            record: $this->record,
        );

        $date = Carbon::parse($value)->startOfDay();
        $boundaryDate = $boundary->startOfDay();

        $passes = match ($this->comparison) {
            'after_or_equal' => $date->greaterThanOrEqualTo($boundaryDate),
            'before_or_equal' => $date->lessThanOrEqualTo($boundaryDate),
            default => true,
        };

        if (! $passes) {
            $fail($this->getMessage($boundaryDate));
        }
    }

    private function getMessage(Carbon $boundary): string
    {
        $formattedDate = $boundary->format('M j, Y');

        return match ($this->comparison) {
            'after_or_equal' => "The :attribute must be on or after {$formattedDate}.",
            'before_or_equal' => "The :attribute must be on or before {$formattedDate}.",
            default => "The :attribute has an invalid date.",
        };
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Validation/Rules/DateConstraintRuleTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add -A && git commit -m "feat: add DateConstraintRule for submit-time date validation"
```

---

### Task 4: Update ValidationCapability contract and capabilities

**Files:**
- Modify: `src/Contracts/ValidationCapability.php` — widen `toRules` return type
- Modify: `src/Validation/Capabilities/MinDateCapability.php` — use new DTO + rule
- Modify: `src/Validation/Capabilities/MaxDateCapability.php` — use new DTO + rule
- Modify: `tests/Unit/Validation/Capabilities/DateCapabilitiesTest.php` — rewrite tests

**Step 1: Rewrite the capability tests**

```php
<?php
// tests/Unit/Validation/Capabilities/DateCapabilitiesTest.php
declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Validation\Capabilities\MaxDateCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinDateCapability;
use Relaticle\CustomFields\Validation\Rules\DateConstraintRule;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-17');
});

it('MinDateCapability has correct key', function (): void {
    expect((new MinDateCapability)->key())->toBe('min_date');
});

it('MinDateCapability returns DateConstraintRule for today anchor', function (): void {
    $capability = new MinDateCapability;
    $value = ['anchor' => 'today', 'offset' => 7, 'offset_unit' => 'days', 'offset_direction' => 'after'];

    $rules = $capability->toRules($value);

    expect($rules)->toHaveCount(1)
        ->and($rules[0])->toBeInstanceOf(DateConstraintRule::class);
});

it('MinDateCapability returns empty rules for null', function (): void {
    expect((new MinDateCapability)->toRules(null))->toBe([]);
});

it('MaxDateCapability has correct key', function (): void {
    expect((new MaxDateCapability)->key())->toBe('max_date');
});

it('MaxDateCapability returns DateConstraintRule for today anchor', function (): void {
    $capability = new MaxDateCapability;
    $value = ['anchor' => 'today', 'offset' => 30, 'offset_unit' => 'days', 'offset_direction' => 'after'];

    $rules = $capability->toRules($value);

    expect($rules)->toHaveCount(1)
        ->and($rules[0])->toBeInstanceOf(DateConstraintRule::class);
});

it('MaxDateCapability returns empty rules for null', function (): void {
    expect((new MaxDateCapability)->toRules(null))->toBe([]);
});

it('MinDateCapability returns form schema', function (): void {
    $capability = new MinDateCapability;
    $schema = $capability->formSchema('validation_rules');

    expect($schema)->not->toBeEmpty();
});

it('MaxDateCapability returns form schema', function (): void {
    $capability = new MaxDateCapability;
    $schema = $capability->formSchema('validation_rules');

    expect($schema)->not->toBeEmpty();
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Validation/Capabilities/DateCapabilitiesTest.php`
Expected: FAIL

**Step 3: Update the contract**

In `src/Contracts/ValidationCapability.php`, change the `toRules` return type docblock:

```php
/** @return array<int, mixed> */
public function toRules(mixed $value): array;
```

**Step 4: Rewrite MinDateCapability**

```php
<?php
// src/Validation/Capabilities/MinDateCapability.php
declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Contracts\ValidationCapability;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Filament\Management\Forms\Components\DateConstraintField;
use Relaticle\CustomFields\Validation\Rules\DateConstraintRule;

final readonly class MinDateCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'min_date';
    }

    public function label(): string
    {
        return 'Minimum Date';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return DateConstraintField::make("{$statePath}.min_date", 'Minimum Date', 'min');
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $constraintValue = DateConstraintValue::from($value);

        $component->minDate(fn (Get $get, ?Model $record = null) => $constraintValue->resolve(
            getCallback: fn (string $path): mixed => $get($path),
            record: $record,
        ));
    }

    /** @return array<int, mixed> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return [new DateConstraintRule(DateConstraintValue::from($value), 'after_or_equal')];
    }
}
```

**Step 5: Rewrite MaxDateCapability** (same pattern, `before_or_equal`, `'max'` context)

```php
<?php
// src/Validation/Capabilities/MaxDateCapability.php
declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Contracts\ValidationCapability;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Filament\Management\Forms\Components\DateConstraintField;
use Relaticle\CustomFields\Validation\Rules\DateConstraintRule;

final readonly class MaxDateCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'max_date';
    }

    public function label(): string
    {
        return 'Maximum Date';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return DateConstraintField::make("{$statePath}.max_date", 'Maximum Date', 'max');
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $constraintValue = DateConstraintValue::from($value);

        $component->maxDate(fn (Get $get, ?Model $record = null) => $constraintValue->resolve(
            getCallback: fn (string $path): mixed => $get($path),
            record: $record,
        ));
    }

    /** @return array<int, mixed> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return [new DateConstraintRule(DateConstraintValue::from($value), 'before_or_equal')];
    }
}
```

**Step 6: Run tests to verify they pass**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Validation/Capabilities/DateCapabilitiesTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add -A && git commit -m "feat: update date capabilities to use anchor-based resolution and DateConstraintRule"
```

---

### Task 5: Rewrite DateConstraintField config UI

**Files:**
- Modify: `src/Filament/Management/Forms/Components/DateConstraintField.php` (full rewrite)

This is the admin-facing UI where field admins configure date constraints. The `make()` method signature changes to accept a `$context` parameter (`'min'` or `'max'`) so dropdown labels are contextual.

**Step 1: Rewrite DateConstraintField**

```php
<?php
// src/Filament/Management/Forms/Components/DateConstraintField.php
declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;
use Relaticle\CustomFields\Models\CustomField;

final class DateConstraintField
{
    /**
     * @return array<int, Component>
     */
    public static function make(string $statePath, string $label, string $context = 'min'): array
    {
        return [
            Fieldset::make($label)
                ->schema([
                    Select::make("{$statePath}.preset")
                        ->label('Restriction')
                        ->options(static::getPresetOptions($context))
                        ->live()
                        ->afterStateHydrated(function (Select $component, ?array $state) use ($statePath): void {
                            // Reverse-map stored data to preset
                            $parent = $component->getContainer()->getState();
                            $data = data_get($parent, $statePath);

                            if (! is_array($data) || ! isset($data['anchor'])) {
                                $component->state('none');

                                return;
                            }

                            $anchor = $data['anchor'] ?? null;
                            $offset = $data['offset'] ?? 0;

                            if ($anchor === 'today' && $offset === 0) {
                                $component->state('today_preset');
                            } elseif ($anchor === 'today') {
                                $component->state('today_offset');
                            } elseif ($anchor === 'custom_field') {
                                $component->state('custom_field');
                            } elseif ($anchor === 'record_created') {
                                $component->state('record_created');
                            } elseif ($anchor === 'fixed_date') {
                                $component->state('fixed_date');
                            } else {
                                $component->state('none');
                            }
                        })
                        ->afterStateUpdated(function (Set $set, ?string $state) use ($statePath, $context): void {
                            $direction = $context === 'min'
                                ? DateOffsetDirection::After->value
                                : DateOffsetDirection::Before->value;

                            match ($state) {
                                'none' => $set($statePath, null),
                                'today_preset' => $set($statePath, [
                                    'anchor' => DateAnchor::Today->value,
                                    'offset' => 0,
                                    'offset_unit' => DateUnit::Days->value,
                                    'offset_direction' => $direction,
                                    'preset' => 'today_preset',
                                ]),
                                'today_offset' => $set($statePath, [
                                    'anchor' => DateAnchor::Today->value,
                                    'offset' => 0,
                                    'offset_unit' => DateUnit::Days->value,
                                    'offset_direction' => $direction,
                                    'preset' => 'today_offset',
                                ]),
                                'custom_field' => $set($statePath, [
                                    'anchor' => DateAnchor::CustomField->value,
                                    'offset' => 0,
                                    'offset_unit' => DateUnit::Days->value,
                                    'offset_direction' => $direction,
                                    'preset' => 'custom_field',
                                ]),
                                'record_created' => $set($statePath, [
                                    'anchor' => DateAnchor::RecordCreated->value,
                                    'offset' => 0,
                                    'offset_unit' => DateUnit::Days->value,
                                    'offset_direction' => $direction,
                                    'preset' => 'record_created',
                                ]),
                                'fixed_date' => $set($statePath, [
                                    'anchor' => DateAnchor::FixedDate->value,
                                    'offset' => 0,
                                    'offset_unit' => DateUnit::Days->value,
                                    'offset_direction' => $direction,
                                    'preset' => 'fixed_date',
                                ]),
                                default => null,
                            };
                        })
                        ->columnSpanFull(),

                    // Offset fields — visible for today_offset, custom_field, record_created
                    TextInput::make("{$statePath}.offset")
                        ->label('Offset')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->visible(fn (Get $get): bool => in_array(
                            $get("{$statePath}.preset"),
                            ['today_offset', 'custom_field', 'record_created'],
                        )),

                    Select::make("{$statePath}.offset_unit")
                        ->label('Unit')
                        ->options(DateUnit::class)
                        ->default(DateUnit::Days->value)
                        ->visible(fn (Get $get): bool => in_array(
                            $get("{$statePath}.preset"),
                            ['today_offset', 'custom_field', 'record_created'],
                        )),

                    Select::make("{$statePath}.offset_direction")
                        ->label('Direction')
                        ->options(DateOffsetDirection::class)
                        ->default(fn () => DateOffsetDirection::After->value)
                        ->visible(fn (Get $get): bool => $get("{$statePath}.preset") === 'today_offset'),

                    // Field reference — visible for custom_field
                    Select::make("{$statePath}.field_reference")
                        ->label('Reference Field')
                        ->options(function (Get $get): array {
                            $entityType = $get('entity_type');
                            $currentCode = $get('code');

                            if (! $entityType) {
                                return [];
                            }

                            return CustomField::query()
                                ->where('entity_type', $entityType)
                                ->whereIn('type', ['date', 'date-time'])
                                ->when($currentCode, fn ($q) => $q->where('code', '!=', $currentCode))
                                ->where('active', true)
                                ->pluck('name', 'code')
                                ->all();
                        })
                        ->required()
                        ->visible(fn (Get $get): bool => $get("{$statePath}.preset") === 'custom_field'),

                    // Fixed date — visible for fixed_date
                    DatePicker::make("{$statePath}.fixed_date")
                        ->label('Date')
                        ->required()
                        ->native(false)
                        ->format('Y-m-d')
                        ->visible(fn (Get $get): bool => $get("{$statePath}.preset") === 'fixed_date'),
                ])
                ->columns(3),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function getPresetOptions(string $context): array
    {
        $todayLabel = $context === 'min' ? 'Today or later' : 'Today or earlier';
        $fieldLabel = $context === 'min' ? 'After another field...' : 'Before another field...';
        $recordLabel = $context === 'min' ? 'After record creation date...' : 'Before record creation date...';

        return [
            'none' => 'No restriction',
            'today_preset' => $todayLabel,
            'today_offset' => 'Offset from today...',
            'custom_field' => $fieldLabel,
            'record_created' => $recordLabel,
            'fixed_date' => 'Fixed date...',
        ];
    }
}
```

**Note on `$statePath` nesting:** The preset is stored alongside the constraint data but stripped before saving. The `afterStateUpdated` callback writes the full constraint structure. The `preset` key is transient UI state — `DateConstraintValue::from()` ignores unknown keys via Spatie Data.

**Step 2: Verify the management form still renders**

Run: `cd /Users/manuk/Herd/filament-demo && php artisan test --compact --filter=CustomField`
(Run any existing feature tests that exercise the custom field management form.)

**Step 3: Commit**

```bash
cd /Users/manuk/Herd/custom-fields && git add -A && git commit -m "feat: rewrite DateConstraintField with preset-driven UI"
```

---

### Task 6: Update AbstractFormComponent for context-aware resolution

**Files:**
- Modify: `src/Filament/Integration/Base/AbstractFormComponent.php:98-117`

The `applyValidationCapabilities` method needs no changes — the capabilities themselves now handle `$get`/`$record` in their closures. However, we need to ensure that fields referenced by `CustomField` anchors are marked `->live()` so the date picker updates reactively.

**Step 1: Add reactive field reference handling**

After the existing `applyValidationCapabilities` call in `configure()` (line 93), add logic to check if any date capability references another custom field, and if so, find that field in the form and mark it live.

This is best done inside `applyValidationCapabilities` itself:

In `src/Filament/Integration/Base/AbstractFormComponent.php`, modify `applyValidationCapabilities`:

```php
private function applyValidationCapabilities(Field $field, CustomField $customField): void
{
    $fieldTypeData = $customField->typeData;

    if (! $fieldTypeData) {
        return;
    }

    $validationRules = $customField->validation_rules;

    foreach ($fieldTypeData->validationCapabilities as $capabilityClass) {
        $capability = app($capabilityClass);
        /** @phpstan-ignore nullsafe.neverNull */
        $value = $validationRules?->get($capability->key());

        if ($value !== null) {
            $capability->applyToComponent($field, $value);
        }
    }
}
```

Actually — the capabilities already pass `Get $get` in the closure to `minDate()`/`maxDate()`. Filament resolves `$get` at render time from the component's schema context. The referenced field needs to be `->live()` for Livewire to re-render when it changes.

The simplest approach: let the `configure()` method check if this field is referenced by any other date field's constraint. If another field's `validation_rules.min_date.field_reference` or `validation_rules.max_date.field_reference` equals this field's code, mark this field `->live()`.

This is already handled by the existing `dependentFieldCodes` mechanism (line 88-91 in `configure()`). We just need to ensure the caller (`FormBuilder`) populates `dependentFieldCodes` to include date constraint references in addition to visibility references.

**Step 2: Check FormBuilder**

Read `src/Filament/Integration/Builders/FormBuilder.php` to see how `dependentFieldCodes` is populated, then extend it.

**Note:** This step requires reading the FormBuilder code. The implementing agent should:
1. Read `FormBuilder.php` to find where `dependentFieldCodes` is computed
2. Add logic to collect field codes referenced in `validation_rules.min_date.field_reference` and `validation_rules.max_date.field_reference`
3. Include those codes in the `dependentFieldCodes` array passed to `make()`

**Step 3: Run full test suite**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest`
Expected: All tests pass

**Step 4: Commit**

```bash
git add -A && git commit -m "feat: add reactive field references for cross-field date constraints"
```

---

### Task 7: Add circular reference detection

**Files:**
- Create: `src/Validation/DateFieldReferenceValidator.php`
- Create: `tests/Unit/Validation/DateFieldReferenceValidatorTest.php`

**Step 1: Write failing tests**

```php
<?php
// tests/Unit/Validation/DateFieldReferenceValidatorTest.php
declare(strict_types=1);

use Relaticle\CustomFields\Validation\DateFieldReferenceValidator;

it('detects no cycle when there are no references', function (): void {
    // field_a has no field reference, field_b has no field reference
    $fieldsWithReferences = [];

    expect(DateFieldReferenceValidator::hasCycle('field_a', $fieldsWithReferences))->toBeFalse();
});

it('detects no cycle for a simple chain', function (): void {
    // field_b references field_a (no cycle)
    $fieldsWithReferences = [
        'field_b' => 'field_a',
    ];

    expect(DateFieldReferenceValidator::hasCycle('field_b', $fieldsWithReferences))->toBeFalse();
});

it('detects a direct cycle between two fields', function (): void {
    // field_a references field_b, field_b references field_a
    $fieldsWithReferences = [
        'field_a' => 'field_b',
        'field_b' => 'field_a',
    ];

    expect(DateFieldReferenceValidator::hasCycle('field_a', $fieldsWithReferences))->toBeTrue();
});

it('detects an indirect cycle through three fields', function (): void {
    // field_a -> field_b -> field_c -> field_a
    $fieldsWithReferences = [
        'field_a' => 'field_b',
        'field_b' => 'field_c',
        'field_c' => 'field_a',
    ];

    expect(DateFieldReferenceValidator::hasCycle('field_a', $fieldsWithReferences))->toBeTrue();
});

it('detects no cycle in a longer chain', function (): void {
    // field_a -> field_b -> field_c (no cycle)
    $fieldsWithReferences = [
        'field_a' => 'field_b',
        'field_b' => 'field_c',
    ];

    expect(DateFieldReferenceValidator::hasCycle('field_a', $fieldsWithReferences))->toBeFalse();
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Validation/DateFieldReferenceValidatorTest.php`
Expected: FAIL

**Step 3: Implement**

```php
<?php
// src/Validation/DateFieldReferenceValidator.php
declare(strict_types=1);

namespace Relaticle\CustomFields\Validation;

final class DateFieldReferenceValidator
{
    /**
     * Detect if adding/updating a field's reference would create a cycle.
     *
     * @param  string  $fieldCode  The field being saved
     * @param  array<string, string>  $fieldsWithReferences  Map of field_code => referenced_field_code
     */
    public static function hasCycle(string $fieldCode, array $fieldsWithReferences): bool
    {
        $visited = [];
        $current = $fieldCode;

        while (isset($fieldsWithReferences[$current])) {
            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;
            $current = $fieldsWithReferences[$current];
        }

        return false;
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest tests/Unit/Validation/DateFieldReferenceValidatorTest.php`
Expected: PASS

**Step 5: Integrate into the management form save logic**

The implementing agent should find where the custom field form is saved (likely in a Filament Resource's `CreateRecord` or `EditRecord` page, or in the form's `afterSave` callback) and add a validation rule that:
1. Collects all date field references for the same entity_type
2. Builds the `$fieldsWithReferences` map
3. Calls `DateFieldReferenceValidator::hasCycle()`
4. Returns a validation error if a cycle is detected

**Step 6: Commit**

```bash
git add -A && git commit -m "feat: add circular reference detection for cross-field date constraints"
```

---

### Task 8: Run full test suite and lint

**Step 1: Run all tests**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pest`
Expected: All pass

**Step 2: Run Pint**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/pint --dirty --format agent`

**Step 3: Run PHPStan**

Run: `cd /Users/manuk/Herd/custom-fields && vendor/bin/phpstan analyse`

**Step 4: Fix any issues and commit**

```bash
git add -A && git commit -m "chore: fix lint and static analysis issues"
```

---

### Task 9: Manual integration test in filament-demo

**Step 1: Verify in browser**

1. Open the custom fields management page for an entity (e.g., Opportunity)
2. Create or edit a date field
3. Go to the Validation tab
4. Verify the Minimum Date dropdown shows all preset options
5. Select "Today or later" — verify no extra fields appear
6. Select "Offset from today..." — verify offset/unit/direction fields appear
7. Select "After another field..." — verify field reference dropdown appears with sibling date fields
8. Select "After record creation date..." — verify offset fields appear
9. Select "Fixed date..." — verify date picker appears
10. Save and verify the JSON is stored correctly

**Step 2: Test runtime validation**

1. Go to an entity form (e.g., create Opportunity)
2. Try entering a date that violates the constraint
3. Verify the error message appears on submit
4. For cross-field constraints, change the referenced field and verify the date picker updates

**Step 3: Commit any fixes**

```bash
git add -A && git commit -m "fix: address issues found during integration testing"
```
