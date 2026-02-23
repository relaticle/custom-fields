<?php

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
    expect($rules)->toHaveCount(1)->and($rules[0])->toBeInstanceOf(DateConstraintRule::class);
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
    expect($rules)->toHaveCount(1)->and($rules[0])->toBeInstanceOf(DateConstraintRule::class);
});

it('MaxDateCapability returns empty rules for null', function (): void {
    expect((new MaxDateCapability)->toRules(null))->toBe([]);
});

it('MinDateCapability returns form schema', function (): void {
    expect((new MinDateCapability)->formSchema('validation_rules'))->not->toBeEmpty();
});

it('MaxDateCapability returns form schema', function (): void {
    expect((new MaxDateCapability)->formSchema('validation_rules'))->not->toBeEmpty();
});
