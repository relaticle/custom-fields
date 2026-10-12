<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RecordFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RelationshipFieldType;
use Relaticle\CustomFields\Filament\Management\Forms\Components\TypeField;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

function linkingFieldOfType(string $type): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'authorship_'.$type,
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
        fromField: new FieldSlotData(
            name: 'Authors '.$type,
            sectionId: sectionForEntity((new Post)->getMorphClass())->getKey(),
            type: $type,
        ),
    ));
}

/**
 * @return array<int, int|string>
 */
function activeLinkTargets(): array
{
    return CustomFieldLink::query()->active()->orderBy('sort_order')->pluck('to_entity_id')->all();
}

it('creates, reads and clears links for both linking field types', function (string $type): void {
    $definition = linkingFieldOfType($type);
    $field = $definition->fromField;
    $user = User::factory()->create();

    $post = Post::factory()->create(['custom_fields' => [$field->code => [$user->getKey()]]]);

    expect($field->type)->toBe($type)
        ->and(activeLinkTargets())->toEqual([(string) $user->getKey()])
        ->and($post->fresh()->getCustomFieldValue($field))->toEqual([$user->getKey()]);

    $post->update(['custom_fields' => [$field->code => []]]);

    expect(activeLinkTargets())->toBe([]);
})->with([RecordFieldType::KEY, RelationshipFieldType::KEY]);

it('registers both linking types with the pairing capability telling them apart', function (): void {
    $record = CustomFieldsType::getFieldType(RecordFieldType::KEY);
    $relationship = CustomFieldsType::getFieldType(RelationshipFieldType::KEY);

    expect($record?->requiresRelationship)->toBeTrue()
        ->and($record?->supportsPairing)->toBeFalse()
        ->and($relationship?->requiresRelationship)->toBeTrue()
        ->and($relationship?->supportsPairing)->toBeTrue();
});

it('lists both linking types in the type picker, each with its own description', function (): void {
    $schema = Schema::make(livewire(ManageFieldsTable::class, ['entityType' => Post::class])->instance())
        ->statePath('data')
        ->components([TypeField::make('type')]);

    $field = $schema->getComponent(fn (mixed $component): bool => $component instanceof TypeField, withHidden: true);

    expect($field)->toBeInstanceOf(TypeField::class);

    $choices = collect($field->getTypeChoices())->keyBy('key');

    expect($choices->has(RecordFieldType::KEY))->toBeTrue()
        ->and($choices->has(RelationshipFieldType::KEY))->toBeTrue()
        ->and($choices[RecordFieldType::KEY]['description'])->toBe('A one-way link to records of another entity.')
        ->and($choices[RelationshipFieldType::KEY]['description'])->toBe('A two-way link, with a matching field on the other entity.');
});

it('refuses a slot rendered by a field type that stores no links', function (): void {
    expect(fn (): CustomFieldRelationship => linkingFieldOfType('text'))
        ->toThrow(InvalidArgumentException::class, 'cannot render the [text] field type');
});
