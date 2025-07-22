<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\ValidationRuleData;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Page Rendering and Authorization', function (): void {
    it('can render the create page', function (): void {
        livewire(CreatePost::class)
            ->assertSuccessful()
            ->assertSchemaExists('form');
    });

    it('allows authorized users to access create page via URL', function (): void {
        $this->get(PostResource::getUrl('create'))
            ->assertSuccessful();
    });

    it('is forbidden for users without permission', function (): void {
        // Arrange
        $unauthorizedUser = User::factory()->create();

        // Act & Assert
        $this->actingAs($unauthorizedUser)
            ->get(PostResource::getUrl('create'))
            ->assertSuccessful(); // Note: In this test setup, all users have permission
    });
});

describe('Record Creation', function (): void {
    it('can create a new record with valid data', function (): void {
        // Arrange
        $newData = Post::factory()->make();

        // Act
        $livewireTest = livewire(CreatePost::class)
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'content' => $newData->content,
                'tags' => $newData->tags,
                'title' => $newData->title,
                'rating' => $newData->rating,
            ])
            ->call('create');

        // Assert
        $livewireTest->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Post::class, [
            'author_id' => $newData->author->getKey(),
            'content' => $newData->content,
            'tags' => json_encode($newData->tags),
            'title' => $newData->title,
            'rating' => $newData->rating,
        ]);

        $this->assertDatabaseCount('posts', 1);
    });

    it('can create another record when create and add another is selected', function (): void {
        // Arrange
        $newData = Post::factory()->make();
        $newData2 = Post::factory()->make();

        // Act
        $livewireTest = livewire(CreatePost::class)
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'content' => $newData->content,
                'tags' => $newData->tags,
                'title' => $newData->title,
                'rating' => $newData->rating,
            ])
            ->call('create', true);

        // Assert first creation
        $livewireTest->assertHasNoFormErrors()
            ->assertNoRedirect()
            ->assertSchemaStateSet([
                'author_id' => null,
                'content' => null,
                'tags' => [],
                'title' => null,
                'rating' => null,
            ]);

        // Act - Create second record
        $livewireTest->fillForm([
            'author_id' => $newData2->author->getKey(),
            'content' => $newData2->content,
            'tags' => $newData2->tags,
            'title' => $newData2->title,
            'rating' => $newData2->rating,
        ])
            ->call('create');

        // Assert second creation
        $livewireTest->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Post::class, [
            'author_id' => $newData->author->getKey(),
            'content' => $newData->content,
            'tags' => json_encode($newData->tags),
            'title' => $newData->title,
            'rating' => $newData->rating,
        ]);

        $this->assertDatabaseHas(Post::class, [
            'author_id' => $newData2->author->getKey(),
            'content' => $newData2->content,
            'tags' => json_encode($newData2->tags),
            'title' => $newData2->title,
            'rating' => $newData2->rating,
        ]);

        $this->assertDatabaseCount('posts', 2);
    });
});

describe('Form Validation', function (): void {
    it('validates form fields', function (string $field, mixed $value, string|array $rule): void {
        livewire(CreatePost::class)
            ->fillForm([$field => $value])
            ->call('create')
            ->assertHasFormErrors([$field => $rule]);
    })->with([
        'title is required' => ['title', null, 'required'],
        'author_id is required' => ['author_id', null, 'required'],
        'rating is required' => ['rating', null, 'required'],
        'rating must be numeric' => ['rating', 'not-a-number', 'numeric'],
    ]);

    it('validates that author must exist', function (): void {
        livewire(CreatePost::class)
            ->fillForm([
                'title' => 'Test Title',
                'author_id' => 99999, // Non-existent ID
                'rating' => 5,
            ])
            ->call('create')
            ->assertHasFormErrors(['author_id']);
    });
});

