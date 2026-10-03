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

/**
 * Regression coverage for https://github.com/relaticle/custom-fields/issues/227
 *
 * Both bugs share a root cause: a CustomFieldValue row (or the field it
 * points at) outlives the state Blueprint's builders assume. The builders
 * never hit this because they always filter to active/registered fields
 * first -- but any consumer reaching a value row or field directly does
 * not get that protection.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $this->section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['active' => true]);
});

it('does not fatal reading the value of a field that has since been deactivated', function (): void {
    $field = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'headline',
        'name' => 'Headline',
        'type' => 'text',
    ]);
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Launch day');

    $field->deactivate();

    // Load the value row the same way a consumer walking
    // $record->customFieldValues directly would -- not through a builder
    // that would have already filtered the deactivated field out. The
    // `customField` BelongsTo carries CustomField's own active-only scope,
    // so it resolves to null once the field is deactivated.
    $row = CustomFieldValue::query()
        ->where('entity_id', $post->getKey())
        ->where('custom_field_id', $field->getKey())
        ->firstOrFail();

    expect($row->customField)->toBeNull()
        ->and($row->getValue())->toBeNull();

    // setValue() has nowhere safe to write without knowing the column,
    // so it's a no-op rather than a fatal.
    $row->setValue('Updated headline');
    expect($row->getValue())->toBeNull();
});

it('does not fatal checking isChoiceField and friends on a field whose type has been disabled', function (): void {
    $field = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'body',
        'name' => 'Body',
        'type' => 'rich-editor',
    ]);

    // Disable the field's type after it was created -- the same
    // situation the issue hit with `rich-editor` in config/custom-fields.php.
    // CustomFieldsType caches its resolved FieldManager on the Facade's own
    // resolvedInstance cache (separate from the container's instance cache,
    // so forgetInstance() alone would not be enough), so the config change
    // only takes effect once that resolved instance is cleared.
    config()->set('custom-fields.field_type_configuration', FieldTypeConfigurator::configure()
        ->disabled(['rich-editor']));
    CustomFieldsType::clearResolvedInstance(FieldManager::class);

    $field = $field->fresh();

    expect($field->typeData)->toBeNull()
        ->and($field->isChoiceField())->toBeFalse()
        ->and($field->isMultiChoiceField())->toBeFalse()
        ->and($field->isDateField())->toBeFalse()
        ->and($field->isDateTimeField())->toBeFalse()
        ->and($field->isFilterable())->toBeFalse();
});

it('does not fatal extracting visibility values for a field whose type has been disabled', function (): void {
    $field = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'notes',
        'name' => 'Notes',
        'type' => 'rich-editor',
    ]);
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'some stored value');

    config()->set('custom-fields.field_type_configuration', FieldTypeConfigurator::configure()
        ->disabled(['rich-editor']));
    CustomFieldsType::clearResolvedInstance(FieldManager::class);

    $post = $post->fresh();
    $field = $field->fresh();

    $values = app(BackendVisibilityService::class)->extractFieldValues($post, collect([$field]));

    expect($values)->toHaveKey('notes');
});
