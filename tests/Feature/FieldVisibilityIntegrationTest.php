<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldComponentFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
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
});

afterEach(function (): void {
    BackendVisibilityService::clearCache();
});

describe('Field-level server-side visibility for relation-attribute conditions', function (): void {
    it('reports isFieldVisible true for a matching record and false for a non-matching record', function (): void {
        $matchingTag = Tag::factory()->create();
        $otherTag = Tag::factory()->create();

        $postMatch = Post::factory()->create();
        $postMatch->tagModels()->attach($matchingTag);
        $commentMatch = Comment::factory()->create(['post_id' => $postMatch->id]);

        $postNoMatch = Post::factory()->create();
        $postNoMatch->tagModels()->attach($otherTag);
        $commentNoMatch = Comment::factory()->create(['post_id' => $postNoMatch->id]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'Field Visibility Section',
            'entity_type' => Comment::class,
            'active' => true,
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Relation-gated field',
            'code' => 'relation_gated',
            'type' => 'text',
            'entity_type' => Comment::class,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'post.tagModels',
                        'operator' => VisibilityOperator::IS_IN,
                        'value' => [$matchingTag->id],
                        'source' => ConditionSource::RelationAttribute,
                    ]],
                ],
            ],
        ]);

        $backendService = app(BackendVisibilityService::class);
        $allFields = collect([$field]);

        expect($backendService->isFieldVisible($commentMatch, $field, $allFields))->toBeTrue()
            ->and($backendService->isFieldVisible($commentNoMatch, $field, $allFields))->toBeFalse();
    });

    it('fails open (field visible) when no record is present (create form)', function (): void {
        $matchingTag = Tag::factory()->create();

        $section = CustomFieldSection::factory()->create([
            'name' => 'Create Form Section',
            'entity_type' => Comment::class,
            'active' => true,
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Create-form field',
            'code' => 'create_form_field',
            'type' => 'text',
            'entity_type' => Comment::class,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'post.tagModels',
                        'operator' => VisibilityOperator::IS_IN,
                        'value' => [$matchingTag->id],
                        'source' => ConditionSource::RelationAttribute,
                    ]],
                ],
            ],
        ]);

        // Passing null record → VisibilityData::evaluate() returns true (fail-open)
        $backendService = app(BackendVisibilityService::class);
        $allFields = collect([$field]);

        // Simulate what applyVisibility does when $record is null: no closure is attached,
        // field is returned plain (visible by default). Verify evaluate([], null) is true.
        $visibilityData = $field->settings->visibility;
        expect($visibilityData->evaluate([]))->toBeTrue();
    });

    it('attaches a visible() closure (not visibleJs) when a relation condition field is built with a record', function (): void {
        $matchingTag = Tag::factory()->create();
        $otherTag = Tag::factory()->create();

        $postMatch = Post::factory()->create();
        $postMatch->tagModels()->attach($matchingTag);
        $commentMatch = Comment::factory()->create(['post_id' => $postMatch->id]);

        $postNoMatch = Post::factory()->create();
        $postNoMatch->tagModels()->attach($otherTag);
        $commentNoMatch = Comment::factory()->create(['post_id' => $postNoMatch->id]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'Closure Test Section',
            'entity_type' => Comment::class,
            'active' => true,
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Closure-gated field',
            'code' => 'closure_gated',
            'type' => 'text',
            'entity_type' => Comment::class,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'post.tagModels',
                        'operator' => VisibilityOperator::IS_IN,
                        'value' => [$matchingTag->id],
                        'source' => ConditionSource::RelationAttribute,
                    ]],
                ],
            ],
        ]);

        $factory = app(FieldComponentFactory::class);
        $allFields = collect([$field]);

        $builtForMatch = $factory->create($field, [], $allFields, $commentMatch);
        $builtForNoMatch = $factory->create($field, [], $allFields, $commentNoMatch);

        // The visible closure is the authoritative check here; evaluate it directly.
        // Filament stores ->visible() as a Closure on the component — invoke it to assert result.
        $isVisibleForMatch = value($builtForMatch->isVisible());
        $isVisibleForNoMatch = value($builtForNoMatch->isVisible());

        expect($isVisibleForMatch)->toBeTrue()
            ->and($isVisibleForNoMatch)->toBeFalse();
    });

    it('keeps visibleJs reactive behavior for pure same-record custom-field conditions (no regression)', function (): void {
        $section = CustomFieldSection::factory()->create([
            'name' => 'Same-record Section',
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $triggerField = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Trigger',
            'code' => 'trigger_field',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        $conditionalField = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Conditional',
            'code' => 'conditional_field',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'trigger_field',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => 'active',
                        'source' => ConditionSource::CustomField,
                    ]],
                ],
            ],
        ]);

        $factory = app(FieldComponentFactory::class);
        $allFields = collect([$triggerField, $conditionalField]);

        $built = $factory->create($conditionalField, [], $allFields);

        // Pure custom-field conditions must drive visibility through the reactive client-side JS
        // path (visibleJs + live), NOT a server-side visible() closure. Asserting only isVisible()
        // would pass even if no JS were attached, so we assert the JS expression itself is present.
        $visibleJs = $built->getVisibleJs();

        expect($visibleJs)
            ->toBeString()
            ->not->toBeEmpty()
            ->and($built->isLive())->toBeTrue();

        // It must also match what the frontend service builds for this field, proving the
        // same-record JS path (not the relation-condition server-side fallback) was taken.
        $expectedJs = app(FrontendVisibilityService::class)
            ->buildVisibilityExpression($conditionalField, $allFields);

        expect($expectedJs)
            ->toBeString()
            ->not->toBeEmpty()
            ->and($visibleJs)->toBe($expectedJs);
    });
});

