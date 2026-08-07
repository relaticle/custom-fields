<?php

declare(strict_types=1);

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Filament\Management\Forms\Components\VisibilityComponent;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;

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

describe('BaseBuilder onlySections() scope', function (): void {
    beforeEach(function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY, CustomFieldsFeature::SYSTEM_SECTIONS)
        );
    });

    it('scopes resolution to the given sections when two sections share a field code', function (): void {
        $sectionA = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Qualifying A', 'code' => 'qualifying_a']);
        $sectionB = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Qualifying B', 'code' => 'qualifying_b']);

        foreach ([$sectionA, $sectionB] as $section) {
            CustomField::factory()->create([
                'custom_field_section_id' => $section->id,
                'entity_type' => Post::class,
                'name' => 'Shared',
                'code' => 'shared_code',
                'type' => 'text',
            ]);
        }

        $scoped = CustomFields::form()
            ->forModel(Post::class)
            ->onlySections([$sectionA->id])
            ->values();

        $unscoped = CustomFields::form()
            ->forModel(Post::class)
            ->values();

        expect($scoped)->toHaveCount(1)
            ->and($unscoped)->toHaveCount(2);
    });

    it('treats an empty section scope as no scope', function (): void {
        $section = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Only Section', 'code' => 'only_section']);

        CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'entity_type' => Post::class,
            'name' => 'A Field',
            'code' => 'a_field',
            'type' => 'text',
        ]);

        expect(
            CustomFields::form()
                ->forModel(Post::class)
                ->onlySections([])
                ->values()
        )->toHaveCount(1);
    });

    it('composes section scope with only() field codes', function (): void {
        $section = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Combo', 'code' => 'combo']);

        foreach (['keep_me', 'drop_me'] as $index => $code) {
            CustomField::factory()->create([
                'custom_field_section_id' => $section->id,
                'entity_type' => Post::class,
                'name' => ucfirst($code),
                'code' => $code,
                'type' => 'text',
                'sort_order' => $index,
            ]);
        }

        expect(
            CustomFields::form()
                ->forModel(Post::class)
                ->onlySections([$section->id])
                ->only(['keep_me'])
                ->values()
        )->toHaveCount(1);
    });
});

describe('BaseBuilder onlySections() scope on a sections-disabled install', function (): void {
    /*
     * custom_field_section_id only exists on the table when SYSTEM_SECTIONS was enabled at
     * migration time. Toggling the feature flag at runtime does not remove an already
     * migrated column, so the column is dropped here to reproduce a genuine
     * sections-were-never-enabled install rather than merely flipping the config flag.
     */
    beforeEach(function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
        );

        collect(Schema::getIndexes('custom_fields'))
            ->filter(fn (array $index): bool => in_array('custom_field_section_id', $index['columns'], true))
            ->each(fn (array $index) => DB::statement("DROP INDEX \"{$index['name']}\""));

        Schema::table('custom_fields', fn (Blueprint $table) => $table->dropColumn('custom_field_section_id'));
    });

    it('returns fields unchanged when the section scope is empty', function (): void {
        CustomField::factory()->create([
            'entity_type' => Post::class,
            'name' => 'Alpha',
            'code' => 'alpha_flat',
            'type' => 'text',
        ]);

        expect(CustomFields::form()->forModel(Post::class)->onlySections([])->values())
            ->toHaveCount(1);
    });

    it('returns an empty collection instead of throwing when a section scope is requested', function (): void {
        CustomField::factory()->create([
            'entity_type' => Post::class,
            'name' => 'Alpha',
            'code' => 'alpha_flat',
            'type' => 'text',
        ]);

        /*
         * SQLite falls back to treating an unresolvable double-quoted identifier as a
         * string literal instead of raising "no such column" (the error MySQL, the
         * project's production driver, actually raises), so a dropped-column WHERE clause
         * silently matches zero rows here either way. A query-log assertion is the
         * driver-agnostic way to prove the guard short-circuits before any query runs,
         * rather than merely happening to agree with the guarded result by accident.
         */
        $customFieldsTableQueried = false;

        DB::listen(function (QueryExecuted $query) use (&$customFieldsTableQueried): void {
            if (str_contains($query->sql, 'custom_fields')) {
                $customFieldsTableQueried = true;
            }
        });

        $result = CustomFields::form()->forModel(Post::class)->onlySections([1])->values();

        expect($customFieldsTableQueried)->toBeFalse()
            ->and($result)->toHaveCount(0);
    });
});

describe('FormContainer/InfolistContainer onlySections() scope', function (): void {
    beforeEach(function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY, CustomFieldsFeature::SYSTEM_SECTIONS)
        );

        $this->actingAs(User::factory()->create());
    });

    /*
     * Filament's getFlatComponents() keys its result by component statePath, so two
     * fields sharing a code would collapse into a single array entry regardless of
     * onlySections() — a false negative unrelated to scoping. Distinct codes per
     * section are what let the assertion actually discriminate scoped vs. unscoped.
     */
    it('honors the section scope through build(), not just values()', function (): void {
        $sectionA = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Built A', 'code' => 'built_a']);
        $sectionB = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Built B', 'code' => 'built_b']);

        CustomField::factory()->create([
            'custom_field_section_id' => $sectionA->id,
            'entity_type' => Post::class,
            'name' => 'Built A Field',
            'code' => 'built_a_field',
            'type' => 'text',
        ]);

        CustomField::factory()->create([
            'custom_field_section_id' => $sectionB->id,
            'entity_type' => Post::class,
            'name' => 'Built B Field',
            'code' => 'built_b_field',
            'type' => 'text',
        ]);

        $container = CustomFields::form()
            ->forModel(Post::class)
            ->onlySections([$sectionA->id])
            ->build();

        $schema = FilamentSchema::make(livewire(CreatePost::class)->instance())
            ->model(Post::class)
            ->components([$container]);

        $fieldNames = collect($schema->getFlatComponents())
            ->filter(fn (object $component): bool => $component instanceof Field)
            ->map(fn (Field $component): string => $component->getName());

        expect($fieldNames)->toHaveCount(1)
            ->and($fieldNames)->toContain('custom_fields.built_a_field')
            ->and($fieldNames)->not->toContain('custom_fields.built_b_field');
    });
});
