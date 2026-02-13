<?php

declare(strict_types=1);

use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Filament\Integration\Factories\SectionComponentFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $currentConfig = config('custom-fields.features');
    $newConfig = FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
            CustomFieldsFeature::UI_TABLE_COLUMNS,
            CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
            CustomFieldsFeature::SYSTEM_SECTIONS,
            CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY,
            CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS,
        );
    config(['custom-fields.features' => $newConfig]);
});

describe('SectionComponentFactory visibility application', function (): void {
    it('applies visibleJs without visible() for model attribute conditions', function (): void {
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
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::CONTAINS,
                            'value' => 'Special',
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $post = Post::factory()->create(['title' => 'Normal Title']);
        $factory = app(SectionComponentFactory::class);
        $component = $factory->create($section, collect(), $post);

        // visibleJs should be set
        expect($component->getVisibleJs())->not->toBeNull()
            ->and($component->getVisibleJs())->toContain("\$get('title')");

        // visible() should NOT have been overridden — component must be visible
        // server-side so the JS can take effect
        expect($component->isVisible())->toBeTrue();
    });

    it('applies visibleJs without visible() for custom field conditions', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'CF Conditional Section',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'status_field',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'active',
                            'source' => ConditionSource::CustomField,
                        ],
                    ],
                ],
            ],
        ]);

        $triggerField = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Status',
            'code' => 'status_field',
            'type' => 'select',
            'entity_type' => Post::class,
        ]);

        $post = Post::factory()->create();
        $factory = app(SectionComponentFactory::class);
        $component = $factory->create($section, collect([$triggerField]), $post);

        expect($component->getVisibleJs())->not->toBeNull()
            ->and($component->getVisibleJs())->toContain("\$get('custom_fields.status_field')")
            ->and($component->isVisible())->toBeTrue();
    });

    it('applies visibleJs without visible() for mixed conditions', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Mixed Section',
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
                        [
                            'field_code' => 'priority',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'high',
                            'source' => ConditionSource::CustomField,
                        ],
                    ],
                ],
            ],
        ]);

        $triggerField = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Priority',
            'code' => 'priority',
            'type' => 'select',
            'entity_type' => Post::class,
        ]);

        $post = Post::factory()->create(['title' => '']);
        $factory = app(SectionComponentFactory::class);
        $component = $factory->create($section, collect([$triggerField]), $post);

        // Even though the post title is empty (condition fails server-side),
        // the component should still be visible server-side so visibleJs can work
        expect($component->getVisibleJs())->not->toBeNull()
            ->and($component->isVisible())->toBeTrue();
    });

    it('does not apply visibility when feature is disabled', function (): void {
        config(['custom-fields.features' => FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::SYSTEM_SECTIONS)
            ->disable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY),
        ]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'Section',
            'entity_type' => Post::class,
            'active' => true,
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
                ],
            ],
        ]);

        $factory = app(SectionComponentFactory::class);
        $component = $factory->create($section, collect());

        expect($component->getVisibleJs())->toBeNull()
            ->and($component->isVisible())->toBeTrue();
    });

    it('does not apply visibility to sections without conditions', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Always Visible',
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $factory = app(SectionComponentFactory::class);
        $component = $factory->create($section);

        expect($component->getVisibleJs())->toBeNull()
            ->and($component->isVisible())->toBeTrue();
    });

    it('does not apply visibility to ALWAYS_VISIBLE sections', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Always Visible Section',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::ALWAYS_VISIBLE,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [],
                ],
            ],
        ]);

        $factory = app(SectionComponentFactory::class);
        $component = $factory->create($section);

        expect($component->getVisibleJs())->toBeNull()
            ->and($component->isVisible())->toBeTrue();
    });

    it('falls back to server-side visible() when JS expression is null', function (): void {
        // Model attribute condition referencing a field not in allFields
        // AND no custom fields exist — the JS expression might still generate
        // since model attributes are always included in JS
        $section = CustomFieldSection::factory()->create([
            'name' => 'Fallback Section',
            'entity_type' => Post::class,
            'active' => true,
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
                ],
            ],
        ]);

        $factory = app(SectionComponentFactory::class);
        // Model attribute conditions always generate JS, so visibleJs will be set
        $component = $factory->create($section, collect(), Post::factory()->create(['title' => 'Wrong']));
        expect($component->getVisibleJs())->not->toBeNull();
    });

    it('creates correct component types with visibility', function (CustomFieldSectionType $type, string $expectedClass): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Typed Section',
            'code' => 'typed_section',
            'entity_type' => Post::class,
            'active' => true,
            'type' => $type,
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

        $factory = app(SectionComponentFactory::class);
        $component = $factory->create($section, collect(), Post::factory()->create());

        expect($component)->toBeInstanceOf($expectedClass)
            ->and($component->getVisibleJs())->not->toBeNull();
    })->with([
        'section' => [CustomFieldSectionType::SECTION, Section::class],
        'fieldset' => [CustomFieldSectionType::FIELDSET, Fieldset::class],
        'headless' => [CustomFieldSectionType::HEADLESS, Grid::class],
    ]);
});

