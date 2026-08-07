<?php

declare(strict_types=1);

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Database\Eloquent\Builder;
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
use Relaticle\CustomFields\Support\CodeGenerator;
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
    CodeGenerator::resolveUniquenessScopeUsing(null);
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

/**
 * With SYSTEM_SECTIONS enabled, FormBuilder::values()/InfolistBuilder::values() return one
 * component per section, not one per field, so asserting a count on the outer collection
 * can't tell "the section kept N fields" from "there are N sections" apart. Reach into the
 * section component's raw childComponents (set by ->schema()) to count what it actually
 * carries, without needing the full Livewire-attached schema tree just to read it back.
 *
 * @return array<int, mixed>
 */
function sectionFieldComponents(Component $section): array
{
    $property = new ReflectionProperty($section, 'childComponents');
    $property->setAccessible(true);

    return $property->getValue($section)['default'] ?? [];
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

describe('CodeGenerator uniqueness scope resolver', function (): void {
    it('suffixes a colliding code when no resolver is registered (backward compatible)', function (): void {
        $section = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Scope Default', 'code' => 'scope_default']);

        CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'entity_type' => Post::class,
            'name' => 'HMIS ID',
            'code' => 'hmis_id',
            'type' => 'text',
        ]);

        expect(CodeGenerator::generateUniqueFieldCode('HMIS ID', Post::class))
            ->toBe('hmis_id_1');
    });

    it('returns the base code when the collision is outside the registered scope', function (): void {
        $outside = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Scope Outside', 'code' => 'scope_outside']);
        $inside = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Scope Inside', 'code' => 'scope_inside']);

        CustomField::factory()->create([
            'custom_field_section_id' => $outside->id,
            'entity_type' => Post::class,
            'name' => 'HMIS ID',
            'code' => 'hmis_id',
            'type' => 'text',
        ]);

        CodeGenerator::resolveUniquenessScopeUsing(
            fn (string $entityType, string $type, int|string|null $sectionId): ?Closure => $type === 'field' && $sectionId !== null
                ? fn (Builder $query): Builder => $query->where('custom_field_section_id', $sectionId)
                : null
        );

        expect(CodeGenerator::generateUniqueFieldCode('HMIS ID', Post::class, sectionId: $inside->id))
            ->toBe('hmis_id');
    });

    it('still suffixes when the collision is inside the registered scope', function (): void {
        $inside = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Scope Inside Only', 'code' => 'scope_inside_only']);

        CustomField::factory()->create([
            'custom_field_section_id' => $inside->id,
            'entity_type' => Post::class,
            'name' => 'HMIS ID',
            'code' => 'hmis_id',
            'type' => 'text',
        ]);

        CodeGenerator::resolveUniquenessScopeUsing(
            fn (string $entityType, string $type, int|string|null $sectionId): ?Closure => $type === 'field' && $sectionId !== null
                ? fn (Builder $query): Builder => $query->where('custom_field_section_id', $sectionId)
                : null
        );

        expect(CodeGenerator::generateUniqueFieldCode('HMIS ID', Post::class, sectionId: $inside->id))
            ->toBe('hmis_id_1');
    });

    it('applies a scope closure that returns a new builder instance instead of mutating in place', function (): void {
        $outside = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Cloned Outside', 'code' => 'cloned_outside']);
        $inside = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Cloned Inside', 'code' => 'cloned_inside']);

        CustomField::factory()->create([
            'custom_field_section_id' => $outside->id,
            'entity_type' => Post::class,
            'name' => 'HMIS ID',
            'code' => 'hmis_id',
            'type' => 'text',
        ]);

        /*
         * Builder::where() mutates and returns the same instance, so a mutation-style scope
         * closure would pass this even if the code discarded its return value. clone() is
         * what actually discriminates: it hands back a distinct instance, so only code that
         * reassigns $query to the closure's return value picks the narrowed clone up.
         */
        CodeGenerator::resolveUniquenessScopeUsing(
            fn (string $entityType, string $type, int|string|null $sectionId): ?Closure => $type === 'field' && $sectionId !== null
                ? fn (Builder $query): Builder => $query->clone()->where('custom_field_section_id', $sectionId)
                : null
        );

        expect(CodeGenerator::generateUniqueFieldCode('HMIS ID', Post::class, sectionId: $inside->id))
            ->toBe('hmis_id');
    });

    it('passes null as the section id to the resolver when generateUniqueFieldCode() is called without one', function (): void {
        $section = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Sectionless', 'code' => 'sectionless']);

        CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'entity_type' => Post::class,
            'name' => 'HMIS ID',
            'code' => 'hmis_id',
            'type' => 'text',
        ]);

        $receivedSectionId = 'not-called';

        CodeGenerator::resolveUniquenessScopeUsing(
            function (string $entityType, string $type, int|string|null $sectionId) use (&$receivedSectionId): ?Closure {
                $receivedSectionId = $sectionId;

                return null;
            }
        );

        CodeGenerator::generateUniqueFieldCode('HMIS ID', Post::class);

        expect($receivedSectionId)->toBeNull();
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

        /*
         * With SYSTEM_SECTIONS enabled, values() returns one component per section (there is
         * only ever the one section here), so asserting a count on the outer collection can't
         * tell "only() dropped a field" from "only() dropped nothing" apart — it stays 1
         * either way. Assert on the section's own field count instead so this discriminates.
         */
        $sections = CustomFields::form()
            ->forModel(Post::class)
            ->onlySections([$section->id])
            ->only(['keep_me'])
            ->values();

        expect($sections)->toHaveCount(1)
            ->and(sectionFieldComponents($sections->first()))->toHaveCount(1);
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
    it('honors the section scope through FormBuilder::build(), not just values()', function (): void {
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

    it('honors the section scope through InfolistBuilder::build(), not just values()', function (): void {
        $sectionA = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Info Built A', 'code' => 'info_built_a']);
        $sectionB = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'name' => 'Info Built B', 'code' => 'info_built_b']);

        CustomField::factory()->create([
            'custom_field_section_id' => $sectionA->id,
            'entity_type' => Post::class,
            'name' => 'Info Built A Field',
            'code' => 'info_built_a_field',
            'type' => 'text',
        ]);

        CustomField::factory()->create([
            'custom_field_section_id' => $sectionB->id,
            'entity_type' => Post::class,
            'name' => 'Info Built B Field',
            'code' => 'info_built_b_field',
            'type' => 'text',
        ]);

        $container = CustomFields::infolist()
            ->forModel(Post::class)
            ->onlySections([$sectionA->id])
            ->build();

        $schema = FilamentSchema::make(livewire(CreatePost::class)->instance())
            ->model(Post::class)
            ->components([$container]);

        $entryNames = collect($schema->getFlatComponents())
            ->filter(fn (object $component): bool => $component instanceof Entry)
            ->map(fn (Entry $component): string => $component->getName());

        expect($entryNames)->toHaveCount(1)
            ->and($entryNames)->toContain('custom_fields.info_built_a_field')
            ->and($entryNames)->not->toContain('custom_fields.info_built_b_field');
    });
});
