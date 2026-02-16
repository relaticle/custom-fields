<?php

declare(strict_types=1);

use Relaticle\CustomFields\Validation\Capabilities\AcceptedFileTypesCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxFileSizeCapability;

it('AcceptedFileTypesCapability has correct key', function (): void {
    expect((new AcceptedFileTypesCapability)->key())->toBe('accepted_types');
});

it('AcceptedFileTypesCapability has correct label', function (): void {
    expect((new AcceptedFileTypesCapability)->label())->toBe('Accepted File Types');
});

it('AcceptedFileTypesCapability produces correct rules', function (): void {
    $capability = new AcceptedFileTypesCapability;

    expect($capability->toRules(['jpg', 'png', 'pdf']))->toBe(['mimes:jpg,png,pdf'])
        ->and($capability->toRules(null))->toBe([])
        ->and($capability->toRules([]))->toBe([]);
});

it('AcceptedFileTypesCapability handles string value', function (): void {
    $capability = new AcceptedFileTypesCapability;

    expect($capability->toRules('jpg'))->toBe(['mimes:jpg']);
});

it('AcceptedFileTypesCapability returns form schema', function (): void {
    $capability = new AcceptedFileTypesCapability;

    expect($capability->formSchema('validation_rules'))->toHaveCount(1);
});

it('MaxFileSizeCapability has correct key', function (): void {
    expect((new MaxFileSizeCapability)->key())->toBe('max_size_kb');
});

it('MaxFileSizeCapability has correct label', function (): void {
    expect((new MaxFileSizeCapability)->label())->toBe('Maximum File Size (KB)');
});

it('MaxFileSizeCapability produces correct rules', function (): void {
    $capability = new MaxFileSizeCapability;

    expect($capability->toRules(1024))->toBe(['max:1024'])
        ->and($capability->toRules(null))->toBe([]);
});

it('MaxFileSizeCapability returns form schema', function (): void {
    $capability = new MaxFileSizeCapability;

    expect($capability->formSchema('validation_rules'))->toHaveCount(1);
});