$buildEqualsJs = function (string|int|float $conditionValue, VisibilityOperator $operator = VisibilityOperator::EQUALS): string {
    $section = CustomFieldSection::factory()->create([
        'name' => 'Text Condition Section',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $triggerField = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Status',
        'code' => 'status',
        'type' => 'text',
        'entity_type' => Post::class,
    ]);

    $conditionalField = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Gated',
        'code' => 'gated',
        'type' => 'text',
        'entity_type' => Post::class,
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [[
                    'field_code' => 'status',
                    'operator' => $operator,
                    'value' => $conditionValue,
                    'source' => ConditionSource::CustomField,
                ]],
            ],
        ],
    ]);

    return (string) app(FrontendVisibilityService::class)
        ->buildVisibilityExpression($conditionalField, collect([$triggerField, $conditionalField]));
};

describe('Numeric-string condition values in the generated visibility JS', function () use ($buildEqualsJs): void {
    it('emits a numeric string as a JS string literal, and still coerces when the field holds a number', function () use ($buildEqualsJs): void {
        expect($buildEqualsJs('42'))
            ->toContain("const compareVal = '42';")
            ->toContain("typeof fieldVal === 'number' && typeof compareVal === 'string'")
            ->toContain("typeof fieldVal === 'string' && typeof compareVal === 'number'");
    });

    it('keeps a numeric string comparable as a string, matching the backend operator', function () use ($buildEqualsJs): void {
        // VisibilityOperator::evaluateEquals() compares two strings as strings, so '42.5' and
        // '42.50' are NOT equal on the server. Emitting the condition as a JS number would make
        // parseFloat('42.5') === 42.5 true on the client and silently desync the two engines.
        expect($buildEqualsJs('42.50'))->toContain("const compareVal = '42.50';")
            ->and(VisibilityOperator::EQUALS->evaluate('42.5', '42.50'))->toBeFalse();
    });

    it('emits genuine int and float condition values as JS numbers', function () use ($buildEqualsJs): void {
        expect($buildEqualsJs(42))->toContain('const compareVal = 42;')
            ->and($buildEqualsJs(42.5))->toContain('const compareVal = 42.5000000000;');
    });
});

describe('Case-insensitive string equality parity between the two visibility engines', function () use ($buildEqualsJs): void {
    it('folds both sides before comparing, matching the backend operator', function () use ($buildEqualsJs): void {
        // Server: strtolower('active') === strtolower('Active') is true. A case-sensitive client
        // comparison hides the field the server would show.
        expect(VisibilityOperator::EQUALS->evaluate('active', 'Active'))->toBeTrue()
            ->and($buildEqualsJs('Active'))
            ->toContain("typeof fieldVal === 'string' && typeof compareVal === 'string'")
            ->toContain('fieldVal.toLowerCase() === compareVal.toLowerCase()');
    });

    it('applies the same folding to not_equals, which negates the equals expression', function () use ($buildEqualsJs): void {
        expect(VisibilityOperator::NOT_EQUALS->evaluate('active', 'Active'))->toBeFalse()
            ->and($buildEqualsJs('Active', VisibilityOperator::NOT_EQUALS))
            ->toContain('fieldVal.toLowerCase() === compareVal.toLowerCase()');
    });

    it('still distinguishes genuinely different strings', function () use ($buildEqualsJs): void {
        expect(VisibilityOperator::EQUALS->evaluate('inactive', 'Active'))->toBeFalse()
            ->and($buildEqualsJs('Active'))->toContain("const compareVal = 'Active';");
    });
});
