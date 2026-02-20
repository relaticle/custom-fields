<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\ModelAttributeDiscoveryService;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

// ConditionSource enum tests

test('ConditionSource enum has correct cases and values', function (): void {
    expect(ConditionSource::CustomField->value)->toBe('custom_field')
        ->and(ConditionSource::ModelAttribute->value)->toBe('model_attribute')
        ->and(ConditionSource::CustomField->getLabel())->toBe('Custom Field')
        ->and(ConditionSource::ModelAttribute->getLabel())->toBe('Model Attribute');
});

// VisibilityConditionData backward compatibility

test('VisibilityConditionData defaults source to CustomField when not provided', function (): void {
    $condition = VisibilityConditionData::from([
        'field_code' => 'status',
        'operator' => VisibilityOperator::EQUALS,
        'value' => 'active',
    ]);

    expect($condition->source)->toBe(ConditionSource::CustomField)
        ->and($condition->isCustomField())->toBeTrue()
        ->and($condition->isModelAttribute())->toBeFalse();
});

test('VisibilityConditionData correctly identifies model attribute source', function (): void {
    $condition = VisibilityConditionData::from([
        'field_code' => 'type',
        'operator' => VisibilityOperator::EQUALS,
        'value' => 'physical',
        'source' => ConditionSource::ModelAttribute,
    ]);

    expect($condition->source)->toBe(ConditionSource::ModelAttribute)
        ->and($condition->isModelAttribute())->toBeTrue()
        ->and($condition->isCustomField())->toBeFalse();
});

// ModelAttributeDiscoveryService tests

test('ModelAttributeDiscoveryService discovers Post model attributes', function (): void {
    $service = app(ModelAttributeDiscoveryService::class);
    $attributes = $service->getAttributes(Post::class);

    expect($attributes)->not->toBeEmpty();

    $names = $attributes->pluck('name')->toArray();

    // Should include regular columns
    expect($names)->toContain('title')
        ->toContain('content')
        ->toContain('is_published')
        ->toContain('rating');

    // Should exclude system columns
    expect($names)->not->toContain('id')
        ->not->toContain('created_at')
        ->not->toContain('updated_at')
        ->not->toContain('deleted_at');
});

test('ModelAttributeDiscoveryService maps column types correctly', function (): void {
    $service = app(ModelAttributeDiscoveryService::class);
    $attributes = $service->getAttributes(Post::class);

    $byName = $attributes->keyBy('name');

    // String column
    expect($byName->get('title')['data_type'])->toBe(FieldDataType::STRING);

    // Boolean column
    expect($byName->get('is_published')['data_type'])->toBe(FieldDataType::BOOLEAN);
});

test('ModelAttributeDiscoveryService excludes array/json cast columns', function (): void {
    $service = app(ModelAttributeDiscoveryService::class);
    $attributes = $service->getAttributes(Post::class);
    $names = $attributes->pluck('name')->toArray();

    // Columns with array/json casts should be excluded
    expect($names)->not->toContain('tags')
        ->not->toContain('json_array_of_objects');
});

test('ModelAttributeDiscoveryService returns options format', function (): void {
    $service = app(ModelAttributeDiscoveryService::class);
    $options = $service->getAttributeOptions(Post::class);

    expect($options)->toBeArray()
        ->toHaveKey('title')
        ->and($options['title'])->toBe('Title');
});

test('ModelAttributeDiscoveryService caches results', function (): void {
    $service = app(ModelAttributeDiscoveryService::class);

    $first = $service->getAttributes(Post::class);
    $second = $service->getAttributes(Post::class);

    expect($first)->toBe($second);

    ModelAttributeDiscoveryService::clearCache();
});

test('ModelAttributeDiscoveryService returns empty for invalid entity type', function (): void {
    $service = app(ModelAttributeDiscoveryService::class);
    $attributes = $service->getAttributes('NonExistentModel');

    expect($attributes)->toBeEmpty();
});

test('ModelAttributeDiscoveryService gets specific attribute data type', function (): void {
    $service = app(ModelAttributeDiscoveryService::class);

    expect($service->getAttributeDataType(Post::class, 'title'))->toBe(FieldDataType::STRING)
        ->and($service->getAttributeDataType(Post::class, 'is_published'))->toBe(FieldDataType::BOOLEAN)
        ->and($service->getAttributeDataType(Post::class, 'nonexistent'))->toBeNull();
});

// VisibilityData evaluate with model record

test('VisibilityData evaluates model attribute conditions using record', function (): void {
    $visibility = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [
            [
                'field_code' => 'title',
                'operator' => VisibilityOperator::EQUALS,
                'value' => 'Test Post',
                'source' => ConditionSource::ModelAttribute,
            ],
        ],
    ]);

    $post = Post::factory()->create(['title' => 'Test Post']);

    // Should be visible because title matches
    expect($visibility->evaluate([], $post))->toBeTrue();

    $otherPost = Post::factory()->create(['title' => 'Other Post']);

    // Should be hidden because title doesn't match
    expect($visibility->evaluate([], $otherPost))->toBeFalse();
});

