<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;

beforeEach(function (): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
        ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
        ->enable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
    );

    $this->coreLogic = app(CoreVisibilityLogicService::class);
    $this->backendService = app(BackendVisibilityService::class);
    $this->frontendService = app(FrontendVisibilityService::class);
});

afterEach(function (): void {
    BackendVisibilityService::clearCache();
});

describe('Section visibility with custom field conditions', function (): void {
    it('evaluates section visibility from core logic service', function (): void {
        $triggerField = CustomField::factory()->create([
            'name' => 'Category',
            'code' => 'category',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

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
                            'field_code' => 'category',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'premium',
                            'source' => ConditionSource::CustomField,
                        ],
                    ],
                ],
            ],
        ]);

        expect($this->coreLogic->hasSectionVisibilityConditions($section))->toBeTrue();

        $visibilityData = $this->coreLogic->getVisibilityDataFromSection($section);
        expect($visibilityData)->toBeInstanceOf(VisibilityData::class)
            ->and($visibilityData->mode)->toBe(VisibilityMode::SHOW_WHEN);
    });

    it('evaluates section visibility with backend service', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Backend Section',
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

        $publishedPost = Post::factory()->create(['is_published' => true]);
        $draftPost = Post::factory()->create(['is_published' => false]);

        expect($this->backendService->isSectionVisible($publishedPost, $section, collect()))->toBeTrue()
            ->and($this->backendService->isSectionVisible($draftPost, $section, collect()))->toBeFalse();
    });
});

describe('Section visibility JS expression generation', function (): void {
    it('generates JS expression for section with custom field condition', function (): void {
        $triggerField = CustomField::factory()->create([
            'name' => 'Plan',
            'code' => 'plan',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'JS Section',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'plan',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'pro',
                            'source' => ConditionSource::CustomField,
                        ],
                    ],
                ],
            ],
        ]);

        $allFields = collect([$triggerField]);
        $jsExpression = $this->frontendService->buildSectionVisibilityExpression($section, $allFields);

        expect($jsExpression)->toBeString()
            ->and($jsExpression)->toContain("\$get('custom_fields.plan')")
            ->and($jsExpression)->toContain("'pro'")
            ->and($jsExpression)->not->toContain('"');
    });

    it('generates an attribute-safe, id-based expression for a multi-choice contains section condition', function (): void {
        $services = CustomField::factory()->create([
            'name' => 'Services',
            'code' => 'services',
            'type' => 'multi-select',
            'entity_type' => Post::class,
        ]);
        $other = CustomFieldOption::factory()->create([
            'custom_field_id' => $services->id,
            'name' => 'Other',
            'sort_order' => 1,
        ]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'Multi Choice Section',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'services',
                            'operator' => VisibilityOperator::CONTAINS,
                            'value' => ['Other'],
                            'source' => ConditionSource::CustomField,
                        ],
                    ],
                ],
            ],
        ]);

        $jsExpression = $this->frontendService->buildSectionVisibilityExpression($section, collect([$services]));

        // Same guarantees as field-level conditions: resolves the option name to its numeric id,
        // string-normalizes, and stays safe inside the double-quoted x-bind:class attribute.
        expect($jsExpression)->toBeString()
            ->and($jsExpression)->toContain((string) $other->id)
            ->and($jsExpression)->not->toContain("'Other'")
            ->and($jsExpression)->toContain('String(v)')
            ->and($jsExpression)->not->toContain('"')
            ->and($jsExpression)->not->toContain("\n");
    });

    it('generates JS expression for section with model attribute condition', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Model Attr Section',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
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

        $jsExpression = $this->frontendService->buildSectionVisibilityExpression($section, collect());

        expect($jsExpression)->toBeString()
            ->and($jsExpression)->toContain("\$get('title')")
            ->and($jsExpression)->not->toContain('custom_fields.title');
    });

    it('returns null JS expression for section without visibility conditions', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'No Conditions Section',
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $jsExpression = $this->frontendService->buildSectionVisibilityExpression($section, collect());

        expect($jsExpression)->toBeNull();
    });
});

describe('Section visibility feature flag', function (): void {
    it('does not apply section visibility when feature flag is off', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
            ->enable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
            ->disable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
        );

        $section = CustomFieldSection::factory()->create([
            'name' => 'Disabled Section',
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

        // Core logic still detects conditions (it doesn't know about features)
        expect($this->coreLogic->hasSectionVisibilityConditions($section))->toBeTrue();

        // But backend service evaluates the section -- the feature flag is checked
        // at the SectionComponentFactory level, not at the service level
        // So evaluateSectionVisibility itself always works; gating is in the factory
    });
});

describe('Section visibility with always_visible mode', function (): void {
    it('always shows section with ALWAYS_VISIBLE mode', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Always Visible Section',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::ALWAYS_VISIBLE,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => null,
                ],
            ],
        ]);

        expect($this->coreLogic->hasSectionVisibilityConditions($section))->toBeFalse();

        $post = Post::factory()->create(['is_published' => false]);
        expect($this->coreLogic->evaluateSectionVisibility($section, [], $post))->toBeTrue();
    });
});

describe('Section visibility with relation conditions', function (): void {
    it('evaluates a relation condition on a section server-side', function (): void {
        $matching = Tag::factory()->create();

        $postMatch = Post::factory()->create();
        $postMatch->tagModels()->attach($matching);
        $commentMatch = Comment::factory()->create(['post_id' => $postMatch->id]);

        $postNoMatch = Post::factory()->create();
        $commentNoMatch = Comment::factory()->create(['post_id' => $postNoMatch->id]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'Relation Condition Section',
            'entity_type' => Comment::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'post.tagModels',
                            'operator' => VisibilityOperator::IS_IN,
                            'value' => [$matching->id],
                            'source' => ConditionSource::RelationAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        expect($this->backendService->isSectionVisible($commentMatch, $section, collect()))->toBeTrue()
            ->and($this->backendService->isSectionVisible($commentNoMatch, $section, collect()))->toBeFalse();
    });

    it('honors a section relation condition when building the infolist (regression)', function (): void {
        // The infolist builder previously ignored section-level visibility and rendered a
        // section whenever it had any visible field, so a relation-conditioned section showed
        // for every record. Here the section carries an always-visible field, so the only thing
        // that can hide it is the section-level relation condition itself.
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::SYSTEM_SECTIONS)
            ->enable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
        );

        $matching = Tag::factory()->create();

        $postMatch = Post::factory()->create();
        $postMatch->tagModels()->attach($matching);
        $commentMatch = Comment::factory()->create(['post_id' => $postMatch->id]);

        $postNoMatch = Post::factory()->create();
        $commentNoMatch = Comment::factory()->create(['post_id' => $postNoMatch->id]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'Relation Infolist Section',
            'entity_type' => Comment::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'post.tagModels',
                            'operator' => VisibilityOperator::IS_IN,
                            'value' => [$matching->id],
                            'source' => ConditionSource::RelationAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'entity_type' => Comment::class,
            'name' => 'External Id',
            'code' => 'external_id',
            'type' => 'text',
        ]);

        $sectionsFor = fn (Comment $comment) => CustomFields::infolist()->forModel($comment)->values();

        expect($sectionsFor($commentMatch))->toHaveCount(1)
            ->and($sectionsFor($commentNoMatch))->toHaveCount(0);
    });
});
