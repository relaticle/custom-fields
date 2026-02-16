<?php

declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Validation\Capabilities\MaxDateCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinDateCapability;

it('MinDateCapability has correct key', function (): void {
    expect((new MinDateCapability)->key())->toBe('min_date');
});

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

it('MaxDateCapability has correct key', function (): void {
    expect((new MaxDateCapability)->key())->toBe('max_date');
});

it('MaxDateCapability produces before_or_equal rule for absolute date', function (): void {
    $capability = new MaxDateCapability;
    $value = ['mode' => 'absolute', 'absolute_value' => '2026-12-31'];

    expect($capability->toRules($value))->toBe(['before_or_equal:2026-12-31']);
});

it('MaxDateCapability produces before_or_equal rule for relative date', function (): void {
    Carbon::setTestNow('2026-02-17');

    $capability = new MaxDateCapability;
    $value = ['mode' => 'relative', 'relative_value' => 30, 'relative_unit' => 'days'];

    expect($capability->toRules($value))->toBe(['before_or_equal:2026-03-19']);
});

it('MaxDateCapability returns empty rules for null', function (): void {
    $capability = new MaxDateCapability;

    expect($capability->toRules(null))->toBe([]);
});

it('MinDateCapability returns form schema', function (): void {
    $capability = new MinDateCapability;
    $schema = $capability->formSchema('validation_rules');

    expect($schema)->not->toBeEmpty();
});
