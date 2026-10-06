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
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Comments\Pages\ViewComment;
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
        $this->section = CustomFieldSection::factory()->create([
            'name' => 'Comment Infolist Fields',
            'entity_type' => Comment::class,
            'active' => true,
            'sort_order' => 1,
        ]);

        $this->statusField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Status',
            'code' => 'status',
            'type' => 'text',
            'entity_type' => Comment::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_view: true,
            ),
        ]);

        $this->conditionalField = function (string $name, string $code, VisibilityMode $mode): CustomField {
            return CustomField::factory()->create([
                'custom_field_section_id' => $this->section->id,
                'name' => $name,
                'code' => $code,
                'type' => 'text',
                'entity_type' => Comment::class,
                'settings' => new CustomFieldSettingsData(
                    visible_in_view: true,
                    visibility: new VisibilityData(
                        mode: $mode,
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
        };
    });

    it('shows custom field entries when show_when condition is met', function (): void {
        $conditionalField = ($this->conditionalField)('Priority', 'priority', VisibilityMode::SHOW_WHEN);

        $published = Comment::factory()->create();
        $published->saveCustomFieldValue($this->statusField, 'published');
        $published->saveCustomFieldValue($conditionalField, 'high');

        $draft = Comment::factory()->create();
        $draft->saveCustomFieldValue($this->statusField, 'draft');
        $draft->saveCustomFieldValue($conditionalField, 'high');

        livewire(ViewComment::class, [
            'record' => $published->getKey(),
        ])
            ->assertSchemaComponentExists('custom_fields.status')
            ->assertSchemaComponentExists('custom_fields.priority')
            ->assertSee('high');

        livewire(ViewComment::class, [
            'record' => $draft->getKey(),
        ])
            ->assertSchemaComponentExists('custom_fields.status')
            ->assertSchemaComponentDoesNotExist('custom_fields.priority');
    });

    it('hides custom field entries when hide_when condition is met', function (): void {
        $conditionalField = ($this->conditionalField)('Internal Notes', 'internal_notes', VisibilityMode::HIDE_WHEN);

        $published = Comment::factory()->create();
        $published->saveCustomFieldValue($this->statusField, 'published');
        $published->saveCustomFieldValue($conditionalField, 'Internal review needed');

        $draft = Comment::factory()->create();
        $draft->saveCustomFieldValue($this->statusField, 'draft');
        $draft->saveCustomFieldValue($conditionalField, 'Internal review needed');

        livewire(ViewComment::class, [
            'record' => $published->getKey(),
        ])
            ->assertSchemaComponentExists('custom_fields.status')
            ->assertSchemaComponentDoesNotExist('custom_fields.internal_notes')
            ->assertDontSee('Internal review needed');

        livewire(ViewComment::class, [
            'record' => $draft->getKey(),
        ])
            ->assertSchemaComponentExists('custom_fields.status')
            ->assertSchemaComponentExists('custom_fields.internal_notes')
            ->assertSee('Internal review needed');
    });
});
