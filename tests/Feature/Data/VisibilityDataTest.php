<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

describe('VisibilityConditionData', function (): void {
    it('defaults source to CustomField', function (): void {
        $condition = VisibilityConditionData::from([
            'field_code' => 'status',
            'operator' => VisibilityOperator::EQUALS,
            'value' => 'active',
        ]);

        expect($condition->source)->toBe(ConditionSource::CustomField)
            ->and($condition->isCustomField())->toBeTrue()
            ->and($condition->isModelAttribute())->toBeFalse();
    });

    it('can be created with ModelAttribute source', function (): void {
        $condition = VisibilityConditionData::from([
            'field_code' => 'title',
            'operator' => VisibilityOperator::CONTAINS,
            'value' => 'Premium',
            'source' => ConditionSource::ModelAttribute,
        ]);

        expect($condition->source)->toBe(ConditionSource::ModelAttribute)
            ->and($condition->isModelAttribute())->toBeTrue()
            ->and($condition->isCustomField())->toBeFalse();
    });

    it('deserializes legacy data without source property', function (): void {
        $condition = VisibilityConditionData::from([
            'field_code' => 'priority',
            'operator' => VisibilityOperator::EQUALS,
            'value' => 'high',
        ]);

        expect($condition->source)->toBe(ConditionSource::CustomField);
    });
});

describe('VisibilityData evaluate with model attributes', function (): void {
    beforeEach(function (): void {
        $currentConfig = config('custom-fields.features');
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(
                CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
                CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS,
            )
        );
    });

    it('evaluates model attribute SHOW_WHEN condition', function (): void {
        $visibility = VisibilityData::from([
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
        ]);

        $published = Post::factory()->create(['is_published' => true]);
        $draft = Post::factory()->create(['is_published' => false]);

        expect($visibility->evaluate([], $published))->toBeTrue()
            ->and($visibility->evaluate([], $draft))->toBeFalse();
    });

    it('evaluates model attribute HIDE_WHEN condition', function (): void {
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

        expect($visibility->evaluate(['priority' => 'high'], $publishedPost))->toBeTrue()
            ->and($visibility->evaluate(['priority' => 'low'], $publishedPost))->toBeFalse()
            ->and($visibility->evaluate(['priority' => 'high'], $draftPost))->toBeFalse()
            ->and($visibility->evaluate(['priority' => 'low'], $draftPost))->toBeFalse();
    });

    it('evaluates model attribute with ANY logic', function (): void {
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

        expect($visibility->evaluate([], $premiumTitle))->toBeTrue()
            ->and($visibility->evaluate([], $publishedOnly))->toBeTrue()
            ->and($visibility->evaluate([], $neitherMatch))->toBeFalse();
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
        'contains match' => ['title', VisibilityOperator::CONTAINS, 'rem', ['title' => 'Premium'], true],
        'contains no match' => ['title', VisibilityOperator::CONTAINS, 'xyz', ['title' => 'Premium'], false],
        'not contains match' => ['title', VisibilityOperator::NOT_CONTAINS, 'xyz', ['title' => 'Premium'], true],
        'is empty match' => ['title', VisibilityOperator::IS_EMPTY, null, ['title' => ''], true],
        'is not empty match' => ['title', VisibilityOperator::IS_NOT_EMPTY, null, ['title' => 'Has content'], true],
        'greater than match' => ['rating', VisibilityOperator::GREATER_THAN, 5, ['rating' => 8], true],
        'greater than no match' => ['rating', VisibilityOperator::GREATER_THAN, 5, ['rating' => 3], false],
        'less than match' => ['rating', VisibilityOperator::LESS_THAN, 5, ['rating' => 3], true],
        'less than no match' => ['rating', VisibilityOperator::LESS_THAN, 5, ['rating' => 8], false],
    ]);
});

describe('VisibilityData feature flag enforcement', function (): void {
    it('skips model attribute conditions when feature flag is disabled', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
            ->disable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
        );

        $visibility = VisibilityData::from([
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
        ]);

        $draft = Post::factory()->create(['is_published' => false]);

        expect($visibility->evaluate([], $draft))->toBeTrue();
    });

    it('evaluates custom field conditions normally when model attributes feature is disabled', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
            ->disable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
        );

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
            ],
        ]);

        expect($visibility->evaluate(['priority' => 'high']))->toBeTrue()
            ->and($visibility->evaluate(['priority' => 'low']))->toBeFalse();
    });

    it('skips model attribute conditions but keeps custom field conditions in mixed mode', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
            ->disable(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)
        );

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

        $draft = Post::factory()->create(['is_published' => false]);

        expect($visibility->evaluate(['priority' => 'high'], $draft))->toBeTrue()
            ->and($visibility->evaluate(['priority' => 'low'], $draft))->toBeFalse();
    });
});

describe('VisibilityData create form fail-open', function (): void {
    beforeEach(function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(
                CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
                CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS,
            )
        );
    });

    it('returns true when record is null and all conditions are model-attribute-only', function (): void {
        $visibility = VisibilityData::from([
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
        ]);

        expect($visibility->evaluate([], null))->toBeTrue();
    });

    it('still evaluates custom field conditions when record is null', function (): void {
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
                    'field_code' => 'title',
                    'operator' => VisibilityOperator::CONTAINS,
                    'value' => 'Premium',
                    'source' => ConditionSource::ModelAttribute,
                ],
            ],
        ]);

        expect($visibility->evaluate(['priority' => 'high'], null))->toBeTrue()
            ->and($visibility->evaluate(['priority' => 'low'], null))->toBeFalse();
    });
});

describe('VisibilityData getDependentFields', function (): void {
    it('excludes model attribute conditions from dependent fields', function (): void {
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

        expect($visibility->getDependentFields())->toBe(['priority']);
    });
});

describe('VisibilityData hasModelAttributeConditions', function (): void {
    it('returns true when model attribute conditions exist', function (): void {
        $visibility = VisibilityData::from([
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
        ]);

        expect($visibility->hasModelAttributeConditions())->toBeTrue();
    });

    it('returns false when only custom field conditions exist', function (): void {
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
            ],
        ]);

        expect($visibility->hasModelAttributeConditions())->toBeFalse();
    });

    it('returns false when no conditions exist', function (): void {
        $visibility = VisibilityData::from([
            'mode' => VisibilityMode::ALWAYS_VISIBLE,
        ]);

        expect($visibility->hasModelAttributeConditions())->toBeFalse();
    });
});
