<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ViewPost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;
use Spatie\LaravelData\DataCollection;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can render page', function (): void {
    $this->get(PostResource::getUrl('view', [
        'record' => Post::factory()->create(),
    ]))->assertSuccessful();
});

it('can retrieve data', function (): void {
    $post = Post::factory()->create();

    livewire(ViewPost::class, [
        'record' => $post->getKey(),
    ])
        ->assertSchemaStateSet([
            'author_id' => $post->author->getKey(),
            'content' => $post->content,
            'tags' => $post->tags,
            'title' => $post->title,
        ]);
});

it('can refresh data', function (): void {
    $post = Post::factory()->create();

    $page = livewire(ViewPost::class, [
        'record' => $post->getKey(),
    ]);

    $originalPostTitle = $post->title;

    $page->assertSchemaStateSet([
        'title' => $originalPostTitle,
    ]);

    $newPostTitle = Str::random();

    $post->title = $newPostTitle;
    $post->save();

    $page->assertSchemaStateSet([
        'title' => $originalPostTitle,
    ]);

    $page->call('refreshTitle');

    $page->assertSchemaStateSet([
        'title' => $newPostTitle,
    ]);
});

describe('Conditional Visibility in Infolists', function (): void {
    beforeEach(function (): void {
        // Create custom field section for Posts
        $this->section = CustomFieldSection::factory()->create([
            'name' => 'Post Infolist Fields',
            'entity_type' => Post::class,
            'active' => true,
            'sort_order' => 1,
        ]);
    });

    it('shows custom field entries when show_when condition is met', function (): void {
        // Arrange - Create a base field and a conditional field
        $baseField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Status',
            'code' => 'status',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_view: true,
            ),
        ]);

        $conditionalField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Priority',
            'code' => 'priority',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_view: true,
                visibility: new VisibilityData(
                    mode: VisibilityMode::SHOW_WHEN,
                    logic: VisibilityLogic::ALL,
                    conditions: new DataCollection(VisibilityConditionData::class, [
                        new VisibilityConditionData(
                            field_code: 'status',
                            operator: VisibilityOperator::EQUALS,
                            value: 'published'
                        ),
                    ])
                )
            ),
        ]);

        $publishedPost = Post::factory()->create();
        $publishedPost->saveCustomFieldValue($baseField, 'published');
        $publishedPost->saveCustomFieldValue($conditionalField, 'high');

        $draftPost = Post::factory()->create();
        $draftPost->saveCustomFieldValue($baseField, 'draft');

        // Act & Assert - Published post should show both fields
        livewire(ViewPost::class, [
            'record' => $publishedPost->getKey(),
        ])
            ->assertSchemaComponentExists('custom_fields.status')
            ->assertSchemaComponentExists('custom_fields.priority')
            ->assertSchemaStateSet([
                'custom_fields.status' => 'published',
                'custom_fields.priority' => 'high',
            ]);

        // Draft post should only show base field, not conditional field
        livewire(ViewPost::class, [
            'record' => $draftPost->getKey(),
        ])
            ->assertSchemaComponentExists('custom_fields.status')
            ->assertSchemaComponentDoesNotExist('custom_fields.priority')
            ->assertSchemaStateSet([
                'custom_fields.status' => 'draft',
            ]);
    })->todo();

    it('hides custom field entries when hide_when condition is met', function (): void {
        // Arrange - Create a base field and a conditional field
        $baseField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Status',
            'code' => 'status',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_view: true,
            ),
        ]);

        $conditionalField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Internal Notes',
            'code' => 'internal_notes',
            'type' => 'textarea',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_view: true,
                visibility: new VisibilityData(
                    mode: VisibilityMode::HIDE_WHEN,
                    logic: VisibilityLogic::ALL,
                    conditions: new DataCollection(VisibilityConditionData::class, [
                        new VisibilityConditionData(
                            field_code: 'status',
                            operator: VisibilityOperator::EQUALS,
                            value: 'published'
                        ),
                    ])
                )
            ),
        ]);

        $publishedPost = Post::factory()->create();
        $publishedPost->saveCustomFieldValue($baseField, 'published');
        // Don't save internal notes for published post - it should be hidden anyway

        $draftPost = Post::factory()->create();
        $draftPost->saveCustomFieldValue($baseField, 'draft');
        $draftPost->saveCustomFieldValue($conditionalField, 'Internal review needed');

        // Act & Assert - Published post should hide conditional field
        livewire(ViewPost::class, [
            'record' => $publishedPost->getKey(),
        ])
            ->assertSchemaComponentExists('custom_fields.status')
            ->assertSchemaComponentDoesNotExist('custom_fields.internal_notes')
            ->assertSchemaStateSet([
                'custom_fields.status' => 'published',
            ]);

        // Draft post should show both fields
        livewire(ViewPost::class, [
            'record' => $draftPost->getKey(),
        ])
            ->assertSchemaComponentExists('custom_fields.status')
            ->assertSchemaComponentExists('custom_fields.internal_notes')
            ->assertSchemaStateSet([
                'custom_fields.status' => 'draft',
                'custom_fields.internal_notes' => 'Internal review needed',
            ]);
    })->todo();
});
