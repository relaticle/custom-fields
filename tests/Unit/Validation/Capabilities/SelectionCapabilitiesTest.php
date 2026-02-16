<?php

declare(strict_types=1);

use Relaticle\CustomFields\Validation\Capabilities\MaxSelectionsCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinSelectionsCapability;

it('MinSelectionsCapability has correct key', function (): void {
    expect((new MinSelectionsCapability)->key())->toBe('min_selections');
});

it('MinSelectionsCapability produces correct rules', function (): void {
    $capability = new MinSelectionsCapability;

    expect($capability->toRules(2))->toBe(['min:2'])
        ->and($capability->toRules(null))->toBe([]);
});

it('MinSelectionsCapability returns form schema', function (): void {
    $capability = new MinSelectionsCapability;

    expect($capability->formSchema('validation_rules'))->toHaveCount(1);
});

it('MinSelectionsCapability has correct label', function (): void {
    expect((new MinSelectionsCapability)->label())->toBe('Minimum Selections');
});

it('MaxSelectionsCapability has correct key', function (): void {
    expect((new MaxSelectionsCapability)->key())->toBe('max_selections');
});

it('MaxSelectionsCapability produces correct rules', function (): void {
    $capability = new MaxSelectionsCapability;

    expect($capability->toRules(5))->toBe(['max:5'])
        ->and($capability->toRules(null))->toBe([]);
});

it('MaxSelectionsCapability returns form schema', function (): void {
    $capability = new MaxSelectionsCapability;

    expect($capability->formSchema('validation_rules'))->toHaveCount(1);
});

it('MaxSelectionsCapability has correct label', function (): void {
    expect((new MaxSelectionsCapability)->label())->toBe('Maximum Selections');
});
