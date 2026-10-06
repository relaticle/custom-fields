<?php

declare(strict_types=1);

use Closure;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RelationshipFieldType;
use Relaticle\CustomFields\Filament\Management\Forms\Components\TypeField;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

function postFieldsTable(): Testable
{
    return livewire(ManageFieldsTable::class, ['entityType' => Post::class]);
}

describe('the attribute table', function (): void {
    it('gives every active row a reorder handle, a type icon and its badges', function (): void {
        CustomField::factory()->ofType('text')->create([
            'entity_type' => Post::class,
            'name' => 'Account owner',
            'settings' => ['unique_per_entity_type' => true],
            'validation_rules' => ['required' => true],
        ]);

        $table = postFieldsTable()
            ->assertSeeHtml('x-sortable-handle')
            ->assertSee('Account owner')
            ->assertSee('Unique');

        if (! rendersPolished(UiSurface::AttributeTable)) {
            // The stock row draws one badge, unique or required, never both.
            $table->assertDontSeeHtml('data-surface="attribute-table"')
                ->assertDontSeeHtml('fi-cf-attribute-row')
                ->assertDontSee('Required');

            return;
        }

        $table->assertSeeHtml('data-surface="attribute-table"')
            ->assertSeeHtml('fi-cf-attribute-row')
            ->assertSee('Required');
    });

    it('offers an archived field an activate button without opening the menu', function (): void {
        $field = CustomField::factory()->ofType('text')->create([
            'entity_type' => Post::class,
            'name' => 'Retired field',
        ]);
        $field->deactivate();

        postFieldsTable()
            ->assertSee('Archived')
            ->assertSeeHtml('activateField')
            ->callAction('activateField', arguments: ['fieldId' => $field->getKey()]);

        expect($field->fresh()->isActive())->toBeTrue();
    });

    it('connects the two rows a relationship pairs', function (): void {
        $section = sectionForEntity(Post::class);

        $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'mentions',
            fromEntityType: Post::class,
            toEntityType: Post::class,
            cardinality: RelationshipCardinality::ManyToMany,
            fromField: new FieldSlotData(name: 'Mentions', sectionId: $section->getKey(), type: RelationshipFieldType::KEY),
            toField: new FieldSlotData(name: 'Mentioned By', sectionId: $section->getKey(), type: RelationshipFieldType::KEY),
        ));

        $table = postFieldsTable();

        expect($table->instance()->relationshipPairs())->toHaveCount(2);

        if (! rendersPolished(UiSurface::AttributeTable)) {
            $table->assertDontSeeHtml('data-pair="'.$definition->getKey().'"');

            return;
        }

        $table->assertSeeHtml('data-pair="'.$definition->getKey().'"')
            ->assertSeeHtml('data-pair-partner="'.$definition->to_field_id.'"')
            ->assertSeeHtml('data-pair-partner="'.$definition->from_field_id.'"')
            ->assertSee('Paired with Mentioned By on Post')
            ->assertSee('Paired with Mentions on Post');
    });

    it('leaves the pairing sentence readable in a column narrower than it', function (): void {
        $section = sectionForEntity(Post::class);

        app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'mentions',
            fromEntityType: Post::class,
            toEntityType: Post::class,
            cardinality: RelationshipCardinality::ManyToMany,
            fromField: new FieldSlotData(name: 'Mentions', sectionId: $section->getKey(), type: RelationshipFieldType::KEY),
            toField: new FieldSlotData(name: 'Mentioned By', sectionId: $section->getKey(), type: RelationshipFieldType::KEY),
        ));

        $table = postFieldsTable();

        if (! rendersPolished(UiSurface::AttributeTable)) {
            // The stock row draws no pairing line at all.
            $table->assertDontSee('Paired with Mentioned By on Post');

            return;
        }

        $table->assertSeeHtml('title="Paired with Mentioned By on Post"')
            ->assertSeeHtml('<span>Paired with Mentioned By on Post</span>');
    });

    it('leaves a symmetric pairing sentence readable too', function (): void {
        $section = sectionForEntity(Post::class);

        app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'peers',
            fromEntityType: Post::class,
            toEntityType: Post::class,
            cardinality: RelationshipCardinality::ManyToMany,
            isSymmetric: true,
            fromField: new FieldSlotData(name: 'Peers', sectionId: $section->getKey(), type: RelationshipFieldType::KEY),
        ));

        $table = postFieldsTable();

        if (! rendersPolished(UiSurface::AttributeTable)) {
            $table->assertDontSee('Read from both ends on Post');

            return;
        }

        $table->assertSeeHtml('title="Read from both ends on Post"')
            ->assertSeeHtml('<span>Read from both ends on Post</span>');
    });

    it('says what a custom field is when there are none', function (): void {
        $table = postFieldsTable()->assertSee('No custom fields yet');

        rendersPolished(UiSurface::AttributeTable)
            ? $table->assertSee('adds a column of your own to every record')
            : $table->assertDontSee('adds a column of your own to every record');
    });

    it('holds a skeleton for the row that is still loading', function (): void {
        CustomField::factory()->ofType('text')->create(['entity_type' => Post::class, 'name' => 'Owner']);

        $table = postFieldsTable();

        if (! rendersPolished(UiSurface::AttributeTable)) {
            $table->assertDontSeeHtml('fi-cf-attribute-skeleton');

            return;
        }

        $table->assertSeeHtml('fi-cf-attribute-skeleton')
            ->assertSeeHtml('wire:target="search"');
    });

    it('reads the relationship pairs in a fixed number of queries however many fields there are', function (): void {
        $section = sectionForEntity(Post::class);

        $created = 0;

        $pairQueries = function (int $count) use ($section, &$created): int {
            while ($created < $count) {
                app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
                    code: 'pair_'.$created,
                    fromEntityType: Post::class,
                    toEntityType: Post::class,
                    cardinality: RelationshipCardinality::ManyToMany,
                    fromField: new FieldSlotData(name: 'Pair '.$created, sectionId: $section->getKey(), type: RelationshipFieldType::KEY),
                ));

                $created++;
            }

            $component = postFieldsTable()->instance();

            DB::flushQueryLog();
            DB::enableQueryLog();

            try {
                unset($component->relationshipPairs);

                expect($component->relationshipPairs())->toHaveCount($count);

                return count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }
        };

        // The first render that has a pair to resolve pays a one-time check, and the two
        // flavors reach that render at different points, so the count is read after it.
        $pairQueries(2);

        // The two field lists, the definitions, and one eager load for the populated slot.
        expect($pairQueries(2))->toBe(4)
            ->and($pairQueries(4))->toBe(4);
    });

    it('keeps the pre-redesign table in the native flavor', function (): void {
        config()->set('custom-fields.ui.flavor', 'native');

        CustomField::factory()->ofType('text')->create(['entity_type' => Post::class, 'name' => 'Owner']);

        postFieldsTable()
            ->assertDontSeeHtml('data-surface="attribute-table"')
            ->assertSee('Owner');
    });
});

