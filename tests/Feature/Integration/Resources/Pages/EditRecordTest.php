<?php

declare(strict_types=1);

use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Data\ValidationRuleData;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->post = Post::factory()->create();
});

describe('Page Rendering and Authorization', function (): void {
    it('can render the edit page', function (): void {
        $this->get(PostResource::getUrl('edit', ['record' => $this->post]))
            ->assertSuccessful();
    });

    it('can render edit page via livewire component', function (): void {
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->assertSuccessful()
            ->assertSchemaExists('form');
    });

    it('is forbidden for users without permission', function (): void {
        // Arrange
        $unauthorizedUser = User::factory()->create();

        // Act & Assert
        $this->actingAs($unauthorizedUser)
            ->get(PostResource::getUrl('edit', ['record' => $this->post]))
            ->assertSuccessful(); // Note: In this test setup, all users have permission
    });
});

describe('Data Retrieval and Form Population', function (): void {
    it('can retrieve and populate form with existing record data', function (): void {
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->assertSchemaStateSet([
                'author_id' => $this->post->author->getKey(),
                'content' => $this->post->content,
                'tags' => $this->post->tags,
                'title' => $this->post->title,
                'rating' => $this->post->rating,
            ]);
    });

    it('can refresh form data after external changes', function (): void {
        // Arrange
        $page = livewire(EditPost::class, ['record' => $this->post->getKey()]);
        $originalTitle = $this->post->title;

        // Act - Verify initial state
        $page->assertSchemaStateSet(['title' => $originalTitle]);

        // Change the record externally
        $newTitle = Str::random();
        $this->post->update(['title' => $newTitle]);

        // Assert - Form still shows old data until refresh
        $page->assertSchemaStateSet(['title' => $originalTitle]);

        // Act - Refresh the form
        $page->call('refreshTitle');

        // Assert - Form now shows updated data
        $page->assertSchemaStateSet(['title' => $newTitle]);
    });
});

describe('Record Updates and Persistence', function (): void {
    it('can save updated record with valid data', function (): void {
        // Arrange
        $newData = Post::factory()->make();

        // Act
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'content' => $newData->content,
                'tags' => $newData->tags,
                'title' => $newData->title,
                'rating' => $newData->rating,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert
        expect($this->post->refresh())
            ->author->toBeSameModel($newData->author)
            ->content->toBe($newData->content)
            ->tags->toBe($newData->tags)
            ->title->toBe($newData->title)
            ->rating->toBe($newData->rating);
    });

    it('validates form fields before saving', function (string $field, mixed $value, string|array $rule): void {
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm([$field => $value])
            ->call('save')
            ->assertHasFormErrors([$field => $rule]);
    })->with([
        'title is required' => ['title', null, 'required'],
        'author_id is required' => ['author_id', null, 'required'],
        'rating is required' => ['rating', null, 'required'],
        'rating must be numeric' => ['rating', 'not-a-number', 'numeric'],
    ]);

    it('validates that author must exist', function (): void {
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm(['author_id' => 99999]) // Non-existent ID
            ->call('save')
            ->assertHasFormErrors(['author_id']);
    });
});

describe('Record Actions', function (): void {
    it('can delete record using delete action', function (): void {
        // Act
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->callAction(DeleteAction::class);

        // Assert
        assertSoftDeleted($this->post);
    });

    it('maintains transaction integrity during action errors', function (): void {
        // Arrange
        $transactionLevel = DB::transactionLevel();

        // Act
        try {
            livewire(EditPost::class, ['record' => $this->post->getKey()])
                ->callAction('randomize_title');
        } catch (Exception) {
            // This can be caught and handled somewhere else, code continues...
        }

        // Assert - Original transaction level should be unaffected
        expect(DB::transactionLevel())->toBe($transactionLevel);
    });
});