test('VisibilityData evaluates mixed conditions (custom field + model attribute)', function (): void {
    $visibility = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [
            [
                'field_code' => 'status',
                'operator' => VisibilityOperator::EQUALS,
                'value' => 'active',
                'source' => ConditionSource::CustomField,
            ],
            [
                'field_code' => 'is_published',
                'operator' => VisibilityOperator::EQUALS,
                'value' => true,
                'source' => ConditionSource::ModelAttribute,
            ],
        ],
    ]);

    $publishedPost = Post::factory()->create(['is_published' => true]);

    // Both conditions met
    expect($visibility->evaluate(['status' => 'active'], $publishedPost))->toBeTrue();

    // Custom field condition not met
    expect($visibility->evaluate(['status' => 'inactive'], $publishedPost))->toBeFalse();

    // Model attribute condition not met
    $unpublishedPost = Post::factory()->create(['is_published' => false]);
    expect($visibility->evaluate(['status' => 'active'], $unpublishedPost))->toBeFalse();
});

test('VisibilityData getDependentFields excludes model attribute conditions', function (): void {
    $visibility = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [
            [
                'field_code' => 'status',
                'operator' => VisibilityOperator::EQUALS,
                'value' => 'active',
                'source' => ConditionSource::CustomField,
            ],
            [
                'field_code' => 'title',
                'operator' => VisibilityOperator::IS_NOT_EMPTY,
                'value' => null,
                'source' => ConditionSource::ModelAttribute,
            ],
        ],
    ]);

    $dependentFields = $visibility->getDependentFields();

    // Only custom field dependencies should be returned
    expect($dependentFields)->toBe(['status'])
        ->not->toContain('title');
});

test('VisibilityData hasModelAttributeConditions detects model attribute conditions', function (): void {
    $withModelAttr = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [
            [
                'field_code' => 'title',
                'operator' => VisibilityOperator::EQUALS,
                'value' => 'Test',
                'source' => ConditionSource::ModelAttribute,
            ],
        ],
    ]);

    $withoutModelAttr = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [
            [
                'field_code' => 'status',
                'operator' => VisibilityOperator::EQUALS,
                'value' => 'active',
                'source' => ConditionSource::CustomField,
            ],
        ],
    ]);

    expect($withModelAttr->hasModelAttributeConditions())->toBeTrue()
        ->and($withoutModelAttr->hasModelAttributeConditions())->toBeFalse();
});

// CoreVisibilityLogicService with model attribute conditions

test('CoreVisibilityLogicService evaluates visibility with model record', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Test Section',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $field = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Warranty Info',
        'code' => 'warranty_info',
        'type' => 'text',
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [
                    [
                        'field_code' => 'is_published',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => true,
                        'source' => ConditionSource::ModelAttribute,
                    ],
                ],
                'always_save' => false,
            ],
        ],
    ]);

    $coreLogic = app(CoreVisibilityLogicService::class);
    $publishedPost = Post::factory()->create(['is_published' => true]);
    $unpublishedPost = Post::factory()->create(['is_published' => false]);

    expect($coreLogic->evaluateVisibility($field, [], $publishedPost))->toBeTrue()
        ->and($coreLogic->evaluateVisibility($field, [], $unpublishedPost))->toBeFalse();
});

// Section visibility tests

test('CoreVisibilityLogicService handles section visibility conditions', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Conditional Section',
        'entity_type' => Post::class,
        'active' => true,
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [
                    [
                        'field_code' => 'status',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => 'premium',
                        'source' => ConditionSource::CustomField,
                    ],
                ],
            ],
        ],
    ]);

    $coreLogic = app(CoreVisibilityLogicService::class);

    expect($coreLogic->hasSectionVisibilityConditions($section))->toBeTrue()
        ->and($coreLogic->evaluateSectionVisibility($section, ['status' => 'premium']))->toBeTrue()
        ->and($coreLogic->evaluateSectionVisibility($section, ['status' => 'basic']))->toBeFalse();
});

test('section without visibility conditions is always visible', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Always Visible Section',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $coreLogic = app(CoreVisibilityLogicService::class);

    expect($coreLogic->hasSectionVisibilityConditions($section))->toBeFalse()
        ->and($coreLogic->evaluateSectionVisibility($section, []))->toBeTrue();
});

test('section visibility with model attribute conditions', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Published Only Section',
        'entity_type' => Post::class,
        'active' => true,
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [
                    [
                        'field_code' => 'is_published',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => true,
                        'source' => ConditionSource::ModelAttribute,
                    ],
                ],
            ],
        ],
    ]);

    $coreLogic = app(CoreVisibilityLogicService::class);
    $publishedPost = Post::factory()->create(['is_published' => true]);
    $unpublishedPost = Post::factory()->create(['is_published' => false]);

    expect($coreLogic->evaluateSectionVisibility($section, [], $publishedPost))->toBeTrue()
        ->and($coreLogic->evaluateSectionVisibility($section, [], $unpublishedPost))->toBeFalse();
});