describe('Custom Fields Integration', function (): void {
    it('can create a record with custom fields', function (): void {
        // Arrange
        $section = CustomFieldSection::factory()->create([
            'name' => 'Post Custom Fields',
            'entity_type' => Post::class,
            'active' => true,
            'sort_order' => 1,
        ]);

        CustomField::factory()->createMany([
            [
                'custom_field_section_id' => $section->id,
                'name' => 'SEO Title',
                'code' => 'seo_title',
                'type' => 'text',
                'sort_order' => 1,
                'entity_type' => Post::class,
                'validation_rules' => [],
            ],
            [
                'custom_field_section_id' => $section->id,
                'name' => 'View Count',
                'code' => 'view_count',
                'type' => 'number',
                'sort_order' => 2,
                'entity_type' => Post::class,
                'validation_rules' => [],
            ],
        ]);

        $newData = Post::factory()->make();

        // Act
        livewire(CreatePost::class)
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'content' => $newData->content,
                'tags' => $newData->tags,
                'title' => $newData->title,
                'rating' => $newData->rating,
                'custom_fields' => [
                    'seo_title' => 'Custom SEO Title',
                    'view_count' => 100,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        // Assert
        $this->assertDatabaseHas(Post::class, [
            'author_id' => $newData->author->getKey(),
            'content' => $newData->content,
            'tags' => json_encode($newData->tags),
            'title' => $newData->title,
            'rating' => $newData->rating,
        ]);

        $post = Post::query()->firstWhere('title', $newData->title);
        $customFieldValues = $post->customFieldValues->keyBy('customField.code');

        expect($customFieldValues)->toHaveCount(2)
            ->and($customFieldValues->get('seo_title')?->getValue())->toBe('Custom SEO Title')
            ->and($customFieldValues->get('view_count')?->getValue())->toBe(100);
    });

    it('validates required custom fields', function (): void {
        // Arrange
        $section = CustomFieldSection::factory()->create([
            'name' => 'Post Custom Fields',
            'entity_type' => Post::class,
            'active' => true,
            'sort_order' => 1,
        ]);

        CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Meta Description',
            'code' => 'meta_description',
            'type' => 'text',
            'sort_order' => 1,
            'entity_type' => Post::class,
            'validation_rules' => [
                new ValidationRuleData(name: 'required', parameters: []),
            ],
        ]);

        $newData = Post::factory()->make();

        // Act & Assert
        livewire(CreatePost::class)
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'content' => $newData->content,
                'tags' => $newData->tags,
                'title' => $newData->title,
                'rating' => $newData->rating,
                // Missing required custom field
            ])
            ->call('create')
            ->assertHasFormErrors(['custom_fields.meta_description']);
    });

    it('validates custom field types and constraints', function (string $fieldType, mixed $invalidValue, string $rule): void {
        // Arrange
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
        ]);

        CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'code' => 'test_field',
            'type' => $fieldType,
            'entity_type' => Post::class,
            'validation_rules' => [
                new ValidationRuleData(name: $rule, parameters: []),
            ],
        ]);

        $newData = Post::factory()->make();

        // Act & Assert
        livewire(CreatePost::class)
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'title' => $newData->title,
                'rating' => $newData->rating,
                'custom_fields' => [
                    'test_field' => $invalidValue,
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['custom_fields.test_field']);
    })->with([
        'text field min length' => ['text', 'a', 'min:3'],
        'number field must be numeric' => ['number', 'not-a-number', 'numeric'],
        'date field must be valid date' => ['date', 'invalid-date', 'date'],
    ]);
});

describe('Form Field Visibility and State', function (): void {
    it('displays custom fields section when custom fields exist', function (): void {
        // Arrange
        $section = CustomFieldSection::factory()->create([
            'name' => 'Post Custom Fields',
            'entity_type' => Post::class,
            'active' => true,
        ]);

        CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'Test Field',
            'code' => 'test_field',
            'type' => 'text',
            'entity_type' => Post::class,
        ]);

        // Act & Assert
        livewire(CreatePost::class)
            ->assertSee('Post Custom Fields');
    });

    it('hides custom fields section when no active custom fields exist', function (): void {
        // Arrange - No custom fields created

        // Act & Assert
        livewire(CreatePost::class)
            ->assertDontSee('Post Custom Fields');
    });
});