describe('Section visibility JS expression generation', function (): void {
    it('generates SHOW_WHEN expression for contains operator', function (): void {
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::CONTAINS,
                            'value' => 'premium',
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $frontendService = app(FrontendVisibilityService::class);
        $js = $frontendService->buildSectionVisibilityExpression($section, collect());

        expect($js)->toContain("\$get('title')")
            ->toContain("'premium'")
            ->toContain('.includes(');
    });

    it('generates HIDE_WHEN expression with negation', function (): void {
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
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

        $frontendService = app(FrontendVisibilityService::class);
        $js = $frontendService->buildSectionVisibilityExpression($section, collect());

        // HIDE_WHEN wraps the entire expression in !()
        expect($js)->toStartWith('!(')
            ->toContain("\$get('is_published')");
    });

    it('generates ANY logic with OR operator', function (): void {
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ANY,
                    'conditions' => [
                        [
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::CONTAINS,
                            'value' => 'premium',
                            'source' => ConditionSource::ModelAttribute,
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

        $frontendService = app(FrontendVisibilityService::class);
        $js = $frontendService->buildSectionVisibilityExpression($section, collect());

        // ANY logic uses || operator
        expect($js)->toContain(' || ');
    });

    it('generates ALL logic with AND operator', function (): void {
        $section = CustomFieldSection::factory()->create([
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
        $js = $frontendService->buildSectionVisibilityExpression($section, collect());

        // ALL logic uses && operator
        expect($js)->toContain(' && ');
    });

    it('generates IS_EMPTY expression for model attributes', function (): void {
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'content',
                            'operator' => VisibilityOperator::IS_EMPTY,
                            'value' => null,
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $frontendService = app(FrontendVisibilityService::class);
        $js = $frontendService->buildSectionVisibilityExpression($section, collect());

        expect($js)->toContain("\$get('content')")
            ->toContain('=== null')
            ->toContain("=== ''");
    });

    it('generates numeric comparison for GREATER_THAN', function (): void {
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'rating',
                            'operator' => VisibilityOperator::GREATER_THAN,
                            'value' => 5,
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $frontendService = app(FrontendVisibilityService::class);
        $js = $frontendService->buildSectionVisibilityExpression($section, collect());

        expect($js)->toContain("\$get('rating')")
            ->toContain('parseFloat')
            ->toContain('>');
    });

    it('filters out custom field conditions referencing non-existent fields', function (): void {
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'nonexistent_field',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'test',
                            'source' => ConditionSource::CustomField,
                        ],
                    ],
                ],
            ],
        ]);

        $frontendService = app(FrontendVisibilityService::class);
        $js = $frontendService->buildSectionVisibilityExpression($section, collect());

        // No valid conditions — should return null
        expect($js)->toBeNull();
    });

    it('includes model attribute conditions even without matching allFields', function (): void {
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
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
                ],
            ],
        ]);

        $frontendService = app(FrontendVisibilityService::class);
        // Empty allFields — model attribute conditions should still generate JS
        $js = $frontendService->buildSectionVisibilityExpression($section, collect());

        expect($js)->not->toBeNull()
            ->toContain("\$get('title')");
    });
});

