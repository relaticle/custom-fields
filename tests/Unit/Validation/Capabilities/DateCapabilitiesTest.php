<?php

declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Validation\Capabilities\MaxDateCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinDateCapability;

it('MinDateCapability has correct key', function (): void {
    expect((new MinDateCapability)->key())->toBe('min_date');
});

it('MinDateCapability produces after_or_equal rule for future date', function (): void {
    Carbon::setTestNow('2026-02-17');

    $capability = new MinDateCapability;
    $value = ['relative_value' => 7, 'relative_unit' => 'days', 'direction' => 'from_now'];

    expect($capability->toRules($value))->toBe(['after_or_equal:2026-02-24']);
});

it('MinDateCapability produces after_or_equal rule for past date', function (): void {
    Carbon::setTestNow('2026-02-17');

    $capability = new MinDateCapability;
    $value = ['relative_value' => 30, 'relative_unit' => 'days', 'direction' => 'ago'];

    expect($capability->toRules($value))->toBe(['after_or_equal:2026-01-18']);
});

it('MinDateCapability returns empty rules for null', function (): void {
    $capability = new MinDateCapability;

    expect($capability->toRules(null))->toBe([]);
});

it('MaxDateCapability has correct key', function (): void {
    expect((new MaxDateCapability)->key())->toBe('max_date');
});

it('MaxDateCapability produces before_or_equal rule for future date', function (): void {
    Carbon::setTestNow('2026-02-17');

    $capability = new MaxDateCapability;
    $value = ['relative_value' => 30, 'relative_unit' => 'days', 'direction' => 'from_now'];

    expect($capability->toRules($value))->toBe(['before_or_equal:2026-03-19']);
});

it('MaxDateCapability produces before_or_equal rule for past date', function (): void {
    Carbon::setTestNow('2026-02-17');

    $capability = new MaxDateCapability;
    $value = ['relative_value' => 18, 'relative_unit' => 'years', 'direction' => 'ago'];

    expect($capability->toRules($value))->toBe(['before_or_equal:2008-02-17']);
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