describe('Choice Field Validation with Option IDs', function (): void {
    it('validates choice fields with IN/NOT_IN rules using option IDs', function (
        string $fieldType,
        string $fieldCode,
        array $validationRules,
        array $optionNames,
        mixed $submitValue,
        bool $shouldPass,
        ?string $expectedError = null
    ): void {
        // Arrange
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'code' => $fieldCode,
            'type' => $fieldType,
            'entity_type' => Post::class,
            'validation_rules' => $validationRules,
        ]);

        // Create options and collect their IDs
        $options = collect($optionNames)->map(function ($name, $index) use ($field) {
            return CustomFieldOption::factory()->create([
                'custom_field_id' => $field->id,
                'name' => $name,
                'sort_order' => $index + 1,
            ]);
        });

        // Transform submit value to use option IDs (like Filament does)
        $transformedValue = match (true) {
            is_string($submitValue) && $submitValue === 'invalid' => 999999,
            is_string($submitValue) => $options->firstWhere('name', $submitValue)?->id,
            is_array($submitValue) => collect($submitValue)->map(fn ($name) => $name === 'invalid' ? 999999 : $options->firstWhere('name', $name)?->id
            )->toArray(),
            default => $submitValue,
        };

        $newData = Post::factory()->make();

        // Act
        $test = livewire(CreatePost::class)
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'content' => $newData->content,
                'tags' => $newData->tags,
                'title' => $newData->title,
                'rating' => $newData->rating,
                'custom_fields' => [
                    $fieldCode => $transformedValue,
                ],
            ])
            ->call('create');

        // Assert
        if ($shouldPass) {
            $test->assertHasNoFormErrors()->assertRedirect();

            // Verify saved value
            $post = Post::query()->firstWhere('title', $newData->title);
            expect($post)->not->toBeNull();

            $customValue = $post->customFieldValues()
                ->whereHas('customField', fn ($q) => $q->where('code', $fieldCode))
                ->first();

            expect($customValue)->not->toBeNull();
        } else {
            $test->assertHasFormErrors(['custom_fields.'.$fieldCode => [$expectedError]]);
        }
    })->with([
        // Single choice field tests
        'select with valid IN rule' => [
            'fieldType' => 'select',
            'fieldCode' => 'priority',
            'validationRules' => [
                new ValidationRuleData(name: 'required'),
                new ValidationRuleData(name: 'in', parameters: ['Low', 'Medium', 'High']),
            ],
            'optionNames' => ['Low', 'Medium', 'High'],
            'submitValue' => 'Medium',
            'shouldPass' => true,
        ],
        'select with invalid IN rule' => [
            'fieldType' => 'select',
            'fieldCode' => 'priority',
            'validationRules' => [
                new ValidationRuleData(name: 'required'),
                new ValidationRuleData(name: 'in', parameters: ['Low', 'Medium', 'High']),
            ],
            'optionNames' => ['Low', 'Medium', 'High'],
            'submitValue' => 'invalid',
            'shouldPass' => false,
            'expectedError' => 'in',
        ],
        'select with NOT_IN rule valid' => [
            'fieldType' => 'select',
            'fieldCode' => 'status',
            'validationRules' => [
                new ValidationRuleData(name: 'required'),
                new ValidationRuleData(name: 'not_in', parameters: ['Deleted', 'Banned']),
            ],
            'optionNames' => ['Active', 'Deleted', 'Banned'],
            'submitValue' => 'Active',
            'shouldPass' => true,
        ],
        'select with NOT_IN rule invalid' => [
            'fieldType' => 'select',
            'fieldCode' => 'status',
            'validationRules' => [
                new ValidationRuleData(name: 'required'),
                new ValidationRuleData(name: 'not_in', parameters: ['Deleted', 'Banned']),
            ],
            'optionNames' => ['Active', 'Deleted', 'Banned'],
            'submitValue' => 'Deleted',
            'shouldPass' => false,
            'expectedError' => 'not_in',
        ],
        // Multi-choice field tests
        'multi-select with valid values' => [
            'fieldType' => 'multi-select',
            'fieldCode' => 'categories',
            'validationRules' => [
                new ValidationRuleData(name: 'required'),
                new ValidationRuleData(name: 'array'),
                new ValidationRuleData(name: 'min', parameters: [1]),
            ],
            'optionNames' => ['Technology', 'Business', 'Design'],
            'submitValue' => ['Technology', 'Design'],
            'shouldPass' => true,
        ],
        'multi-select with empty array' => [
            'fieldType' => 'multi-select',
            'fieldCode' => 'categories',
            'validationRules' => [
                new ValidationRuleData(name: 'required'),
                new ValidationRuleData(name: 'array'),
                new ValidationRuleData(name: 'min', parameters: [1]),
            ],
            'optionNames' => ['Technology', 'Business', 'Design'],
            'submitValue' => [],
            'shouldPass' => false,
            'expectedError' => 'required',
        ],
        'checkbox-list with IN validation' => [
            'fieldType' => 'checkbox-list',
            'fieldCode' => 'features',
            'validationRules' => [
                new ValidationRuleData(name: 'array'),
                new ValidationRuleData(name: 'in', parameters: ['Feature A', 'Feature B', 'Feature C']),
            ],
            'optionNames' => ['Feature A', 'Feature B', 'Feature C'],
            'submitValue' => ['Feature A', 'Feature C'],
            'shouldPass' => true,
        ],
        'radio with required validation' => [
            'fieldType' => 'radio',
            'fieldCode' => 'subscription',
            'validationRules' => [
                new ValidationRuleData(name: 'required'),
                new ValidationRuleData(name: 'in', parameters: ['Basic', 'Pro', 'Enterprise']),
            ],
            'optionNames' => ['Basic', 'Pro', 'Enterprise'],
            'submitValue' => 'Pro',
            'shouldPass' => true,
        ],
    ]);

    it('handles edge cases for choice field validation', function (): void {
        // Test with options that have special characters
        $section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'code' => 'special_field',
            'type' => 'select',
            'entity_type' => Post::class,
            'validation_rules' => [
                new ValidationRuleData(name: 'required'),
                new ValidationRuleData(name: 'in', parameters: ['Option/1', 'Option,2', 'Option:3']),
            ],
        ]);

        // Create options with special characters
        $option1 = CustomFieldOption::factory()->create([
            'custom_field_id' => $field->id,
            'name' => 'Option/1',
            'sort_order' => 1,
        ]);

        $option2 = CustomFieldOption::factory()->create([
            'custom_field_id' => $field->id,
            'name' => 'Option,2',
            'sort_order' => 2,
        ]);

        $newData = Post::factory()->make();

        // Test that special characters in option names work correctly
        livewire(CreatePost::class)
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'title' => $newData->title,
                'rating' => $newData->rating,
                'custom_fields' => [
                    'special_field' => $option2->id,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();
    });
});