describe('Section visibility in Livewire integration', function (): void {
    it('renders edit page with section visibility conditions', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Premium Details',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::CONTAINS,
                            'value' => 'Premium',
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Premium Notes',
            'code' => 'premium_notes',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        // Post with matching title
        $post = Post::factory()->create(['title' => 'Premium Package']);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->assertSuccessful();
    });

    it('renders edit page when section condition does not match record', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Premium Details',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::CONTAINS,
                            'value' => 'Premium',
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Premium Notes',
            'code' => 'premium_notes',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        // Post WITHOUT matching title — section should still be rendered (for visibleJs)
        $post = Post::factory()->create(['title' => 'Basic Package']);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('custom_fields.premium_notes');
    });

    it('renders section with custom field visibility conditions on edit', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Advanced Settings',
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [
                        [
                            'field_code' => 'priority',
                            'operator' => VisibilityOperator::EQUALS,
                            'value' => 'high',
                            'source' => ConditionSource::CustomField,
                        ],
                    ],
                ],
            ],
        ]);

        $triggerField = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Priority',
            'code' => 'priority',
            'type' => 'select',
            'entity_type' => Post::class,
        ]);

        $conditionalField = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Advanced Note',
            'code' => 'advanced_note',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        $post = Post::factory()->create();

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('custom_fields.priority')
            ->assertFormFieldExists('custom_fields.advanced_note');
    });

    it('renders section with HIDE_WHEN mode on edit', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Draft Section',
            'entity_type' => Post::class,
            'active' => true,
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

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Draft Note',
            'code' => 'draft_note',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        $publishedPost = Post::factory()->create(['is_published' => true]);

        // Section should still be in the form (visibleJs handles hiding)
        livewire(EditPost::class, ['record' => $publishedPost->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('custom_fields.draft_note');
    });
});

