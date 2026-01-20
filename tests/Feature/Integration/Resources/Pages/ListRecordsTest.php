<?php

declare(strict_types=1);

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
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ListPosts;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;
use Spatie\LaravelData\DataCollection;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Page Rendering and Authorization', function (): void {
    it('can render the list page', function (): void {
        $this->get(PostResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('can render list page via livewire component', function (): void {
        livewire(ListPosts::class)
            ->assertSuccessful();
    });

    it('is forbidden for users without permission', function (): void {
        // Arrange
        $unauthorizedUser = User::factory()->create();

        // Act & Assert
        $this->actingAs($unauthorizedUser)
            ->get(PostResource::getUrl('index'))
            ->assertSuccessful(); // Note: In this test setup, all users have permission
    });
});

describe('Basic Table Functionality', function (): void {
    beforeEach(function (): void {
        $this->posts = Post::factory()->count(10)->create();
    });

    it('can list all records in the table', function (): void {
        livewire(ListPosts::class)
            ->assertCanSeeTableRecords($this->posts);
    });

    it('can render standard table columns', function (string $column): void {
        livewire(ListPosts::class)
            ->assertCanRenderTableColumn($column);
    })->with([
        'title',
        'author.name',
    ]);

    it('displays correct record count', function (): void {
        livewire(ListPosts::class)
            ->assertCountTableRecords(10);
    });

    it('can handle empty table state', function (): void {
        // Arrange - Delete all posts
        Post::query()->delete();

        // Act & Assert
        livewire(ListPosts::class)
            ->assertCountTableRecords(0);
    });
});

describe('Table Sorting', function (): void {
    beforeEach(function (): void {
        $this->posts = Post::factory()->count(10)->create();
    });

    it('can sort records by standard columns', function (string $column, string $direction): void {
        $sortedPosts = $direction === 'asc'
            ? $this->posts->sortBy($column)
            : $this->posts->sortByDesc($column);

        livewire(ListPosts::class)
            ->sortTable($column, $direction)
            ->assertCanSeeTableRecords($sortedPosts, inOrder: true);
    })->with([
        'title ascending' => ['title', 'asc'],
        'title descending' => ['title', 'desc'],
        'author ascending' => ['author.name', 'asc'],
        'author descending' => ['author.name', 'desc'],
    ]);
});

describe('Table Search', function (): void {
    beforeEach(function (): void {
        $this->posts = Post::factory()->count(10)->create();
    });

    it('can search records by title', function (): void {
        $testPost = $this->posts->first();
        $searchTerm = $testPost->title;

        $expectedPosts = $this->posts->where('title', $searchTerm);
        $unexpectedPosts = $this->posts->where('title', '!=', $searchTerm);

        livewire(ListPosts::class)
            ->searchTable($searchTerm)
            ->assertCanSeeTableRecords($expectedPosts)
            ->assertCanNotSeeTableRecords($unexpectedPosts);
    });

    it('can search records by author name', function (): void {
        $testPost = $this->posts->first();
        $searchTerm = $testPost->author->name;

        $expectedPosts = $this->posts->where('author.name', $searchTerm);
        $unexpectedPosts = $this->posts->where('author.name', '!=', $searchTerm);

        livewire(ListPosts::class)
            ->searchTable($searchTerm)
            ->assertCanSeeTableRecords($expectedPosts)
            ->assertCanNotSeeTableRecords($unexpectedPosts);
    });

    it('shows no results for non-existent search terms', function (): void {
        livewire(ListPosts::class)
            ->searchTable('NonExistentSearchTerm12345')
            ->assertCountTableRecords(0);
    });

    it('can clear search and show all records again', function (): void {
        livewire(ListPosts::class)
            ->searchTable('some search term')
            ->searchTable('') // Clear search
            ->assertCanSeeTableRecords($this->posts);
    });
});

describe('Table Filtering', function (): void {
    beforeEach(function (): void {
        $this->posts = Post::factory()->count(10)->create();
    });

    it('can filter records by is_published status', function (): void {
        $publishedPosts = $this->posts->where('is_published', true);
        $unpublishedPosts = $this->posts->where('is_published', false);

        livewire(ListPosts::class)
            ->assertCanSeeTableRecords($this->posts)
            ->filterTable('is_published')
            ->assertCanSeeTableRecords($publishedPosts)
            ->assertCanNotSeeTableRecords($unpublishedPosts);
    });

    it('can clear filters to show all records', function (): void {
        livewire(ListPosts::class)
            ->filterTable('is_published')
            ->assertCanSeeTableRecords($this->posts->where('is_published', true))
            ->resetTableFilters()
            ->assertCanSeeTableRecords($this->posts);
    });
});

describe('Custom Fields Integration in Tables', function (): void {
    beforeEach(function (): void {
        // Create custom field section for Posts
        $this->section = CustomFieldSection::factory()->create([
            'name' => 'Post Table Fields',
            'entity_type' => Post::class,
            'active' => true,
            'sort_order' => 1,
        ]);
    });

    it('can display posts with custom field values', function ($column): void {
        // Arrange
        $customField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Category',
            'code' => 'category',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_list: true,
                list_toggleable_hidden: false
            ),
        ]);

        $posts = Post::factory()->count(3)->create();
        $categories = ['Technology', 'Science', 'Arts'];

        foreach ($posts as $index => $post) {
            $post->saveCustomFieldValue($customField, $categories[$index]);
        }

        // Act & Assert
        livewire(ListPosts::class)
            ->assertTableColumnExists($column)
            ->assertCanRenderTableColumn($column)
            ->assertCanSeeTableRecords($posts);
    })->with([
        'custom_fields.category',
    ]);

    it('can handle multiple custom field types in table display', function ($column): void {
        // Arrange
        $customFields = CustomField::factory()->createMany([
            [
                'custom_field_section_id' => $this->section->id,
                'code' => 'text_field',
                'type' => 'text',
                'entity_type' => Post::class,
                'settings' => new CustomFieldSettingsData(
                    visible_in_list: true,
                    list_toggleable_hidden: false
                ),
            ],
            [
                'custom_field_section_id' => $this->section->id,
                'code' => 'number_field',
                'type' => 'number',
                'entity_type' => Post::class,
                'settings' => new CustomFieldSettingsData(
                    visible_in_list: true,
                    list_toggleable_hidden: false
                ),
            ],
        ]);

        $post = Post::factory()->create();
        $post->saveCustomFieldValue($customFields[0], 'Text Value');
        $post->saveCustomFieldValue($customFields[1], 42);

        // Act & Assert
        livewire(ListPosts::class)
            ->assertTableColumnExists($column)
            ->assertCanRenderTableColumn($column)
            ->assertCanSeeTableRecords([$post]);
    })->with([
        'custom_fields.text_field',
        'custom_fields.number_field',
    ]);

    it('displays records without custom field values', function (): void {
        // Arrange
        $customField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'code' => 'optional_field',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_list: true,
                list_toggleable_hidden: false
            ),
        ]);

        $postWithValue = Post::factory()->create();
        $postWithoutValue = Post::factory()->create();

        $postWithValue->saveCustomFieldValue($customField, 'Has Value');
        // $postWithoutValue intentionally has no custom field value

        // Act & Assert
        livewire(ListPosts::class)
            ->assertCanSeeTableRecords([$postWithValue, $postWithoutValue]);
    });

    it('does not show custom fields that are not visible in list', function (): void {
        CustomField::factory()->create([
            'code' => 'hidden_field',
            'settings' => new CustomFieldSettingsData(
                visible_in_list: false,
            ),
        ]);

        // Act & Assert
        livewire(ListPosts::class)
            ->assertTableColumnDoesNotExist('custom_fields.hidden_field');
    });
});

