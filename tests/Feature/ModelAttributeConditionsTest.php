<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

beforeEach(function (): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
        ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
        ->enable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
    );

    $this->section = CustomFieldSection::factory()->create([
        'name' => 'Model Attr Test Section',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $this->coreLogic = app(CoreVisibilityLogicService::class);
    $this->backendService = app(BackendVisibilityService::class);
    $this->frontendService = app(FrontendVisibilityService::class);
});

afterEach(function (): void {
    BackendVisibilityService::clearCache();
});

describe('End-to-end model attribute visibility', function (): void {
    it('shows field when model attribute condition is met (show_when)', function (): void {
        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Published Details',
            'code' => 'published_details',
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
                ],
            ],
        ]);

        $post = Post::factory()->create(['is_published' => true]);

        $visibility = $this->coreLogic->getVisibilityData($field);

        expect($visibility)->toBeInstanceOf(VisibilityData::class)
            ->and($visibility->evaluate([], $post))->toBeTrue();

        // Opposite: hidden when not published
        $unpublishedPost = Post::factory()->create(['is_published' => false]);
        expect($visibility->evaluate([], $unpublishedPost))->toBeFalse();
    });

    it('hides field when model attribute condition is met (hide_when)', function (): void {
        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Draft Notes',
            'code' => 'draft_notes',
            'type' => 'text',
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::HIDE_WHEN,
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

        $visibility = $this->coreLogic->getVisibilityData($field);

        $publishedPost = Post::factory()->create(['is_published' => true]);
        expect($visibility->evaluate([], $publishedPost))->toBeFalse();

        $draftPost = Post::factory()->create(['is_published' => false]);
        expect($visibility->evaluate([], $draftPost))->toBeTrue();
    });

    it('evaluates ANY logic with model attribute conditions', function (): void {
        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Highlight',
            'code' => 'highlight',
            'type' => 'text',
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ANY,
                    'conditions' => [
                        [
                            'field_code' => 'is_published',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => true,
                            'source' => ConditionSource::ModelAttribute,
                        ],
                        [
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'Featured',
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $visibility = $this->coreLogic->getVisibilityData($field);

        // Published but not featured -> visible (ANY met)
        $post1 = Post::factory()->create(['is_published' => true, 'title' => 'Regular']);
        expect($visibility->evaluate([], $post1))->toBeTrue();

        // Not published but featured -> visible (ANY met)
        $post2 = Post::factory()->create(['is_published' => false, 'title' => 'Featured']);
        expect($visibility->evaluate([], $post2))->toBeTrue();

        // Neither published nor featured -> hidden
        $post3 = Post::factory()->create(['is_published' => false, 'title' => 'Regular']);
        expect($visibility->evaluate([], $post3))->toBeFalse();
    });
});

describe('Mixed custom field + model attribute conditions', function (): void {
    it('evaluates mixed conditions with ALL logic', function (): void {
        $triggerField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Status',
            'code' => 'status',
            'type' => 'text',
        ]);

        $mixedField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Mixed',
            'code' => 'mixed_field',
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

        $visibility = $this->coreLogic->getVisibilityData($mixedField);

        // Both conditions met -> visible
        $publishedPost = Post::factory()->create(['is_published' => true]);
        expect($visibility->evaluate(['status' => 'active'], $publishedPost))->toBeTrue();

        // Custom field met, model attribute not -> hidden
        expect($visibility->evaluate(['status' => 'active'], Post::factory()->create(['is_published' => false])))->toBeFalse();

        // Model attribute met, custom field not -> hidden
        expect($visibility->evaluate(['status' => 'inactive'], $publishedPost))->toBeFalse();
    });
});

