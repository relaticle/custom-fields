<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ListPosts;

function tableSurfaceDefinition(RelationshipCardinality $cardinality): CustomFieldRelationship
{
    registerPostLookupEntity();

    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'table_surface_related',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: $cardinality,
        fromField: new FieldSlotData(name: 'Related Post', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
    ));
}

it('filters posts by several record ids at once', function (): void {
    $definition = tableSurfaceDefinition(RelationshipCardinality::ManyToMany);
    $code = $definition->fromField->code;

    [$first, $second, $third] = Post::factory()->count(3)->create();

    $matchesFirst = Post::factory()->create(['custom_fields' => [$code => [$first->getKey()]]]);
    $matchesSecond = Post::factory()->create(['custom_fields' => [$code => [$second->getKey()]]]);
    $matchesThird = Post::factory()->create(['custom_fields' => [$code => [$third->getKey()]]]);

    livewire(ListPosts::class)
        ->set(sprintf('tableFilters.custom_fields.%s.values', $code), [$first->getKey(), $second->getKey()])
        ->assertCanSeeTableRecords([$matchesFirst, $matchesSecond])
        ->assertCanNotSeeTableRecords([$matchesThird]);
});

it('reads a record field from the to side of a paired definition', function (): void {
    registerPostLookupEntity();
    $section = sectionForEntity((new Post)->getMorphClass());

    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'table_surface_paired',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
        fromField: new FieldSlotData(name: 'Mentions', sectionId: $section->getKey()),
        toField: new FieldSlotData(name: 'Mentioned By', sectionId: $section->getKey()),
    ));

    $mentioned = Post::factory()->create();
    $mentions = Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$mentioned->getKey()]]]);
    $unrelated = Post::factory()->create();

    livewire(ListPosts::class)
        ->set(sprintf('tableFilters.custom_fields.%s.values', $definition->toField->code), [$mentions->getKey()])
        ->assertCanSeeTableRecords([$mentioned])
        ->assertCanNotSeeTableRecords([$mentions, $unrelated]);
});

it('sorts posts by the linked record title and puts unlinked rows last', function (): void {
    $definition = tableSurfaceDefinition(RelationshipCardinality::ManyToOne);
    $code = $definition->fromField->code;

    $alpha = Post::factory()->create(['title' => 'Alpha']);
    $bravo = Post::factory()->create(['title' => 'Bravo']);
    $charlie = Post::factory()->create(['title' => 'Charlie']);
    $unlinked = Post::factory()->create(['title' => 'Delta']);

    $alpha->update(['custom_fields' => [$code => [$charlie->getKey()]]]);
    $bravo->update(['custom_fields' => [$code => [$alpha->getKey()]]]);
    $charlie->update(['custom_fields' => [$code => [$bravo->getKey()]]]);

    livewire(ListPosts::class)
        ->sortTable('custom_fields.'.$code, 'asc')
        ->assertCanSeeTableRecords([$bravo, $charlie, $alpha, $unlinked], inOrder: true)
        ->sortTable('custom_fields.'.$code, 'desc')
        ->assertCanSeeTableRecords([$alpha, $charlie, $bravo, $unlinked], inOrder: true);
});

it('filters, sorts, and searches a symmetric definition from either end', function (): void {
    registerPostLookupEntity();

    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'table_surface_sibling',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: RelationshipCardinality::OneToOne,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Sibling', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
    ));

    $definition->fromField->update(['settings' => new CustomFieldSettingsData(searchable: true)]);

    $code = $definition->fromField->code;

    $left = Post::factory()->create(['title' => 'Aaa Left']);
    $right = Post::factory()->create(['title' => 'Zzz Right']);
    $lonely = Post::factory()->create(['title' => 'Mmm Lonely']);

    $left->update(['custom_fields' => [$code => [$right->getKey()]]]);

    livewire(ListPosts::class)
        ->set(sprintf('tableFilters.custom_fields.%s.values', $code), [$right->getKey()])
        ->assertCanSeeTableRecords([$left])
        ->assertCanNotSeeTableRecords([$lonely]);

    livewire(ListPosts::class)
        ->set(sprintf('tableFilters.custom_fields.%s.values', $code), [$left->getKey()])
        ->assertCanSeeTableRecords([$right])
        ->assertCanNotSeeTableRecords([$lonely]);

    livewire(ListPosts::class)
        ->sortTable('custom_fields.'.$code, 'asc')
        ->assertCanSeeTableRecords([$right, $left, $lonely], inOrder: true);

    livewire(ListPosts::class)
        ->searchTable('Zzz')
        ->assertCanSeeTableRecords([$left, $right])
        ->assertCanNotSeeTableRecords([$lonely]);
});

it('searches posts by the linked record title', function (): void {
    $definition = tableSurfaceDefinition(RelationshipCardinality::ManyToOne);
    $definition->fromField->update(['settings' => new CustomFieldSettingsData(searchable: true)]);

    $code = $definition->fromField->code;

    $target = Post::factory()->create(['title' => 'Findable Target']);
    $other = Post::factory()->create(['title' => 'Unrelated Target']);

    $match = Post::factory()->create(['title' => 'Host one', 'custom_fields' => [$code => [$target->getKey()]]]);
    $miss = Post::factory()->create(['title' => 'Host two', 'custom_fields' => [$code => [$other->getKey()]]]);

    livewire(ListPosts::class)
        ->searchTable('findable')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$miss, $other]);
});

it('hydrates and saves a record field through the panel form', function (): void {
    $definition = tableSurfaceDefinition(RelationshipCardinality::ManyToOne);
    $code = $definition->fromField->code;

    $first = Post::factory()->create(['title' => 'First Target']);
    $second = Post::factory()->create(['title' => 'Second Target']);
    $post = Post::factory()->create(['custom_fields' => [$code => [$first->getKey()]]]);

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertFormSet(['custom_fields' => [$code => [$first->getKey()]]])
        ->fillForm([
            'title' => $post->title,
            'author_id' => $post->author_id,
            'rating' => $post->rating,
            'custom_fields' => [$code => [$second->getKey()]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->refresh()->getCustomFieldValue($definition->fromField))->toBe([$second->getKey()])
        ->and(CustomFieldLink::query()->active()->count())->toBe(1)
        ->and(CustomFieldValue::query()->where('custom_field_id', $definition->from_field_id)->count())->toBe(0);
});
