<?php

declare(strict_types=1);

use Relaticle\CustomFields\Validation\Capabilities\MaxLengthCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinLengthCapability;

it('MinLengthCapability has correct key', function (): void {
    expect((new MinLengthCapability)->key())->toBe('min_length');
});

it('MinLengthCapability produces correct rules', function (): void {
    $capability = new MinLengthCapability;

    expect($capability->toRules(3))->toBe(['min:3'])
        ->and($capability->toRules(null))->toBe([]);
});

it('MinLengthCapability returns form schema', function (): void {
    $capability = new MinLengthCapability;

    expect($capability->formSchema('validation_rules'))->toHaveCount(1);
});

it('MaxLengthCapability has correct key', function (): void {
    expect((new MaxLengthCapability)->key())->toBe('max_length');
});

it('MaxLengthCapability produces correct rules', function (): void {
    $capability = new MaxLengthCapability;

    expect($capability->toRules(255))->toBe(['max:255'])
        ->and($capability->toRules(null))->toBe([]);
});

it('MaxLengthCapability returns form schema', function (): void {
    $capability = new MaxLengthCapability;

    expect($capability->formSchema('validation_rules'))->toHaveCount(1);
});
