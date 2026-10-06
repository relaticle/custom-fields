<?php

declare(strict_types=1);

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Livewire\Features\SupportTesting\Testable;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RecordFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RelationshipFieldType;
use Relaticle\CustomFields\Filament\Management\Forms\Components\RelationshipConfigurator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

beforeEach(function (): void {
    $this->postSection = CustomFieldSection::factory()->forEntityType(Post::class)->create();
});

function mountFieldOfType(CustomFieldSection $section, string $type): Testable
{
    return livewire(ManageCustomFieldSection::class, [
        'section' => $section,
        'entityType' => Post::class,
    ])
        ->mountAction('createField')
        ->set('mountedActions.0.data.type', $type)
        ->set('mountedActions.0.data.name', 'Related Comment')
        ->set('mountedActions.0.data.code', 'related_comment')
        ->set('mountedActions.0.data.relationship.target_entity_type', Comment::class);
}

/**
 * What the user is actually offered: the two frames are mutually exclusive, so a hidden one
 * takes its children out of this list.
 *
 * @return array<int, string>
 */
function visibleRelationshipInputs(Testable $component): array
{
    $livewire = $component->instance();
    $schema = $livewire->getSchema($livewire->getMountedActionSchemaName());

    $names = [];

    foreach ($schema?->getFlatComponents() ?? [] as $child) {
        if ($child instanceof Field && str_starts_with($child->getName(), 'relationship.')) {
            $names[] = $child->getName();
        }
    }

    return $names;
}

function visibleConfigurator(Testable $component): ?RelationshipConfigurator
{
    $livewire = $component->instance();

    return $livewire
        ->getSchema($livewire->getMountedActionSchemaName())
        ?->getComponent(fn (mixed $child): bool => $child instanceof RelationshipConfigurator);
}

function oneWayComments(CustomFieldSection $section, RelationshipCardinality $cardinality): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'related_comment',
        fromEntityType: Post::class,
        toEntityType: Comment::class,
        cardinality: $cardinality,
        fromField: new FieldSlotData(name: 'Related Comment', sectionId: $section->getKey()),
    ));
}

describe('the record face', function (): void {
    it('asks where the field points and how many records it holds, in both flavors', function (string $flavor): void {
        config()->set('custom-fields.ui.flavor', $flavor);

        $component = mountFieldOfType($this->postSection, RecordFieldType::KEY);

        expect(visibleRelationshipInputs($component))->toBe([
            'relationship.target_entity_type',
            'relationship.allow_multiple',
        ])->and(visibleConfigurator($component))->toBeNull();
    })->with(['polished', 'native']);

    it('gives the relationship type the configurator the record type never shows', function (): void {
        config()->set('custom-fields.ui.flavor', 'polished');

        expect(visibleConfigurator(mountFieldOfType($this->postSection, RelationshipFieldType::KEY)))
            ->toBeInstanceOf(RelationshipConfigurator::class)
            ->and(visibleConfigurator(mountFieldOfType($this->postSection, RecordFieldType::KEY)))
            ->toBeNull();
    });

    it('writes a one-slot definition holding one record while multiple is off', function (): void {
        mountFieldOfType($this->postSection, RecordFieldType::KEY)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->cardinality)->toBe(RelationshipCardinality::ManyToOne)
            ->and($definition->to_field_id)->toBeNull()
            ->and($definition->is_symmetric)->toBeFalse()
            ->and($definition->fromField->type)->toBe(RecordFieldType::KEY)
            ->and($definition->fromField->allowsMultipleRecords())->toBeFalse();
    });

    it('writes a one-slot definition holding many records while multiple is on', function (): void {
        mountFieldOfType($this->postSection, RecordFieldType::KEY)
            ->set('mountedActions.0.data.relationship.allow_multiple', true)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->cardinality)->toBe(RelationshipCardinality::ManyToMany)
            ->and($definition->to_field_id)->toBeNull()
            ->and($definition->fromField->allowsMultipleRecords())->toBeTrue();
    });

    it('fills the toggle from the cardinality the definition holds', function (): void {
        $definition = oneWayComments($this->postSection, RelationshipCardinality::ManyToMany);

        livewire(ManageCustomField::class, ['field' => $definition->fromField])
            ->mountAction('edit')
            ->assertActionDataSet([
                'relationship.target_entity_type' => Comment::class,
                'relationship.allow_multiple' => true,
            ]);
    });

    it('locks the entity the field points at once it exists', function (): void {
        $definition = oneWayComments($this->postSection, RelationshipCardinality::ManyToOne);

        livewire(ManageCustomField::class, ['field' => $definition->fromField])
            ->mountAction('edit')
            ->assertSchemaComponentExists(
                'relationship.target_entity_type',
                checkComponentUsing: fn (Select $component): bool => $component->isDisabled(),
            );
    });

    it('confirms the keep-first before it stops holding many records', function (): void {
        $definition = oneWayComments($this->postSection, RelationshipCardinality::ManyToMany);
        $field = $definition->fromField;
        [$first, $second] = Comment::factory()->count(2)->create();
        $post = Post::factory()->create(['custom_fields' => [$field->code => [$first->getKey(), $second->getKey()]]]);

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->set('mountedActions.0.data.relationship.allow_multiple', false)
            ->assertSchemaComponentVisible('relationship.keep_first')
            ->callMountedAction()
            ->assertHasActionErrors(['relationship.keep_first']);

        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToMany);

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->set('mountedActions.0.data.relationship.allow_multiple', false)
            ->set('mountedActions.0.data.relationship.keep_first', true)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToOne)
            ->and($post->fresh()->getCustomFieldValue($field->fresh()))->toBe([$first->getKey()])
            ->and(CustomFieldLink::query()->whereNotNull('active_until')->count())->toBe(1);
    });

    it('keeps the far end where it is when a save only renames the field', function (string $cardinality): void {
        $definition = oneWayComments($this->postSection, RelationshipCardinality::from($cardinality));

        livewire(ManageCustomField::class, ['field' => $definition->fromField])
            ->mountAction('edit')
            ->set('mountedActions.0.data.name', 'Renamed')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($definition->refresh()->cardinality->value)->toBe($cardinality)
            ->and($definition->fromField->name)->toBe('Renamed');
    })->with([
        RelationshipCardinality::OneToOne->value,
        RelationshipCardinality::OneToMany->value,
        RelationshipCardinality::ManyToOne->value,
        RelationshipCardinality::ManyToMany->value,
    ]);

    it('holds many records without freeing the end that holds one', function (): void {
        $definition = oneWayComments($this->postSection, RelationshipCardinality::OneToOne);

        livewire(ManageCustomField::class, ['field' => $definition->fromField])
            ->mountAction('edit')
            ->set('mountedActions.0.data.relationship.allow_multiple', true)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::OneToMany);

        livewire(ManageCustomField::class, ['field' => $definition->fromField->fresh()])
            ->mountAction('edit')
            ->set('mountedActions.0.data.relationship.allow_multiple', false)
            ->set('mountedActions.0.data.relationship.keep_first', true)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::OneToOne);
    });

    it('keeps every record when the field goes on holding many', function (): void {
        $definition = oneWayComments($this->postSection, RelationshipCardinality::ManyToMany);
        $field = $definition->fromField;
        $comments = Comment::factory()->count(2)->create();
        $post = Post::factory()->create(['custom_fields' => [$field->code => $comments->modelKeys()]]);

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->assertSchemaComponentHidden('relationship.keep_first')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToMany)
            ->and($post->fresh()->getCustomFieldValue($field->fresh()))->toBe($comments->modelKeys());
    });
});