// Backend visibility service section tests

test('BackendVisibilityService evaluates section visibility', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Conditional Section',
        'entity_type' => Post::class,
        'active' => true,
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [
                    [
                        'field_code' => 'is_published',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => true,
                        'source' => ConditionSource::ModelAttribute,
                    ],
                ],
            ],
        ],
    ]);

    $field = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Test Field',
        'code' => 'test_field',
        'type' => 'text',
    ]);

    $backendService = app(BackendVisibilityService::class);
    $publishedPost = Post::factory()->create(['is_published' => true]);
    $unpublishedPost = Post::factory()->create(['is_published' => false]);

    expect($backendService->isSectionVisible($publishedPost, $section, collect([$field])))->toBeTrue()
        ->and($backendService->isSectionVisible($unpublishedPost, $section, collect([$field])))->toBeFalse();
});

// Frontend visibility service tests

test('FrontendVisibilityService generates correct JS for model attribute conditions', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Test Section',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $triggerField = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Status',
        'code' => 'status',
        'type' => 'select',
    ]);

    $conditionalField = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Model Attr Field',
        'code' => 'model_attr_field',
        'type' => 'text',
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [
                    [
                        'field_code' => 'title',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => 'Test',
                        'source' => ConditionSource::ModelAttribute,
                    ],
                ],
                'always_save' => false,
            ],
        ],
    ]);

    $frontendService = app(FrontendVisibilityService::class);
    $allFields = collect([$triggerField, $conditionalField]);

    $jsExpression = $frontendService->buildVisibilityExpression($conditionalField, $allFields);

    // Should use $get('title') for model attribute, not $get('custom_fields.title')
    expect($jsExpression)->toBeString()
        ->toContain("\$get('title')")
        ->not->toContain('custom_fields.title');
});

test('FrontendVisibilityService generates correct JS for custom field conditions', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Test Section',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $triggerField = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Status',
        'code' => 'status',
        'type' => 'select',
    ]);

    $conditionalField = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Custom Field Cond',
        'code' => 'custom_cond',
        'type' => 'text',
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [
                    [
                        'field_code' => 'status',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => 'active',
                        'source' => ConditionSource::CustomField,
                    ],
                ],
                'always_save' => false,
            ],
        ],
    ]);

    $frontendService = app(FrontendVisibilityService::class);
    $allFields = collect([$triggerField, $conditionalField]);

    $jsExpression = $frontendService->buildVisibilityExpression($conditionalField, $allFields);

    // Should use $get('custom_fields.status') for custom field source
    expect($jsExpression)->toBeString()
        ->toContain("\$get('custom_fields.status')");
});

test('FrontendVisibilityService builds section visibility expression', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Conditional Section',
        'entity_type' => Post::class,
        'active' => true,
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [
                    [
                        'field_code' => 'is_published',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => true,
                        'source' => ConditionSource::ModelAttribute,
                    ],
                ],
            ],
        ],
    ]);

    $frontendService = app(FrontendVisibilityService::class);
    $jsExpression = $frontendService->buildSectionVisibilityExpression($section, collect());

    expect($jsExpression)->toBeString()
        ->toContain("\$get('is_published')");
});

test('FrontendVisibilityService returns null for section without visibility', function (): void {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Always Visible',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $frontendService = app(FrontendVisibilityService::class);
    $jsExpression = $frontendService->buildSectionVisibilityExpression($section, collect());

    expect($jsExpression)->toBeNull();
});

// Fail-open behavior on create (no record)

test('model attribute conditions fail open when no record provided', function (): void {
    $visibility = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [
            [
                'field_code' => 'title',
                'operator' => VisibilityOperator::EQUALS,
                'value' => 'Test',
                'source' => ConditionSource::ModelAttribute,
            ],
        ],
    ]);

    // Without record, model attribute resolves to null
    // EQUALS 'Test' with null field value = false
    // This means the field would be hidden on create - the "fail-open" behavior
    // is handled at the AbstractFormComponent level, not in VisibilityData
    expect($visibility->evaluate([], null))->toBeFalse();
});

// Existing tests still work (backward compatibility)

test('existing custom field only conditions still work without source', function (): void {
    $visibility = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [
            [
                'field_code' => 'status',
                'operator' => VisibilityOperator::EQUALS,
                'value' => 'active',
                // No source - should default to CustomField
            ],
        ],
    ]);

    expect($visibility->evaluate(['status' => 'active']))->toBeTrue()
        ->and($visibility->evaluate(['status' => 'inactive']))->toBeFalse()
        ->and($visibility->getDependentFields())->toBe(['status'])
        ->and($visibility->hasModelAttributeConditions())->toBeFalse();
});
