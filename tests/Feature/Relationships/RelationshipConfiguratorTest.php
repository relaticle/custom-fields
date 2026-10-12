<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Features\SupportTesting\Testable;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\EntitySystem\EntityConfigurator;
use Relaticle\CustomFields\EntitySystem\EntityManager;
use Relaticle\CustomFields\EntitySystem\EntityModel;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RelationshipFieldType;
use Relaticle\CustomFields\Filament\Management\Forms\Components\RelationshipConfigurator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

beforeEach(function (): void {
    $this->postSection = CustomFieldSection::factory()->forEntityType(Post::class)->create();
    $this->commentSection = CustomFieldSection::factory()->forEntityType(Comment::class)->create();
});

function mountRelationshipField(CustomFieldSection $section, ?string $targetEntityType = Comment::class): Testable
{
    $component = livewire(ManageCustomFieldSection::class, [
        'section' => $section,
        'entityType' => Post::class,
    ])
        ->mountAction('createField')
        ->set('mountedActions.0.data.type', RelationshipFieldType::KEY)
        ->set('mountedActions.0.data.name', 'Related Comment')
        ->set('mountedActions.0.data.code', 'related_comment');

    return $component->set('mountedActions.0.data.relationship.target_entity_type', $targetEntityType);
}

/**
 * A host whose entity labels are written for Filament's sentence use, where "Create
 * opportunity" is right and an entity card heading reading "opportunity" is not.
 */
function registerLowercaseLabelledEntities(): void
{
    config()->set('custom-fields.entity_configuration',
        EntityConfigurator::configure()
            ->autoDiscover(false)
            ->cache(false)
            ->models([
                EntityModel::configure(
                    modelClass: Post::class,
                    labelSingular: 'post',
                    labelPlural: 'posts',
                    features: [EntityFeature::CUSTOM_FIELDS, EntityFeature::LOOKUP_SOURCE],
                ),
                EntityModel::configure(
                    modelClass: Comment::class,
                    labelSingular: 'comment',
                    labelPlural: 'comments',
                    features: [EntityFeature::CUSTOM_FIELDS, EntityFeature::LOOKUP_SOURCE],
                ),
            ])
    );

    app()->forgetInstance(EntityManager::class);
}

function mountedConfigurator(Testable $component): ?RelationshipConfigurator
{
    $livewire = $component->instance();

    return $livewire
        ->getSchema($livewire->getMountedActionSchemaName())
        ?->getComponent(fn (mixed $component): bool => $component instanceof RelationshipConfigurator, withHidden: true);
}

describe('flavors', function (): void {
    it('frames the record configuration in the polished configurator', function (): void {
        config()->set('custom-fields.ui.flavor', 'polished');

        $configurator = mountedConfigurator(mountRelationshipField($this->postSection));

        expect($configurator)->not->toBeNull()
            ->and(array_keys($configurator->getConfiguredFields()))->toBe([
                'relationship.target_entity_type',
                'relationship.cardinality',
                'relationship.paired_field_name',
            ]);
    });

    it('keeps the stock fieldset in the native flavor', function (): void {
        config()->set('custom-fields.ui.flavor_overrides', ['relationship-configurator' => 'native']);

        $component = mountRelationshipField($this->postSection);

        expect(mountedConfigurator($component))->toBeNull();

        $component->assertSchemaComponentExists('relationship.target_entity_type')
            ->assertSchemaComponentExists('relationship.cardinality');
    });

    it('stores the same definition whichever flavor collected it', function (string $flavor): void {
        config()->set('custom-fields.ui.flavor', $flavor);

        mountRelationshipField($this->postSection)
            ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToMany->value)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->cardinality)->toBe(RelationshipCardinality::ManyToMany)
            ->and($definition->from_entity_type)->toBe(Post::class)
            ->and($definition->to_entity_type)->toBe(Comment::class);
    })->with(['polished', 'native']);
});

