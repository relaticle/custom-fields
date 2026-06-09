<?php

declare(strict_types=1);

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Filament\Management\Forms\Components\VisibilityComponent;
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
        $config = app(RelationConditionConfig::class);

        expect($config->isRelationSourceAvailable(Comment::class))->toBeTrue()
            ->and($config->isRelationSourceAvailable(Post::class))->toBeFalse();
    });

    it('relationsFor returns configured paths for the entity', function (): void {
        $this->setEntityConditionRelations([
            Comment::class => [
                'post.tagModels' => 'Post → Tags',
                'post' => 'Post',
            ],
        ]);

        $config = app(RelationConditionConfig::class);

        expect($config->relationsFor(Comment::class))->toBe([
            'post.tagModels' => 'Post → Tags',
            'post' => 'Post',
        ])->and($config->relationsFor(Post::class))->toBe([]);
    });
});

describe('custom-field fallback operator set excludes relation-only operators', function (): void {
    it('does not include IS_IN or IS_NOT_IN when no field type data is available (custom-field source with blank field_code)', function (): void {
        // VisibilityComponent::getCompatibleOperators() is private; we reach it via reflection
        // to verify the fallback branch (no $fieldData) excludes relation-only operators.
        $component = new VisibilityComponent;

        $method = new ReflectionMethod($component, 'getCompatibleOperators');
        $method->setAccessible(true);

        // Build a minimal Get stub that returns null/blank for all keys (simulates blank field_code,
        // CustomField source), forcing $fieldData to be null so the fallback branch executes.
        $get = new class extends Get
        {
            public function __construct()
            {
                // Skip parent constructor (which needs a Component) — we only need __invoke.
            }

            public function __invoke(string|Component $path = '', bool $isAbsolute = false): mixed
            {
                return null;
            }
        };

        $operators = $method->invoke($component, $get);

        expect(array_keys($operators))
            ->not->toContain(VisibilityOperator::IS_IN->value, 'IS_IN must be excluded from the custom-field fallback operator list')
            ->not->toContain(VisibilityOperator::IS_NOT_IN->value, 'IS_NOT_IN must be excluded from the custom-field fallback operator list');
    });
});
