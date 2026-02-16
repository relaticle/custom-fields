<?php

declare(strict_types=1);

use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;

it('registers capabilities via fluent methods', function (): void {
    $schema = FieldSchema::date()
        ->key('date')
        ->label('Date')
        ->canHaveMinDate()
        ->canHaveMaxDate();

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});

it('registers custom capabilities via withValidationCapability', function (): void {
    $schema = FieldSchema::text()
        ->key('custom')
        ->label('Custom')
        ->withValidationCapability('App\\CustomCapability');

    expect($schema->getValidationCapabilities())->toHaveCount(1)
        ->and($schema->getValidationCapabilities()[0])->toBe('App\\CustomCapability');
});

it('carries capabilities through to FieldTypeData', function (): void {
    $schema = FieldSchema::text()
        ->key('text')
        ->label('Text')
        ->canHaveMinLength()
        ->canHaveMaxLength();

    $data = $schema->data();

    expect($data->validationCapabilities)->toHaveCount(2);
});

it('registers all numeric capabilities', function (): void {
    $schema = FieldSchema::numeric()
        ->key('number')
        ->label('Number')
        ->canHaveMinValue()
        ->canHaveMaxValue()
        ->canBeIntegerOnly()
        ->canHaveDecimalPlaces();

    expect($schema->getValidationCapabilities())->toHaveCount(4);
});

it('registers all selection capabilities', function (): void {
    $schema = FieldSchema::multiChoice()
        ->key('multi')
        ->label('Multi')
        ->canHaveMinSelections()
        ->canHaveMaxSelections();

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});

it('registers all file capabilities', function (): void {
    $schema = FieldSchema::text()
        ->key('file')
        ->label('File')
        ->canHaveAcceptedFileTypes()
        ->canHaveMaxFileSize();

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});

it('allows additive capability registration', function (): void {
    $schema = FieldSchema::text()
        ->key('text')
        ->label('Text')
        ->canHaveMinLength()
        ->canHaveMinLength();

    expect($schema->getValidationCapabilities())->toHaveCount(2);
});
