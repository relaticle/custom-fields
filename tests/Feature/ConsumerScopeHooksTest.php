<?php

declare(strict_types=1);

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Filament\Management\Forms\Components\VisibilityComponent;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

/**
 * Consumer scoping hooks let an app (e.g. a versioned-form builder) constrain the
 * "depends on" field picker and the field-name uniqueness rule to a subset of an
 * entity's fields, so the same names can be reused across separate parent forms.
 */
beforeEach(function (): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
        ->enable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
    );
});

afterEach(function (): void {
    VisibilityComponent::resolveAvailableFieldsScopeUsing(null);
    FieldForm::resolveUniqueRuleModifierUsing(null);
});

function nullGet(): Get
{
    return new class extends Get
    {
        public function __construct() {}

        public function __invoke(string|Component $path = '', bool $isAbsolute = false): mixed
        {
            return null;
        }
    };
}

function availableFields(VisibilityComponent $component): array
{
    $method = new ReflectionMethod($component, 'getAvailableFields');
    $method->setAccessible(true);

    return $method->invoke($component, nullGet());
}

describe('VisibilityComponent available-fields scope resolver', function (): void {
    it('lists all entity fields when no resolver is registered (backward compatible)', function (): void {
        $sectionA = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'A', 'code' => 'a']);
        $sectionB = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'B', 'code' => 'b']);
        CustomField::factory()->create(['custom_field_section_id' => $sectionA->id, 'entity_type' => Post::class, 'name' => 'Alpha', 'code' => 'alpha', 'type' => 'text']);
        CustomField::factory()->create(['custom_field_section_id' => $sectionB->id, 'entity_type' => Post::class, 'name' => 'Beta', 'code' => 'beta', 'type' => 'text']);

        $options = availableFields(VisibilityComponent::makeForSection(Post::class, $sectionA));

        expect($options)->toBe(['alpha' => 'Alpha', 'beta' => 'Beta']);
    });

    it('constrains the field list to the resolver-defined subset', function (): void {
        $sectionA = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'A', 'code' => 'a']);
        $sectionB = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'B', 'code' => 'b']);
        CustomField::factory()->create(['custom_field_section_id' => $sectionA->id, 'entity_type' => Post::class, 'name' => 'Alpha', 'code' => 'alpha', 'type' => 'text']);
        CustomField::factory()->create(['custom_field_section_id' => $sectionB->id, 'entity_type' => Post::class, 'name' => 'Beta', 'code' => 'beta', 'type' => 'text']);

        VisibilityComponent::resolveAvailableFieldsScopeUsing(
            fn (string $entityType, ?CustomFieldSection $section): ?Closure => $section instanceof CustomFieldSection
                ? fn ($query) => $query->where('custom_field_section_id', $section->id)
                : null
        );

        $options = availableFields(VisibilityComponent::makeForSection(Post::class, $sectionA));

        expect($options)->toBe(['alpha' => 'Alpha']);
    });

    it('falls back to all fields when the resolver returns null for the given section', function (): void {
        $sectionA = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'A', 'code' => 'a']);
        CustomField::factory()->create(['custom_field_section_id' => $sectionA->id, 'entity_type' => Post::class, 'name' => 'Alpha', 'code' => 'alpha', 'type' => 'text']);

        VisibilityComponent::resolveAvailableFieldsScopeUsing(fn (string $entityType, ?CustomFieldSection $section): null => null);

        $options = availableFields(VisibilityComponent::makeForSection(Post::class, $sectionA));

        expect($options)->toBe(['alpha' => 'Alpha']);
    });
});

describe('FieldForm unique-name modifier resolver', function (): void {
    it('builds the field schema unchanged when no resolver is registered (backward compatible)', function (): void {
        $schema = FieldForm::schema();

        expect($schema)->toBeArray()->not->toBeEmpty();
    });

    it('accepts an optional section without altering the schema shape', function (): void {
        $section = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'A', 'code' => 'a']);

        FieldForm::resolveUniqueRuleModifierUsing(
            fn (?CustomFieldSection $s): ?Closure => $s instanceof CustomFieldSection
                ? fn ($rule) => $rule->where('custom_field_section_id', $s->id)
                : null
        );

        expect(FieldForm::schema(section: $section))->toBeArray()->not->toBeEmpty();
    });
});
