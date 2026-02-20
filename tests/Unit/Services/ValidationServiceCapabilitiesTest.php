<?php

declare(strict_types=1);

use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ValidationService;

it('returns required true when required is set in validation rules', function (): void {
    $field = CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => ['required' => true],
    ]);

    $service = app(ValidationService::class);
    expect($service->isRequired($field))->toBeTrue();
});

it('returns required false when not set', function (): void {
    $field = CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => [],
    ]);

    $service = app(ValidationService::class);
    expect($service->isRequired($field))->toBeFalse();
});

it('returns required false for null validation rules', function (): void {
    $field = CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => null,
    ]);

    $service = app(ValidationService::class);
    expect($service->isRequired($field))->toBeFalse();
});
