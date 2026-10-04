<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Relaticle\CustomFields\CustomFields as CustomFieldsConfig;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Tests\Fixtures\Models\CountingCustomFieldValue;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Spatie\LaravelData\DataCollection;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create(['active' => true]);
});

it('resolves each custom field value once per infolist build', function (): void {
    $post = Post::factory()->create();

    collect(range(1, 10))->each(function (int $i) use ($post): void {
        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => Post::class,
            'code' => "field_{$i}",
            'name' => "Field {$i}",
            'type' => 'text',
        ]);

        $post->saveCustomFieldValue($field, "value {$i}");
    });

    CustomFieldsConfig::useValueModel(CountingCustomFieldValue::class);
    CountingCustomFieldValue::$resolutions = 0;

    try {
        CustomFields::infolist()->forModel(Post::query()->findOrFail($post->getKey()))->values();

        expect(CountingCustomFieldValue::$resolutions)->toBe(10);
    } finally {
        CustomFieldsConfig::useValueModel(CustomFieldValue::class);
    }
});

it('re-evaluates visibility once the values relation is reloaded', function (): void {
    $status = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'status',
        'name' => 'Status',
        'type' => 'text',
    ]);

    $priority = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'priority',
        'name' => 'Priority',
        'type' => 'text',
        'settings' => new CustomFieldSettingsData(
            visibility: new VisibilityData(
                mode: VisibilityMode::SHOW_WHEN,
                logic: VisibilityLogic::ALL,
                conditions: new DataCollection(VisibilityConditionData::class, [
                    new VisibilityConditionData(
                        field_code: 'status',
                        operator: VisibilityOperator::EQUALS,
                        value: 'published',
                    ),
                ]),
            ),
        ),
    ]);

    $post = Post::factory()->create();
    $post->saveCustomFieldValue($status, 'draft');

    $fields = collect([$status, $priority]);
    $visibility = resolve(BackendVisibilityService::class);

    expect($visibility->isFieldVisible($post, $priority, $fields))->toBeFalse();

    $post->saveCustomFieldValue($status, 'published');
    $post->load('customFieldValues.customField');

    expect($visibility->isFieldVisible($post, $priority, $fields))->toBeTrue();
});

it('builds the field type registry once', function (): void {
    expect(CustomFieldsType::toCollection())->toBe(CustomFieldsType::toCollection())
        ->and(CustomFieldsType::getFieldType('text'))->toBe(CustomFieldsType::getFieldType('text'));
});
