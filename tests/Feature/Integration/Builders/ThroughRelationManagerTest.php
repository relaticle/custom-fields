<?php

declare(strict_types=1);

use Livewire\Features\SupportTesting\Testable;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\RelationManagers\CommentsRelationManager;

function commentsRelationManager(Post $post): Testable
{
    return livewire(CommentsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ]);
}

it('shows the owner record custom fields on rows that hold none', function (): void {
    $field = throughTextField(Post::class, 'category', 'Category');

    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Technology');

    $comments = Comment::factory()->count(3)->create(['post_id' => $post->getKey()]);

    $test = commentsRelationManager($post)
        ->assertCanSeeTableRecords($comments)
        ->assertTableColumnExists('custom_fields.category')
        ->assertCanRenderTableColumn('custom_fields.category');

    $comments->each(function (Comment $comment) use ($test): void {
        $test->assertTableColumnStateSet('custom_fields.category', 'Technology', $comment);
    });
});

it('renders empty for a field the owner record has no value for', function (): void {
    throughTextField(Post::class, 'category', 'Category');

    $post = Post::factory()->create();
    $comment = Comment::factory()->create(['post_id' => $post->getKey()]);

    commentsRelationManager($post)
        ->assertCanSeeTableRecords([$comment])
        ->assertTableColumnStateSet('custom_fields.category', null, $comment)
        ->assertTableColumnFormattedStateSet('custom_fields.category', null, $comment);
});

it('sorts relation manager rows by the owner record field', function (): void {
    $field = throughTextField(Post::class, 'category', 'Category');

    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Technology');

    $comments = Comment::factory()->count(3)->create(['post_id' => $post->getKey()]);

    commentsRelationManager($post)
        ->sortTable('custom_fields.category', 'asc')
        ->assertCanSeeTableRecords($comments)
        ->sortTable('custom_fields.category', 'desc')
        ->assertCanSeeTableRecords($comments);
});

it('filters relation manager rows by the owner record field', function (): void {
    $field = CustomField::factory()->create([
        'custom_field_section_id' => sectionForEntity(Post::class)->getKey(),
        'name' => 'Stage',
        'code' => 'stage',
        'type' => 'select',
        'entity_type' => Post::class,
        'settings' => new CustomFieldSettingsData(visible_in_list: true, list_toggleable_hidden: false),
    ]);

    $won = CustomFieldOption::factory()->create(['custom_field_id' => $field->getKey(), 'name' => 'Won', 'sort_order' => 1]);
    $lost = CustomFieldOption::factory()->create(['custom_field_id' => $field->getKey(), 'name' => 'Lost', 'sort_order' => 2]);

    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, $won->getKey());

    $comments = Comment::factory()->count(2)->create(['post_id' => $post->getKey()]);

    commentsRelationManager($post)
        ->set('tableFilters.custom_fields.stage.values', [$won->getKey()])
        ->assertCanSeeTableRecords($comments)
        ->set('tableFilters.custom_fields.stage.values', [$lost->getKey()])
        ->assertCanNotSeeTableRecords($comments);
});
