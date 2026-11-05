<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Exceptions\UnsupportedThroughRelationException;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldFilterFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\FieldTypes\TernaryToggleFieldType;
use Relaticle\CustomFields\Tests\Fixtures\Livewire\ThroughTable;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

afterEach(function (): void {
    ThroughTable::$configureUsing = null;
});

function filterableField(string $type, string $code): CustomField
{
    return CustomField::factory()->create([
        'custom_field_section_id' => sectionForEntity(Post::class)->getKey(),
        'name' => ucfirst($code),
        'code' => $code,
        'type' => $type,
        'entity_type' => Post::class,
        'settings' => new CustomFieldSettingsData(visible_in_list: true, list_toggleable_hidden: false),
    ]);
}

function commentOn(Post $post): Comment
{
    return Comment::factory()->create(['post_id' => $post->getKey()]);
}

it('filters by a select field with and without a through path', function (): void {
    $field = filterableField('select', 'stage');

    $won = CustomFieldOption::factory()->create(['custom_field_id' => $field->getKey(), 'name' => 'Won', 'sort_order' => 1]);
    $lost = CustomFieldOption::factory()->create(['custom_field_id' => $field->getKey(), 'name' => 'Lost', 'sort_order' => 2]);

    $wonPost = Post::factory()->create();
    $wonPost->saveCustomFieldValue($field, $won->getKey());

    $lostPost = Post::factory()->create();
    $lostPost->saveCustomFieldValue($field, $lost->getKey());

    $onWon = commentOn($wonPost);
    $onLost = commentOn($lostPost);

    ownTable(Post::class, Post::class)
        ->set('tableFilters.custom_fields.stage.values', [$won->getKey()])
        ->assertCanSeeTableRecords([$wonPost])
        ->assertCanNotSeeTableRecords([$lostPost]);

    throughTable(Comment::class, Post::class, 'post')
        ->set('tableFilters.custom_fields.stage.values', [$won->getKey()])
        ->assertCanSeeTableRecords([$onWon])
        ->assertCanNotSeeTableRecords([$onLost]);
});

it('filters by a tags field with and without a through path', function (): void {
    $field = filterableField('tags-input', 'labels');

    $tagged = Post::factory()->create();
    $tagged->saveCustomFieldValue($field, ['urgent', 'blue']);

    $untagged = Post::factory()->create();
    $untagged->saveCustomFieldValue($field, ['calm']);

    $onTagged = commentOn($tagged);
    $onUntagged = commentOn($untagged);

    ownTable(Post::class, Post::class)
        ->set('tableFilters.custom_fields.labels.values', ['urgent'])
        ->assertCanSeeTableRecords([$tagged])
        ->assertCanNotSeeTableRecords([$untagged]);

    throughTable(Comment::class, Post::class, 'post')
        ->set('tableFilters.custom_fields.labels.values', ['urgent'])
        ->assertCanSeeTableRecords([$onTagged])
        ->assertCanNotSeeTableRecords([$onUntagged]);
});

it('filters by a ternary field with and without a through path', function (): void {
    CustomFieldsType::register(['ternary-toggle' => TernaryToggleFieldType::class]);

    $field = filterableField('ternary-toggle', 'archived');

    $archived = Post::factory()->create();
    $archived->saveCustomFieldValue($field, true);

    $active = Post::factory()->create();
    $active->saveCustomFieldValue($field, false);

    $onArchived = commentOn($archived);
    $onActive = commentOn($active);
    $orphan = Comment::factory()->create(['post_id' => Post::query()->max('id') + 1000]);

    ownTable(Post::class, Post::class)
        ->set('tableFilters.custom_fields.archived.value', true)
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$active]);

    throughTable(Comment::class, Post::class, 'post')
        ->set('tableFilters.custom_fields.archived.value', true)
        ->assertCanSeeTableRecords([$onArchived])
        ->assertCanNotSeeTableRecords([$onActive]);

    // A through filter asks about the related record, so a row without one answers neither
    // side of the ternary.
    throughTable(Comment::class, Post::class, 'post')
        ->set('tableFilters.custom_fields.archived.value', false)
        ->assertCanSeeTableRecords([$onActive])
        ->assertCanNotSeeTableRecords([$onArchived, $orphan]);
});

it('filters by a record field with and without a through path', function (): void {
    registerPostLookupEntity();

    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'through_filter_related',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToOne,
        fromField: new FieldSlotData(name: 'Related Post', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
    ));

    $code = $definition->fromField->code;

    $linked = Post::factory()->create(['title' => 'Linked Post']);
    $linking = Post::factory()->create(['custom_fields' => [$code => [$linked->getKey()]]]);
    $unlinked = Post::factory()->create();

    $onLinking = commentOn($linking);
    $onUnlinked = commentOn($unlinked);

    ownTable(Post::class, Post::class)
        ->set(sprintf('tableFilters.custom_fields.%s.values', $code), [$linked->getKey()])
        ->assertCanSeeTableRecords([$linking])
        ->assertCanNotSeeTableRecords([$unlinked]);

    throughTable(Comment::class, Post::class, 'post')
        ->set(sprintf('tableFilters.custom_fields.%s.values', $code), [$linked->getKey()])
        ->assertCanSeeTableRecords([$onLinking])
        ->assertCanNotSeeTableRecords([$onUnlinked]);
});

it('builds a filter for an unsupported relation and rejects it when the query runs', function (): void {
    $field = filterableField('select', 'stage');

    $filter = app(FieldFilterFactory::class)->create($field, 'commentable');

    expect(fn (): Builder => $filter->apply(Comment::query(), ['values' => [1]]))
        ->toThrow(UnsupportedThroughRelationException::class, 'is a MorphTo');
});
