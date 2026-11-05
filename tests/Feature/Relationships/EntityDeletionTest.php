<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ListPosts;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ViewPost;

function deletionMentions(): CustomFieldRelationship
{
    registerPostLookupEntity();

    $section = sectionForEntity((new Post)->getMorphClass());

    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'deletion_mentions',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
        fromField: new FieldSlotData(name: 'Mentions', sectionId: $section->getKey()),
        toField: new FieldSlotData(name: 'Mentioned By', sectionId: $section->getKey()),
    ));
}

function deletionCommentary(): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'deletion_commentary',
        fromEntityType: (new Comment)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
        fromField: new FieldSlotData(name: 'About', sectionId: sectionForEntity((new Comment)->getMorphClass())->getKey()),
    ));
}

it('sweeps both ends and the history when a record is force deleted', function (): void {
    $definition = deletionMentions();
    $code = $definition->fromField->code;

    $subject = Post::factory()->create();
    [$mentioned, $closed, $other] = Post::factory()->count(3)->create();

    $subject->update(['custom_fields' => [$code => [$closed->getKey()]]]);
    $subject->update(['custom_fields' => [$code => [$mentioned->getKey()]]]);
    Post::factory()->create(['custom_fields' => [$code => [$subject->getKey()]]]);
    $unrelated = Post::factory()->create(['custom_fields' => [$code => [$other->getKey()]]]);

    expect(CustomFieldLink::query()->count())->toBe(4);

    $subject->forceDelete();

    expect(CustomFieldLink::query()->count())->toBe(1)
        ->and(CustomFieldLink::query()->sole()->from_entity_id)->toEqual($unrelated->getKey());
});

it('sweeps the edges of a record whose model deletes outright', function (): void {
    $definition = deletionCommentary();
    $post = Post::factory()->create();

    $comment = Comment::factory()->create(['custom_fields' => [$definition->fromField->code => [$post->getKey()]]]);

    expect(CustomFieldLink::query()->count())->toBe(1);

    $comment->delete();

    expect(CustomFieldLink::query()->count())->toBe(0);
});

it('keeps every edge when a record is soft deleted', function (): void {
    $definition = deletionMentions();
    $code = $definition->fromField->code;

    $mentioned = Post::factory()->create();
    $post = Post::factory()->create(['custom_fields' => [$code => [$mentioned->getKey()]]]);

    $post->delete();

    expect(CustomFieldLink::query()->active()->count())->toBe(1)
        ->and($post->getCustomFieldValue($definition->fromField))->toBe([$mentioned->getKey()]);
});

it('keeps the edges of a soft deleted target and reads them again after a restore', function (): void {
    $definition = deletionMentions();
    $code = $definition->fromField->code;

    $mentioned = Post::factory()->create();
    $post = Post::factory()->create(['custom_fields' => [$code => [$mentioned->getKey()]]]);

    $mentioned->delete();
    $mentioned->restore();

    expect(CustomFieldLink::query()->active()->count())->toBe(1)
        ->and($post->getCustomFieldValue($definition->fromField))->toBe([$mentioned->getKey()]);
});

it('skips a trashed end in the table column and the infolist entry', function (): void {
    $definition = deletionMentions();
    $code = $definition->fromField->code;

    $kept = Post::factory()->create(['title' => 'Kept Target']);
    $trashed = Post::factory()->create(['title' => 'Trashed Target']);
    $post = Post::factory()->create(['title' => 'Mentioning Host', 'custom_fields' => [$code => [$kept->getKey(), $trashed->getKey()]]]);

    $trashed->delete();

    livewire(ListPosts::class)
        ->assertSee('Kept Target')
        ->assertDontSee('Trashed Target');

    livewire(ViewPost::class, ['record' => $post->getRouteKey()])
        ->assertSee('Kept Target')
        ->assertDontSee('Trashed Target');
});

it('skips a trashed end in the record select options', function (): void {
    registerPostLookupEntity();

    $kept = Post::factory()->create(['title' => 'Kept Target']);
    $trashed = Post::factory()->create(['title' => 'Trashed Target']);

    $trashed->delete();

    $ids = [(string) $kept->getKey(), (string) $trashed->getKey()];

    expect(array_column(recordSelectFor(Post::class)->getRecordsByIds($ids), 'label'))->toBe(['Kept Target'])
        ->and(array_column(recordSelectInitialOptions(), 'label'))->not->toContain('Trashed Target');
});

it('keeps a record saveable while one of its targets is trashed', function (): void {
    $definition = deletionMentions();
    $code = $definition->fromField->code;

    $kept = Post::factory()->create();
    $trashed = Post::factory()->create();
    $post = Post::factory()->create(['custom_fields' => [$code => [$kept->getKey(), $trashed->getKey()]]]);

    $trashed->delete();

    $post->update(['custom_fields' => [$code => [$kept->getKey(), $trashed->getKey()]]]);

    expect(CustomFieldLink::query()->active()->count())->toBe(2);
});

it('counts an active edge as a value of the relationship slot', function (): void {
    $definition = deletionMentions();
    $code = $definition->fromField->code;

    $mentioned = Post::factory()->create();

    expect($definition->fromField->hasValues())->toBeFalse();

    $post = Post::factory()->create(['custom_fields' => [$code => [$mentioned->getKey()]]]);

    expect($definition->fromField->hasValues())->toBeTrue();

    $post->update(['custom_fields' => [$code => []]]);

    expect($definition->fromField->hasValues())->toBeFalse();
});