describe('create defaults', function (): void {
    it('opens the relationship configuration on a target and a cardinality', function (): void {
        $component = livewire(ManageCustomFieldSection::class, [
            'section' => $this->postSection,
            'entityType' => Post::class,
        ])
            ->mountAction('createField')
            ->set('mountedActions.0.data.type', RelationshipFieldType::KEY);

        expect($component->get('mountedActions.0.data.entity_type'))->toBe(Post::class)
            ->and($component->get('mountedActions.0.data.relationship.target_entity_type'))->toBe(Post::class)
            ->and($component->get('mountedActions.0.data.relationship.cardinality'))
            ->toBe(RelationshipCardinality::ManyToOne->value);
    });

    it('writes the definition those defaults describe without a second choice', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->postSection,
            'entityType' => Post::class,
        ])
            ->mountAction('createField')
            ->set('mountedActions.0.data.type', RelationshipFieldType::KEY)
            ->set('mountedActions.0.data.name', 'Related Post')
            ->set('mountedActions.0.data.code', 'related_post')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->cardinality)->toBe(RelationshipCardinality::ManyToOne)
            ->and($definition->from_entity_type)->toBe(Post::class)
            ->and($definition->to_entity_type)->toBe(Post::class);
    });
});

describe('the cardinality sentence', function (): void {
    // The sentence is the polished configurator's own reading of the state, so this block
    // asserts one flavor whichever one the run is configured for.
    beforeEach(fn () => config()->set('custom-fields.ui.flavor', 'polished'));

    it('names both ends in the words the cardinality means', function (string $cardinality, string $sentence): void {
        $component = mountRelationshipField($this->postSection)
            ->set('mountedActions.0.data.relationship.cardinality', $cardinality);

        expect(mountedConfigurator($component)?->getCardinalitySentence())->toBe($sentence);
    })->with([
        [RelationshipCardinality::OneToOne->value, 'One Post links to one Comment.'],
        [RelationshipCardinality::OneToMany->value, 'One Post links to many Comments.'],
        [RelationshipCardinality::ManyToOne->value, 'Many Posts link to one Comment.'],
        [RelationshipCardinality::ManyToMany->value, 'Many Posts link to many Comments.'],
    ]);

    it('opens each name with a capital however the host cased its labels', function (string $cardinality, string $sentence): void {
        registerLowercaseLabelledEntities();

        $component = mountRelationshipField($this->postSection)
            ->set('mountedActions.0.data.relationship.cardinality', $cardinality);

        expect(mountedConfigurator($component)?->getCardinalitySentence())->toBe($sentence);
    })->with([
        [RelationshipCardinality::OneToOne->value, 'One Post links to one Comment.'],
        [RelationshipCardinality::ManyToOne->value, 'Many Posts link to one Comment.'],
        [RelationshipCardinality::OneToMany->value, 'One Post links to many Comments.'],
    ]);

    it('has no sentence to read until both ends are chosen', function (): void {
        $component = mountRelationshipField($this->postSection, targetEntityType: null)
            ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToOne->value);

        expect(mountedConfigurator($component)?->getCardinalitySentence())->toBeNull();
    });

    it('mirrors the field name from the shared grid rather than asking for it twice', function (): void {
        $configurator = mountedConfigurator(mountRelationshipField($this->postSection));

        expect($configurator?->getFieldName())->toBe('Related Comment')
            ->and($configurator?->getFieldNameStatePath())->toBe('mountedActions.0.data.name');
    });
});

