<?php

declare(strict_types=1);

use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\FieldTypeSystem\FieldTypeConfigurator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $this->section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['active' => true]);
});

function orphanedRowField(CustomFieldSection $section, string $code, string $type): CustomField
{
    return CustomField::factory()->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'code' => $code,
        'name' => ucfirst($code),
        'type' => $type,
    ]);
}

function disableFieldType(string $type): void
{
    config()->set('custom-fields.field_type_configuration', FieldTypeConfigurator::configure()->disabled([$type]));
    CustomFieldsType::clearResolvedInstance(FieldManager::class);
}

function storedValueRow(Post $post, CustomField $field): ?CustomFieldValue
{
    return CustomFieldValue::query()
        ->where('entity_id', $post->getKey())
        ->where('custom_field_id', $field->getKey())
        ->first();
}

it('reads null for the value of a field that has since been deactivated', function (): void {
    $field = orphanedRowField($this->section, 'headline', 'text');
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Launch day');

    $field->deactivate();

    $row = storedValueRow($post, $field);

    expect($row->customField)->toBeNull()
        ->and($row->getValue())->toBeNull();
});

it('refuses to set a value on a row whose field has been deactivated', function (): void {
    $field = orphanedRowField($this->section, 'headline', 'text');
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Launch day');

    $field->deactivate();

    expect(fn () => storedValueRow($post, $field)->setValue('Updated headline'))
        ->toThrow(RuntimeException::class, "Unable to set a value for custom field [{$field->getKey()}]");
});

it('keeps the stored value when a save targets a deactivated field', function (): void {
    $field = orphanedRowField($this->section, 'headline', 'text');
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Launch day');

    $field->deactivate();

    expect(fn () => $post->saveCustomFieldValue($field, 'Updated headline'))->toThrow(RuntimeException::class);

    $field->activate();

    expect(storedValueRow($post, $field)->getValue())->toBe('Launch day');
});

it('writes no row when a first save targets a deactivated field', function (): void {
    $field = orphanedRowField($this->section, 'headline', 'text');
    $post = Post::factory()->create();

    $field->deactivate();

    expect(fn () => $post->saveCustomFieldValue($field, 'Launch day'))->toThrow(RuntimeException::class)
        ->and(storedValueRow($post, $field))->toBeNull();
});

it('refuses to set a value on a field whose type has been disabled', function (): void {
    $field = orphanedRowField($this->section, 'notes', 'rich-editor');
    $post = Post::factory()->create();

    disableFieldType('rich-editor');

    expect(fn () => $post->saveCustomFieldValue($field->fresh(), 'some value'))
        ->toThrow(RuntimeException::class, 'unregistered field type [rich-editor]')
        ->and(storedValueRow($post, $field))->toBeNull();
});

it('answers false for every type check on a field whose type has been disabled', function (): void {
    $field = orphanedRowField($this->section, 'body', 'rich-editor');

    disableFieldType('rich-editor');

    $field = $field->fresh();

    expect($field->typeData)->toBeNull()
        ->and($field->isChoiceField())->toBeFalse()
        ->and($field->isMultiChoiceField())->toBeFalse()
        ->and($field->isDateField())->toBeFalse()
        ->and($field->isDateTimeField())->toBeFalse()
        ->and($field->isFilterable())->toBeFalse();
});

it('extracts visibility values for a field whose type has been disabled', function (): void {
    $field = orphanedRowField($this->section, 'notes', 'rich-editor');
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'some stored value');

    disableFieldType('rich-editor');

    $values = app(BackendVisibilityService::class)->extractFieldValues($post->fresh(), collect([$field->fresh()]));

    expect($values)->toHaveKey('notes');
});
