<?php

declare(strict_types=1);

use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Validation\Capabilities\AcceptedFileTypesCapability;
use Relaticle\CustomFields\Validation\Capabilities\DecimalPlacesCapability;
use Relaticle\CustomFields\Validation\Capabilities\IntegerOnlyCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxDateCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxFileSizeCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxLengthCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxSelectionsCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxValueCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinDateCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinLengthCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinSelectionsCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinValueCapability;

it('registers capabilities via withValidationCapabilities', function (): void {
    $schema = FieldSchema::date()
        ->key('date')
        ->label('Date')
        ->withValidationCapabilities(
            MinDateCapability::class,
            MaxDateCapability::class,
        );

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});

it('registers custom capabilities via withValidationCapabilities', function (): void {
    $schema = FieldSchema::text()
        ->key('custom')
        ->label('Custom')
        ->withValidationCapabilities('App\\CustomCapability');

    expect($schema->getValidationCapabilities())->toHaveCount(1)
        ->and($schema->getValidationCapabilities()[0])->toBe('App\\CustomCapability');
});

it('carries capabilities through to FieldTypeData', function (): void {
    $schema = FieldSchema::text()
        ->key('text')
        ->label('Text')
        ->withValidationCapabilities(
            MinLengthCapability::class,
            MaxLengthCapability::class,
        );

    $data = $schema->data();

    expect($data->validationCapabilities)->toHaveCount(2);
});

it('registers all numeric capabilities', function (): void {
    $schema = FieldSchema::numeric()
        ->key('number')
        ->label('Number')
        ->withValidationCapabilities(
            MinValueCapability::class,
            MaxValueCapability::class,
            IntegerOnlyCapability::class,
            DecimalPlacesCapability::class,
        );

    expect($schema->getValidationCapabilities())->toHaveCount(4);
});

it('registers all selection capabilities', function (): void {
    $schema = FieldSchema::multiChoice()
        ->key('multi')
        ->label('Multi')
        ->withValidationCapabilities(
            MinSelectionsCapability::class,
            MaxSelectionsCapability::class,
        );

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});

it('registers all file capabilities', function (): void {
    $schema = FieldSchema::text()
        ->key('file')
        ->label('File')
        ->withValidationCapabilities(
            AcceptedFileTypesCapability::class,
            MaxFileSizeCapability::class,
        );

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});

it('allows additive capability registration', function (): void {
    $schema = FieldSchema::text()
        ->key('text')
        ->label('Text')
        ->withValidationCapabilities(
            MinLengthCapability::class,
            MinLengthCapability::class,
        );

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});