describe('polished markup', function (): void {
    beforeEach(fn () => config()->set('custom-fields.ui.flavor', 'polished'));

    it('renders both entity cards, the sync banner and the sentence', function (): void {
        $html = view('custom-fields::flavors.polished.relationship-configurator', [
            'attributes' => new ComponentAttributeBag,
            'getId' => fn (): string => 'configurator',
            'getExtraAttributes' => fn (): array => [],
            'getConfiguredFields' => fn (): array => [],
            'getSourceEntity' => fn () => Entities::getEntity(Post::class),
            'getTargetEntity' => fn () => Entities::getEntity(Comment::class),
            'getCardinalitySentence' => fn (): string => 'Many Posts link to one Comment.',
            'getFieldName' => fn (): string => 'Related Comment',
            'getFieldNameStatePath' => fn (): string => 'data.name',
            'isSymmetric' => fn (): bool => false,
            'pairsAField' => fn (): bool => false,
        ])->render();

        expect($html)
            ->toContain('data-surface="relationship-configurator"')
            ->toContain('data-flavor="polished"')
            ->toContain('This entity')
            ->toContain('Related entity')
            ->toContain('Many Posts link to one Comment.')
            ->toContain('One-way field')
            ->toContain('Related Comment')
            ->toContain('dark:');
    });

    it('renders the shared children inside the polished frame', function (): void {
        // The error bag is a request-scoped view variable a field wrapper reads, and the
        // schema is rendered here outside one.
        view()->share('errors', new ViewErrorBag);

        $configurator = mountedConfigurator(
            mountRelationshipField($this->postSection)
                ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToOne->value)
        );

        $html = (string) $configurator?->toHtml();

        expect($html)
            ->toContain('data-surface="relationship-configurator"')
            ->toContain('Many Posts link to one Comment.')
            ->toContain('relationship.target_entity_type')
            ->toContain('relationship.cardinality')
            ->toContain('relationship.paired_field_name');
    });

    it('heads each entity card with a capital however the host cased its labels', function (): void {
        registerLowercaseLabelledEntities();

        $html = view('custom-fields::flavors.polished.relationship-configurator', [
            'attributes' => new ComponentAttributeBag,
            'getId' => fn (): string => 'configurator',
            'getExtraAttributes' => fn (): array => [],
            'getConfiguredFields' => fn (): array => [],
            'getSourceEntity' => fn () => Entities::getEntity(Post::class),
            'getTargetEntity' => fn () => Entities::getEntity(Comment::class),
            'getCardinalitySentence' => fn (): ?string => null,
            'getFieldName' => fn (): string => 'Related Comment',
            'getFieldNameStatePath' => fn (): string => 'data.name',
            'isSymmetric' => fn (): bool => false,
            'pairsAField' => fn (): bool => false,
        ])->render();

        $compact = (string) preg_replace(['/>\s+/', '/\s+</'], ['>', '<'], $html);

        expect($compact)
            ->toContain('>Post<')
            ->toContain('>Comment<')
            ->not->toContain('>post<')
            ->not->toContain('>comment<');
    });

    it('says the two fields stay in sync once the other side is named', function (): void {
        $html = view('custom-fields::flavors.polished.relationship-configurator', [
            'attributes' => new ComponentAttributeBag,
            'getId' => fn (): string => 'configurator',
            'getExtraAttributes' => fn (): array => [],
            'getConfiguredFields' => fn (): array => [],
            'getSourceEntity' => fn () => Entities::getEntity(Post::class),
            'getTargetEntity' => fn () => Entities::getEntity(Post::class),
            'getCardinalitySentence' => fn (): ?string => null,
            'getFieldName' => fn (): string => 'Related Post',
            'getFieldNameStatePath' => fn (): string => 'data.name',
            'isSymmetric' => fn (): bool => true,
            'pairsAField' => fn (): bool => false,
        ])->render();

        expect($html)
            ->toContain('One field read from both ends')
            ->toContain('Pick a related entity to see how the two sides connect.');
    });
});