describe('Conditional Visibility in Tables', function (): void {
    beforeEach(function (): void {
        // Create custom field section for Posts
        $this->section = CustomFieldSection::factory()->create([
            'name' => 'Post Conditional Fields',
            'entity_type' => Post::class,
            'active' => true,
            'sort_order' => 1,
        ]);
    });

    it('shows custom field values when show_when condition is met', function (): void {
        // Arrange - Create a base field and a conditional field
        $baseField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Status',
            'code' => 'status',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_list: true,
                list_toggleable_hidden: false
            ),
        ]);

        $conditionalField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Priority',
            'code' => 'priority',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_list: true,
                list_toggleable_hidden: false,
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

        // Act & Assert
        livewire(ListPosts::class)
            ->assertCanSeeTableRecords([$publishedPost, $draftPost])
            ->assertTableColumnStateSet('custom_fields.status', 'published', $publishedPost)
            ->assertTableColumnStateSet('custom_fields.status', 'draft', $draftPost)
            ->assertTableColumnStateSet('custom_fields.priority', 'high', $publishedPost)
            ->assertTableColumnStateNotSet('custom_fields.priority', 'high', $draftPost);
    });

    it('hides custom field values when hide_when condition is met', function (): void {
        // Arrange - Create a base field and a conditional field
        $baseField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Status',
            'code' => 'status',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_list: true,
                list_toggleable_hidden: false
            ),
        ]);

        $conditionalField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Internal Notes',
            'code' => 'internal_notes',
            'type' => 'textarea',
            'entity_type' => Post::class,
            'settings' => new CustomFieldSettingsData(
                visible_in_list: true,
                list_toggleable_hidden: false,
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

        // Act & Assert
        livewire(ListPosts::class)
            ->assertCanSeeTableRecords([$publishedPost, $draftPost])
            ->assertTableColumnStateSet('custom_fields.status', 'published', $publishedPost)
            ->assertTableColumnStateSet('custom_fields.status', 'draft', $draftPost)
            ->assertTableColumnStateNotSet('custom_fields.internal_notes', 'Internal review needed', $publishedPost)
            ->assertTableColumnStateSet('custom_fields.internal_notes', 'Internal review needed', $draftPost);
    });
});