describe('the type picker', function (): void {
    it('describes every field type the package ships', function (): void {
        $choices = containerisedTypeField()->getTypeChoices();

        expect($choices)->not->toBeEmpty();

        $missing = array_values(array_filter(
            $choices,
            static fn (array $choice): bool => $choice['description'] === null,
        ));

        expect($missing)->toBeEmpty(implode(', ', array_column($missing, 'key')));
    });

    it('offers only the types the consumer left on the field, in both flavors', function (string $flavor): void {
        config()->set('custom-fields.ui.flavor', $flavor);

        $field = containerisedTypeField(fn (TypeField $field): TypeField => $field
            ->options(['text' => 'Text', 'number' => 'Number', 'record' => 'Record'])
            ->disableOptionWhen(fn (string $value): bool => $value === 'record'));

        expect(array_column($field->getTypeChoices(), 'key'))->toBe(['text', 'number'])
            ->and(array_keys($field->getEnabledOptions()))->toBe(['text', 'number']);
    })->with(['polished', 'native']);

    it('renders the grid with a search box, icons and descriptions', function (): void {
        $html = renderTypePickerGrid(containerisedTypeField()->getTypeChoices());

        expect($html)
            ->toContain('data-surface="type-picker"')
            ->toContain('Search field types')
            ->toContain('role="radiogroup"')
            ->toContain('A single line of text.')
            ->toContain('dark:');
    });

    it('says the type is locked rather than offering a grid on an existing field', function (): void {
        $html = renderTypePickerGrid(containerisedTypeField()->getTypeChoices(), isDisabled: true);

        expect($html)
            ->toContain('A field keeps the type it was created with.')
            ->not->toContain('Search field types');
    });
});

/**
 * A TypeField reads its options through the schema it belongs to, so it is built inside one.
 *
 * @param  ?Closure(TypeField): TypeField  $configure
 */
function containerisedTypeField(?Closure $configure = null): TypeField
{
    $field = TypeField::make('type');

    if ($configure instanceof Closure) {
        $field = $configure($field);
    }

    $schema = Schema::make(livewire(ManageFieldsTable::class, ['entityType' => Post::class])->instance())
        ->statePath('data')
        ->components([$field]);

    // Filament containerises a component when the schema first resolves it, not when it is
    // handed over, so the field is read back from the schema rather than from the variable.
    $containerised = $schema->getComponent(fn (mixed $component): bool => $component instanceof TypeField, withHidden: true);

    expect($containerised)->toBeInstanceOf(TypeField::class);

    return $containerised;
}

/**
 * @param  array<int, array{key: string, label: string, icon: string, description: ?string}>  $choices
 */
function renderTypePickerGrid(array $choices, bool $isDisabled = false): string
{
    return view('custom-fields::flavors.polished.partials.type-picker-grid', [
        'choices' => $choices,
        'isDisabled' => $isDisabled,
        'label' => 'Type',
        'stateBinding' => "\$entangle('data.type')",
    ])->render();
}