describe('Feature flag enforcement', function (): void {
    it('ignores model attribute conditions when feature flag is off', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
            ->disable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
        );

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Flagged',
            'code' => 'flagged',
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
                ],
            ],
        ]);

        $visibility = $this->coreLogic->getVisibilityData($field);

        // With flag off, model attribute conditions are skipped
        // No conditions remain -> evaluates to true (visible)
        $unpublishedPost = Post::factory()->create(['is_published' => false]);
        expect($visibility->evaluate([], $unpublishedPost))->toBeTrue();
    });

    it('enforces model attribute conditions at frontend service level', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
            ->disable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
        );

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'FE Flagged',
            'code' => 'fe_flagged',
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
                ],
            ],
        ]);

        $fields = collect([$field]);
        $jsExpression = $this->frontendService->buildVisibilityExpression($field, $fields);

        // With flag off, should return null (no JS expression needed, field always visible)
        expect($jsExpression)->toBeNull();
    });

    it('handles mixed flag states: FIELD_CONDITIONAL_VISIBILITY on + MODEL_ATTRIBUTE_CONDITIONS off', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
            ->disable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
        );

        $triggerField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Status',
            'code' => 'status_mixed',
            'type' => 'text',
        ]);

        $mixedField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Mixed Flags',
            'code' => 'mixed_flags',
            'type' => 'text',
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'status_mixed',
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
                ],
            ],
        ]);

        $visibility = $this->coreLogic->getVisibilityData($mixedField);

        // Model attribute condition is skipped, only custom field condition evaluated
        $post = Post::factory()->create(['is_published' => false]);

        // status=active -> custom field condition met, model attr skipped -> visible
        expect($visibility->evaluate(['status_mixed' => 'active'], $post))->toBeTrue();

        // status=inactive -> custom field condition NOT met -> hidden
        expect($visibility->evaluate(['status_mixed' => 'inactive'], $post))->toBeFalse();
    });
});

describe('Create form fail-open', function (): void {
    it('shows field on create form when model attribute condition has no record', function (): void {
        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Create Safe',
            'code' => 'create_safe',
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
                ],
            ],
        ]);

        $visibility = $this->coreLogic->getVisibilityData($field);

        // null record -> fail-open -> visible
        expect($visibility->evaluate([], null))->toBeTrue();
    });

    it('shows field on create form with mixed conditions when no record', function (): void {
        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Create Mixed',
            'code' => 'create_mixed',
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

        $visibility = $this->coreLogic->getVisibilityData($field);

        // Custom field met + model attr fail-open -> visible
        expect($visibility->evaluate(['status' => 'active'], null))->toBeTrue();

        // Custom field NOT met + model attr fail-open -> hidden (ALL logic)
        expect($visibility->evaluate(['status' => 'inactive'], null))->toBeFalse();
    });
});

describe('Frontend JS generation for model attributes', function (): void {
    it('generates correct JS path for model attribute conditions', function (): void {
        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'JS Path Test',
            'code' => 'js_path_test',
            'type' => 'text',
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'Featured',
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $fields = collect([$field]);
        $jsExpression = $this->frontendService->buildVisibilityExpression($field, $fields);

        // Model attributes use $get('field_code') NOT $get('custom_fields.field_code')
        expect($jsExpression)->toBeString()
            ->and($jsExpression)->toContain("\$get('title')")
            ->and($jsExpression)->not->toContain('custom_fields.title');
    });

    it('generates correct JS for mixed custom field and model attribute conditions', function (): void {
        $triggerField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Trigger',
            'code' => 'trigger_code',
            'type' => 'text',
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'JS Mixed',
            'code' => 'js_mixed',
            'type' => 'text',
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'trigger_code',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'yes',
                            'source' => ConditionSource::CustomField,
                        ],
                        [
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::IS_NOT_EMPTY,
                            'value' => null,
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $fields = collect([$triggerField, $field]);
        $jsExpression = $this->frontendService->buildVisibilityExpression($field, $fields);

        expect($jsExpression)->toBeString()
            ->and($jsExpression)->toContain("\$get('custom_fields.trigger_code')")
            ->and($jsExpression)->toContain("\$get('title')");
    });
});

describe('getDependentFields excludes model attributes', function (): void {
    it('only returns custom field codes in dependency list', function (): void {
        $field = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Deps Test',
            'code' => 'deps_test',
            'type' => 'text',
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'some_custom_field',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'yes',
                            'source' => ConditionSource::CustomField,
                        ],
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

        $dependentFields = $this->coreLogic->getDependentFields($field);

        expect($dependentFields)->toBe(['some_custom_field'])
            ->and($dependentFields)->not->toContain('is_published');
    });
});