describe('submission', function (): void {
    it('stores the definition the sentence describes', function (string $cardinality): void {
        mountRelationshipField($this->postSection)
            ->set('mountedActions.0.data.relationship.cardinality', $cardinality)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->cardinality->value)->toBe($cardinality)
            ->and($definition->to_field_id)->toBeNull();
    })->with([
        RelationshipCardinality::OneToOne->value,
        RelationshipCardinality::OneToMany->value,
        RelationshipCardinality::ManyToOne->value,
        RelationshipCardinality::ManyToMany->value,
    ]);

    it('adds the paired field the related-entity card names', function (): void {
        mountRelationshipField($this->postSection)
            ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToMany->value)
            ->set('mountedActions.0.data.relationship.paired_field_name', 'Related Post')
            ->set('mountedActions.0.data.relationship.paired_section_id', $this->commentSection->getKey())
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->toField->name)->toBe('Related Post')
            ->and($definition->toField->entity_type)->toBe(Comment::class);
    });

    it('collapses to one field when the toggle makes the relationship symmetric', function (): void {
        mountRelationshipField($this->postSection, targetEntityType: Post::class)
            ->set('mountedActions.0.data.relationship.is_symmetric', true)
            ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToMany->value)
            ->assertSchemaComponentHidden('relationship.paired_field_name')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->is_symmetric)->toBeTrue()
            ->and($definition->to_field_id)->toBe($definition->from_field_id)
            ->and(CustomField::query()->count())->toBe(1);
    });

    it('reports a record configuration with nowhere to point', function (): void {
        mountRelationshipField($this->postSection, targetEntityType: null)
            ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToOne->value)
            ->callMountedAction()
            ->assertHasActionErrors(['relationship.target_entity_type' => 'required']);

        expect(CustomFieldRelationship::query()->count())->toBe(0);
    });

    it('asks for a cardinality before it writes a definition', function (): void {
        mountRelationshipField($this->postSection)
            ->set('mountedActions.0.data.relationship.cardinality')
            ->callMountedAction()
            ->assertHasActionErrors(['relationship.cardinality' => 'required']);

        expect(CustomFieldRelationship::query()->count())->toBe(0);
    });

    it('refuses a machine code another field on the entity already answers to', function (): void {
        CustomField::factory()->ofType('text')->create([
            'entity_type' => Post::class,
            'code' => 'related_comment',
        ]);

        mountRelationshipField($this->postSection)
            ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToOne->value)
            ->callMountedAction()
            ->assertHasActionErrors(['code' => 'unique']);
    });
});

describe('lifecycle', function (): void {
    it('refuses to narrow the cardinality without the keep-first confirmation', function (): void {
        $definition = manyToManyComments($this->postSection);

        livewire(ManageCustomField::class, ['field' => $definition->fromField])
            ->mountAction('edit')
            ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToOne->value)
            ->assertSchemaComponentVisible('relationship.keep_first')
            ->callMountedAction()
            ->assertHasActionErrors(['relationship.keep_first']);

        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToMany);
    });

    it('locks both ends once the definition exists', function (): void {
        $definition = manyToManyComments($this->postSection);

        livewire(ManageCustomField::class, ['field' => $definition->fromField])
            ->mountAction('edit')
            ->assertSchemaComponentHidden('relationship.is_symmetric')
            ->assertSchemaComponentExists(
                'relationship.target_entity_type',
                checkComponentUsing: fn (Select $component): bool => $component->isDisabled(),
            );
    });
});

describe('keyboard and disclosure', function (): void {
    it('submits the field form from the keyboard', function (): void {
        $component = livewire(ManageCustomFieldSection::class, [
            'section' => $this->postSection,
            'entityType' => Post::class,
        ])->mountAction('createField');

        $attributes = $component->instance()->getMountedActions()[0]->getExtraModalWindowAttributes();

        expect($attributes)->toHaveKeys(['x-on:keydown.meta.enter.prevent', 'x-on:keydown.ctrl.enter.prevent'])
            ->and($attributes['x-on:keydown.meta.enter.prevent'])->toBe('$el.requestSubmit()');
    });

    it('keeps the machine code behind an advanced disclosure', function (): void {
        $component = livewire(ManageCustomFieldSection::class, [
            'section' => $this->postSection,
            'entityType' => Post::class,
        ])->mountAction('createField');

        $livewire = $component->instance();
        $advanced = $livewire
            ->getSchema($livewire->getMountedActionSchemaName())
            ?->getComponent(fn (mixed $component): bool => $component instanceof Section
                && $component->getHeading() === 'Advanced', withHidden: true);

        expect($advanced)->not->toBeNull()
            ->and($advanced->isCollapsed())->toBeTrue();

        $component->assertSchemaComponentExists('code');
    });
});

function manyToManyComments(CustomFieldSection $section): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'related_comment',
        fromEntityType: Post::class,
        toEntityType: Comment::class,
        cardinality: RelationshipCardinality::ManyToMany,
        fromField: new FieldSlotData(name: 'Related Comment', sectionId: $section->getKey(), type: RelationshipFieldType::KEY),
    ));
}
