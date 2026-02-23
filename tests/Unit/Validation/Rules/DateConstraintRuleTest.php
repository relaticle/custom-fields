<?php

declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Validation\Rules\DateConstraintRule;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-17');
});

it('passes when date is after min date (today anchor)', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today', 'offset' => 0, 'offset_unit' => 'days', 'offset_direction' => 'after',
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
        'anchor' => 'today', 'offset' => 0, 'offset_unit' => 'days', 'offset_direction' => 'after',
    ]);
    $rule = new DateConstraintRule($constraint, 'after_or_equal');
    $failed = false;
    $rule->validate('field', '2026-02-16', function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('passes when date equals max date (today anchor)', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today', 'offset' => 0, 'offset_unit' => 'days', 'offset_direction' => 'after',
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
        'anchor' => 'today', 'offset' => 0, 'offset_unit' => 'days', 'offset_direction' => 'after',
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
        'anchor' => 'custom_field', 'offset' => 0, 'offset_unit' => 'days', 'offset_direction' => 'after', 'field_reference' => 'start_date',
    ]);
    $rule = new DateConstraintRule(constraint: $constraint, comparison: 'after_or_equal', formData: ['custom_fields' => ['start_date' => '2026-03-01']]);
    $failed = false;
    $rule->validate('field', '2026-03-01', function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

it('resolves fixed date anchor', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'fixed_date', 'offset' => 0, 'offset_unit' => 'days', 'offset_direction' => 'after', 'fixed_date' => '2026-01-01',
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
        'anchor' => 'today', 'offset' => 0, 'offset_unit' => 'days', 'offset_direction' => 'after',
    ]);
    $rule = new DateConstraintRule($constraint, 'after_or_equal');
    $failed = false;
    $rule->validate('field', null, function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

it('skips validation when value is empty string', function (): void {
    $constraint = DateConstraintValue::from([
        'anchor' => 'today', 'offset' => 0, 'offset_unit' => 'days', 'offset_direction' => 'after',
    ]);
    $rule = new DateConstraintRule($constraint, 'after_or_equal');
    $failed = false;
    $rule->validate('field', '', function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});