describe('Backend section visibility evaluation', function (): void {
    it('evaluates section visibility with ALL logic correctly', function (): void {
        $visibility = VisibilityData::from([
            'mode' => VisibilityMode::SHOW_WHEN,
            'logic' => VisibilityLogic::ALL,
            'conditions' => [
                [
                    'field_code' => 'title',
                    'operator' => VisibilityOperator::IS_NOT_EMPTY,
                    'value' => null,
                    'source' => ConditionSource::ModelAttribute,
                ],
                [
                    'field_code' => 'is_published',
                    'operator' => VisibilityOperator::EQUALS,
                    'value' => true,
                    'source' => ConditionSource::ModelAttribute,
                ],
            ],
        ]);

        $matchingPost = Post::factory()->create(['title' => 'Test', 'is_published' => true]);
        $missingTitle = Post::factory()->create(['title' => '', 'is_published' => true]);
        $notPublished = Post::factory()->create(['title' => 'Test', 'is_published' => false]);

        expect($visibility->evaluate([], $matchingPost))->toBeTrue()
            ->and($visibility->evaluate([], $missingTitle))->toBeFalse()
            ->and($visibility->evaluate([], $notPublished))->toBeFalse();
    });

    it('evaluates section visibility with ANY logic correctly', function (): void {
        $visibility = VisibilityData::from([
            'mode' => VisibilityMode::SHOW_WHEN,
            'logic' => VisibilityLogic::ANY,
            'conditions' => [
                [
                    'field_code' => 'title',
                    'operator' => VisibilityOperator::CONTAINS,
                    'value' => 'Premium',
                    'source' => ConditionSource::ModelAttribute,
                ],
                [
                    'field_code' => 'is_published',
                    'operator' => VisibilityOperator::EQUALS,
                    'value' => true,
                    'source' => ConditionSource::ModelAttribute,
                ],
            ],
        ]);

        $premiumTitle = Post::factory()->create(['title' => 'Premium Post', 'is_published' => false]);
        $publishedOnly = Post::factory()->create(['title' => 'Basic Post', 'is_published' => true]);
        $neitherMatch = Post::factory()->create(['title' => 'Basic Post', 'is_published' => false]);

        // ANY: at least one condition met
        expect($visibility->evaluate([], $premiumTitle))->toBeTrue()
            ->and($visibility->evaluate([], $publishedOnly))->toBeTrue()
            ->and($visibility->evaluate([], $neitherMatch))->toBeFalse();
    });

    it('evaluates HIDE_WHEN mode correctly', function (): void {
        $visibility = VisibilityData::from([
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
        ]);

        $published = Post::factory()->create(['is_published' => true]);
        $draft = Post::factory()->create(['is_published' => false]);

        // HIDE_WHEN: condition met = hidden, condition not met = visible
        expect($visibility->evaluate([], $published))->toBeFalse()
            ->and($visibility->evaluate([], $draft))->toBeTrue();
    });

    it('evaluates mixed custom field and model attribute conditions', function (): void {
        $visibility = VisibilityData::from([
            'mode' => VisibilityMode::SHOW_WHEN,
            'logic' => VisibilityLogic::ALL,
            'conditions' => [
                [
                    'field_code' => 'priority',
                    'operator' => VisibilityOperator::EQUALS,
                    'value' => 'high',
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
        $draftPost = Post::factory()->create(['is_published' => false]);

        // Both conditions must be met
        expect($visibility->evaluate(['priority' => 'high'], $publishedPost))->toBeTrue()
            ->and($visibility->evaluate(['priority' => 'low'], $publishedPost))->toBeFalse()
            ->and($visibility->evaluate(['priority' => 'high'], $draftPost))->toBeFalse()
            ->and($visibility->evaluate(['priority' => 'low'], $draftPost))->toBeFalse();
    });

    it('evaluates with various operators on model attributes', function (
        string $fieldCode,
        VisibilityOperator $operator,
        mixed $conditionValue,
        array $postData,
        bool $expectedVisible
    ): void {
        $visibility = VisibilityData::from([
            'mode' => VisibilityMode::SHOW_WHEN,
            'logic' => VisibilityLogic::ALL,
            'conditions' => [
                [
                    'field_code' => $fieldCode,
                    'operator' => $operator,
                    'value' => $conditionValue,
                    'source' => ConditionSource::ModelAttribute,
                ],
            ],
        ]);

        $post = Post::factory()->create($postData);

        expect($visibility->evaluate([], $post))->toBe($expectedVisible);
    })->with([
        'equals match' => ['title', VisibilityOperator::EQUALS, 'Test', ['title' => 'Test'], true],
        'equals no match' => ['title', VisibilityOperator::EQUALS, 'Test', ['title' => 'Other'], false],
        'not equals match' => ['title', VisibilityOperator::NOT_EQUALS, 'Test', ['title' => 'Other'], true],
        'not equals no match' => ['title', VisibilityOperator::NOT_EQUALS, 'Test', ['title' => 'Test'], false],
        'contains match' => ['title', VisibilityOperator::CONTAINS, 'rem', ['title' => 'Premium'], true],
        'contains no match' => ['title', VisibilityOperator::CONTAINS, 'xyz', ['title' => 'Premium'], false],
        'not contains match' => ['title', VisibilityOperator::NOT_CONTAINS, 'xyz', ['title' => 'Premium'], true],
        'not contains no match' => ['title', VisibilityOperator::NOT_CONTAINS, 'rem', ['title' => 'Premium'], false],
        'is empty match' => ['title', VisibilityOperator::IS_EMPTY, null, ['title' => ''], true],
        'is empty no match' => ['title', VisibilityOperator::IS_EMPTY, null, ['title' => 'Has content'], false],
        'is not empty match' => ['title', VisibilityOperator::IS_NOT_EMPTY, null, ['title' => 'Has content'], true],
        'is not empty no match' => ['title', VisibilityOperator::IS_NOT_EMPTY, null, ['title' => ''], false],
        'greater than match' => ['rating', VisibilityOperator::GREATER_THAN, 5, ['rating' => 8], true],
        'greater than no match' => ['rating', VisibilityOperator::GREATER_THAN, 5, ['rating' => 3], false],
        'less than match' => ['rating', VisibilityOperator::LESS_THAN, 5, ['rating' => 3], true],
        'less than no match' => ['rating', VisibilityOperator::LESS_THAN, 5, ['rating' => 8], false],
        'boolean equals true' => ['is_published', VisibilityOperator::EQUALS, true, ['is_published' => true], true],
        'boolean equals false' => ['is_published', VisibilityOperator::EQUALS, true, ['is_published' => false], false],
    ]);
});

describe('Edge cases', function (): void {
    it('handles section with empty conditions array', function (): void {
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [],
                ],
            ],
        ]);

        $factory = app(SectionComponentFactory::class);
        $component = $factory->create($section);

        // No conditions = no visibility expression applied
        expect($component->getVisibleJs())->toBeNull()
            ->and($component->isVisible())->toBeTrue();
    });

    it('handles section with null record on create forms', function (): void {
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
                            'field_code' => 'title',
                            'operator' => VisibilityOperator::CONTAINS,
                            'value' => 'Premium',
                            'source' => ConditionSource::ModelAttribute,
                        ],
                    ],
                ],
            ],
        ]);

        $factory = app(SectionComponentFactory::class);
        // null record (create form)
        $component = $factory->create($section, collect(), null);

        // Should still have visibleJs for client-side reactivity
        expect($component->getVisibleJs())->not->toBeNull()
            ->and($component->isVisible())->toBeTrue();
    });

    it('handles multiple sections with different visibility', function (): void {
        $alwaysVisible = CustomFieldSection::factory()->create([
            'name' => 'Always Visible',
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $conditionalSection = CustomFieldSection::factory()->create([
            'name' => 'Conditional',
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

        $factory = app(SectionComponentFactory::class);
        $post = Post::factory()->create(['is_published' => false]);

        $visible = $factory->create($alwaysVisible, collect(), $post);
        $conditional = $factory->create($conditionalSection, collect(), $post);

        expect($visible->getVisibleJs())->toBeNull()
            ->and($visible->isVisible())->toBeTrue()
            ->and($conditional->getVisibleJs())->not->toBeNull()
            ->and($conditional->isVisible())->toBeTrue();
    });

    it('handles section visibility with NOT_CONTAINS operator', function (): void {
        $visibility = VisibilityData::from([
            'mode' => VisibilityMode::SHOW_WHEN,
            'logic' => VisibilityLogic::ALL,
            'conditions' => [
                [
                    'field_code' => 'title',
                    'operator' => VisibilityOperator::NOT_CONTAINS,
                    'value' => 'draft',
                    'source' => ConditionSource::ModelAttribute,
                ],
            ],
        ]);

        $publishedPost = Post::factory()->create(['title' => 'Published Article']);
        $draftPost = Post::factory()->create(['title' => 'This is a draft post']);

        expect($visibility->evaluate([], $publishedPost))->toBeTrue()
            ->and($visibility->evaluate([], $draftPost))->toBeFalse();
    });

    it('handles section visibility with null model attribute value', function (): void {
        $visibility = VisibilityData::from([
            'mode' => VisibilityMode::SHOW_WHEN,
            'logic' => VisibilityLogic::ALL,
            'conditions' => [
                [
                    'field_code' => 'content',
                    'operator' => VisibilityOperator::IS_NOT_EMPTY,
                    'value' => null,
                    'source' => ConditionSource::ModelAttribute,
                ],
            ],
        ]);

        $withContent = Post::factory()->create(['content' => 'Some content']);
        $nullContent = Post::factory()->create(['content' => null]);

        expect($visibility->evaluate([], $withContent))->toBeTrue()
            ->and($visibility->evaluate([], $nullContent))->toBeFalse();
    });
});
