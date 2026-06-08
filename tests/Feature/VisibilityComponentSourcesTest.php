<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Support\RelationConditionConfig;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

describe('IS_IN / IS_NOT_IN are not offered by any field data type', function (): void {
    it('excludes IS_IN and IS_NOT_IN from all FieldDataType compatible operator sets', function (): void {
        $forbidden = [VisibilityOperator::IS_IN, VisibilityOperator::IS_NOT_IN];

        foreach (FieldDataType::cases() as $dataType) {
            foreach ($forbidden as $operator) {
                expect($dataType->getCompatibleOperators())
                    ->not->toContain($operator, sprintf('FieldDataType::%s must not include %s', $dataType->name, $operator->name));
            }
        }
    });

    it('excludes IS_IN and IS_NOT_IN from every registered field type compatible operator options', function (): void {
        $forbiddenValues = [VisibilityOperator::IS_IN->value, VisibilityOperator::IS_NOT_IN->value];
        $fieldTypes = ['text', 'textarea', 'number', 'select', 'checkbox', 'radio', 'date', 'date-time', 'toggle', 'tags-input'];

        foreach ($fieldTypes as $type) {
            $fieldTypeData = CustomFieldsType::getFieldType($type);

            expect($fieldTypeData)->not->toBeNull(sprintf("Field type '%s' must be registered", $type));

            $operatorKeys = array_keys($fieldTypeData->getCompatibleOperatorOptions());

            foreach ($forbiddenValues as $value) {
                expect($operatorKeys)->not->toContain($value, sprintf("Field type '%s' must not include operator '%s'", $type, $value));
            }
        }
    });
});

describe('RelationConditionConfig source availability', function (): void {
    it('relation source is only offered when paths are configured for the entity', function (): void {
        config()->set('custom-fields.visibility', [
            'restrict_to_configured' => false,
            'sources' => [
                Comment::class => [
                    'relations' => ['post.tagModels' => 'Post → Tags'],
                ],
            ],
        ]);

        $config = app(RelationConditionConfig::class);

        expect($config->isRelationSourceAvailable(Comment::class))->toBeTrue()
            ->and($config->isRelationSourceAvailable(Post::class))->toBeFalse();
    });

    it('model-attribute source is available for all entities when not restricted', function (): void {
        config()->set('custom-fields.visibility', ['restrict_to_configured' => false, 'sources' => []]);

        $config = app(RelationConditionConfig::class);

        expect($config->isModelAttributeSourceAvailable(Post::class))->toBeTrue()
            ->and($config->isModelAttributeSourceAvailable(Comment::class))->toBeTrue();
    });

    it('model-attribute source is unavailable when restricted with no configured attributes', function (): void {
        config()->set('custom-fields.visibility', [
            'restrict_to_configured' => true,
            'sources' => [
                Comment::class => [
                    'relations' => ['post.tagModels' => 'Post → Tags'],
                ],
            ],
        ]);

        $config = app(RelationConditionConfig::class);

        expect($config->isModelAttributeSourceAvailable(Post::class))->toBeFalse()
            ->and($config->isModelAttributeSourceAvailable(Comment::class))->toBeFalse();
    });

    it('relationsFor returns configured paths for the entity', function (): void {
        config()->set('custom-fields.visibility', [
            'restrict_to_configured' => false,
            'sources' => [
                Comment::class => [
                    'relations' => [
                        'post.tagModels' => 'Post → Tags',
                        'post' => 'Post',
                    ],
                ],
            ],
        ]);

        $config = app(RelationConditionConfig::class);

        expect($config->relationsFor(Comment::class))->toBe([
            'post.tagModels' => 'Post → Tags',
            'post' => 'Post',
        ])->and($config->relationsFor(Post::class))->toBe([]);
    });
});