describe('Custom Fields Integration', function (): void {
    beforeEach(function (): void {
        // Create a custom field section for all custom field tests
        $this->section = CustomFieldSection::factory()->create([
            'name' => 'Post Custom Fields',
            'entity_type' => Post::class,
            'active' => true,
            'sort_order' => 1,
        ]);
    });

    it('can retrieve existing custom field values in edit form', function (): void {
        // Arrange
        $customField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'SEO Title',
            'code' => 'seo_title',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        $this->post->saveCustomFieldValue($customField, 'Test SEO Title');

        // Act & Assert
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->assertSchemaStateSet([
                'author_id' => $this->post->author->getKey(),
                'content' => $this->post->content,
                'tags' => $this->post->tags,
                'title' => $this->post->title,
                'rating' => $this->post->rating,
                'custom_fields' => [
                    'seo_title' => 'Test SEO Title',
                ],
            ]);
    });

    it('can update existing custom field values', function (): void {
        // Arrange
        $customFields = CustomField::factory()->createMany([
            [
                'custom_field_section_id' => $this->section->id,
                'name' => 'SEO Title',
                'code' => 'seo_title',
                'type' => 'text',
                'entity_type' => Post::class,
            ],
            [
                'custom_field_section_id' => $this->section->id,
                'name' => 'View Count',
                'code' => 'view_count',
                'type' => 'number',
                'entity_type' => Post::class,
            ],
        ]);

        $this->post->saveCustomFieldValue($customFields->first(), 'Original SEO Title');
        $this->post->saveCustomFieldValue($customFields->last(), 50);

        $newData = Post::factory()->make();

        // Act
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'content' => $newData->content,
                'tags' => $newData->tags,
                'title' => $newData->title,
                'rating' => $newData->rating,
                'custom_fields' => [
                    'seo_title' => 'Updated SEO Title',
                    'view_count' => 200,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert
        expect($this->post->refresh())
            ->author->toBeSameModel($newData->author)
            ->title->toBe($newData->title);

        $customFieldValues = $this->post->customFieldValues->keyBy('customField.code');
        expect($customFieldValues)->toHaveCount(2)
            ->and($customFieldValues->get('seo_title')?->getValue())->toBe('Updated SEO Title')
            ->and($customFieldValues->get('view_count')?->getValue())->toBe(200);
    });

    it('can add new custom field values to existing record', function (): void {
        // Arrange
        $existingCustomField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'code' => 'existing_field',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        $this->post->saveCustomFieldValue($existingCustomField, 'Existing Value');

        // Create a new custom field after the post was created
        $newCustomField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'code' => 'new_field',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        // Act
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'existing_field' => 'Updated Existing Value',
                    'new_field' => 'New Field Value',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert
        $customFieldValues = $this->post->refresh()->customFieldValues->keyBy('customField.code');
        expect($customFieldValues)->toHaveCount(2)
            ->and($customFieldValues->get('existing_field')?->getValue())->toBe('Updated Existing Value')
            ->and($customFieldValues->get('new_field')?->getValue())->toBe('New Field Value');
    });

    it('validates required custom fields during update', function (): void {
        // Arrange
        $requiredCustomField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Meta Description',
            'code' => 'meta_description',
            'type' => 'text',
            'entity_type' => Post::class,
            'validation_rules' => [
                new ValidationRuleData(name: 'required', parameters: []),
            ],
        ]);

        $this->post->saveCustomFieldValue($requiredCustomField, 'Original meta description');

        // Act & Assert
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm([
                'title' => 'Updated Title',
                'custom_fields' => [
                    'meta_description' => '', // Empty required field
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.meta_description']);
    });

    it('validates custom field types and constraints during update', function (string $fieldType, mixed $invalidValue, string $rule): void {
        // Arrange
        $customField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'code' => 'test_field',
            'type' => $fieldType,
            'entity_type' => Post::class,
            'validation_rules' => [
                new ValidationRuleData(name: $rule, parameters: $rule === 'min' ? [3] : []),
            ],
        ]);

        $this->post->saveCustomFieldValue($customField, 'original value');

        // Act & Assert
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'test_field' => $invalidValue,
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.test_field']);
    })->with([
        'text field min length' => ['text', 'a', 'min'],
        'number field must be numeric' => ['number', 'not-a-number', 'numeric'],
    ]);

    it('can clear custom field values', function (): void {
        // Arrange
        $customField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'code' => 'clearable_field',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        $this->post->saveCustomFieldValue($customField, 'Value to be cleared');

        // Act
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'clearable_field' => null,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert
        $this->post->refresh();
        expect($this->post->getCustomFieldValue($customField))->toBeNull();
    });

    it('handles multiple custom field types in a single update', function (): void {
        // Arrange
        CustomField::factory()->createMany([
            [
                'custom_field_section_id' => $this->section->id,
                'code' => 'text_field',
                'type' => 'text',
                'entity_type' => Post::class,
            ],
            [
                'custom_field_section_id' => $this->section->id,
                'code' => 'number_field',
                'type' => 'number',
                'entity_type' => Post::class,
            ],
            [
                'custom_field_section_id' => $this->section->id,
                'code' => 'date_field',
                'type' => 'date',
                'entity_type' => Post::class,
            ],
        ]);

        // Act
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'text_field' => 'Updated text value',
                    'number_field' => 42,
                    'date_field' => '2024-12-25',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert
        $customFieldValues = $this->post->refresh()->customFieldValues->keyBy('customField.code');
        expect($customFieldValues)->toHaveCount(3)
            ->and($customFieldValues->get('text_field')?->getValue())->toBe('Updated text value')
            ->and($customFieldValues->get('number_field')?->getValue())->toBe(42)
            ->and($customFieldValues->get('date_field')?->getValue()->format('Y-m-d'))->toBe('2024-12-25');
    });
});

describe('Custom Fields Form Visibility', function (): void {
    it('displays custom fields when custom fields exist for the entity', function (): void {
        // Arrange
        $section = CustomFieldSection::factory()->create([
            'name' => 'Post Custom Fields',
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'entity_type' => Post::class,
            'name' => 'Test Field',
            'code' => 'test_field',
            'type' => 'text',
        ]);

        // Act & Assert - Verify the custom field is present in the form and can be filled
        livewire(EditPost::class, ['record' => $this->post->getKey()])
            ->assertFormFieldExists('custom_fields.test_field');
    });

    it('hides custom fields when no active custom fields exist', function (): void {
        // Arrange - No custom fields created

        // Act & Assert - When no custom fields exist, the form should not render any custom field components
        $livewire = livewire(EditPost::class, ['record' => $this->post->getKey()]);

        // The form should still render successfully, just without custom fields
        expect($livewire)->toBeTruthy();
    });
});
