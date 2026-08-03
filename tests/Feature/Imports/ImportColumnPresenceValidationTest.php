<?php

declare(strict_types=1);

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Facades\Validator;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\FieldTypeConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\ValidationService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    config()->set('custom-fields.field_type_configuration', FieldTypeConfigurator::configure()
        ->enabled([])
        ->disabled([])
        ->discover(true)
        ->cache(enabled: false));

    $this->section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create();
});

/**
 * Mirrors what Filament does per row: Importer::castData() casts the raw cell
 * (blank always becomes null), then Importer::validateData() validates the cast state.
 */
function validateImportedCell(CustomField $field, mixed $cell): Illuminate\Contracts\Validation\Validator
{
    $column = ImportColumn::make('custom_fields_'.$field->code)->label($field->name);

    app(ImportColumnConfigurator::class)->configure($column, $field);

    return Validator::make(
        ['value' => $column->castState($cell)],
        ['value' => $column->getDataValidationRules()],
    );
}

function makeImportableField(string $type, bool $required): CustomField
{
    return CustomField::factory()->create([
        'custom_field_section_id' => test()->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'imported_'.str_replace('-', '_', $type),
        'type' => $type,
        'validation_rules' => $required ? ['required' => true] : [],
    ]);
}

/**
 * @return array<int, string>
 */
function registeredFieldTypeKeys(): array
{
    return CustomFieldsType::toCollection()
        ->map(fn (mixed $fieldType): string => $fieldType->key)
        ->values()
        ->all();
}

it('accepts an empty mapped cell for every optional field type', function (): void {
    $rejected = [];

    foreach (registeredFieldTypeKeys() as $type) {
        $validator = validateImportedCell(makeImportableField($type, required: false), '');

        if ($validator->fails()) {
            $rejected[$type] = $validator->errors()->first();
        }
    }

    expect($rejected)->toBe([]);
});

it('rejects an empty mapped cell for every required field type', function (): void {
    $accepted = [];

    foreach (registeredFieldTypeKeys() as $type) {
        if (! validateImportedCell(makeImportableField($type, required: true), '')->fails()) {
            $accepted[] = $type;
        }
    }

    expect($accepted)->toBe([]);
});

it('accepts a filled cell for an optional field', function (string $type, string $cell): void {
    $validator = validateImportedCell(makeImportableField($type, required: false), $cell);

    expect($validator->fails())->toBeFalse(
        sprintf('[%s] optional field rejected "%s": %s', $type, $cell, $validator->errors()->first())
    );
})->with([
    'text' => ['text', 'Sample text'],
    'textarea' => ['textarea', 'Sample longer text'],
    'rich-editor' => ['rich-editor', '<p>Sample</p>'],
    'markdown-editor' => ['markdown-editor', '# Sample'],
    'number' => ['number', '42'],
    'currency' => ['currency', '10.50'],
    'date' => ['date', '2026-01-15'],
    'date-time' => ['date-time', '2026-01-15 10:00'],
    'checkbox' => ['checkbox', 'true'],
    'toggle' => ['toggle', 'yes'],
    'color-picker' => ['color-picker', '#ff0000'],
    'link' => ['link', 'https://example.com'],
    'tags-input' => ['tags-input', 'alpha, beta'],
]);

it('exposes the presence rule as the single source of truth', function (): void {
    $service = app(ValidationService::class);

    expect($service->getPresenceRule(makeImportableField('text', required: false)))->toBe('nullable')
        ->and($service->getPresenceRule(makeImportableField('textarea', required: true)))->toBe('required');
});

it('keeps value validation rules free of presence rules for callers that supply their own', function (): void {
    $service = app(ValidationService::class);

    expect($service->getValueValidationRules(makeImportableField('number', required: false)))
        ->not->toContain('nullable')
        ->not->toContain('required')
        ->and($service->getValueValidationRules(makeImportableField('date', required: true)))
        ->not->toContain('nullable')
        ->not->toContain('required');
});
