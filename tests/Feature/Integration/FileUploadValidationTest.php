<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\FieldTypeSystem\FieldTypeConfigurator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\ValidationService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    config()->set('custom-fields.field_type_configuration', FieldTypeConfigurator::configure()
        ->enabled([])
        ->disabled([])
        ->discover(true)
        ->cache(enabled: false));

    $this->section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create();
});

it('resolves FILE data type to string_value column', function (): void {
    $field = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'document',
        'type' => 'file-upload',
    ]);

    $column = CustomFieldValue::getValueColumn($field->type);

    expect($column)->toBe('string_value');
});

it('does not apply string database constraints to file upload fields', function (): void {
    $field = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'attachment',
        'type' => 'file-upload',
    ]);

    $service = app(ValidationService::class);
    $rules = $service->getValidationRules($field);

    expect($rules)
        ->toContain('file')
        ->not->toContain('string')
        ->not->toContain('max:255');
});

it('returns empty database validation rules for file types', function (): void {
    $service = app(ValidationService::class);
    $dbRules = $service->getDatabaseValidationRules('file-upload');

    expect($dbRules)->toBe([]);
});

it('has FILE case in FieldDataType enum', function (): void {
    $file = FieldDataType::FILE;

    expect($file->value)->toBe('file')
        ->and($file->isChoiceField())->toBeFalse()
        ->and($file->isMultiChoiceField())->toBeFalse();
});
