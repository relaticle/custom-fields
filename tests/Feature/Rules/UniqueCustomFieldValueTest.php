<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Rules\UniqueCustomFieldValue;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create(['active' => true]);

    $this->linkField = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'domains',
        'name' => 'Domains',
        'type' => 'link',
        'settings' => new CustomFieldSettingsData(
            allow_multiple: true,
            max_values: 5,
            unique_per_entity_type: true,
        ),
    ]);

    $this->textField = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'slug',
        'name' => 'Slug',
        'type' => 'text',
        'settings' => new CustomFieldSettingsData(
            unique_per_entity_type: true,
        ),
    ]);
});

function validPostData(array $overrides = []): array
{
    $post = Post::factory()->make();

    return array_merge([
        'author_id' => $post->author->getKey(),
        'content' => $post->content,
        'tags' => $post->tags,
        'title' => $post->title,
        'rating' => $post->rating,
    ], $overrides);
}

function storeLinkValueForPost(Post $post, CustomField $field, array $domains): void
{
    CustomFieldValue::factory()->create([
        'custom_field_id' => $field->getKey(),
        'entity_type' => Post::class,
        'entity_id' => $post->getKey(),
        'json_value' => $domains,
    ]);
}

function storeTextValueForPost(Post $post, CustomField $field, string $value): void
{
    CustomFieldValue::factory()->create([
        'custom_field_id' => $field->getKey(),
        'entity_type' => Post::class,
        'entity_id' => $post->getKey(),
        'text_value' => $value,
    ]);
}

describe('Create record — link field uniqueness via Livewire', function (): void {
    it('creates a record when domain is unique', function (): void {
        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['example.com'],
                ],
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $post = Post::query()->latest('id')->first();
        $value = $post->customFieldValues->firstWhere('customField.code', 'domains');
        expect($value->json_value->toArray())->toBe(['example.com']);
    });

    it('blocks creation when exact domain already exists on another record', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['taken.com']);

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['taken.com'],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['custom_fields.domains']);
    });

    it('allows creation when a different domain exists', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['other.com']);

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['new.com'],
                ],
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();
    });

    it('blocks creation when https:// prefixed input matches stored bare domain', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['example.com']);

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['https://example.com'],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['custom_fields.domains']);
    });

    it('blocks creation when http:// prefixed input matches stored bare domain', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['example.com']);

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['http://example.com'],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['custom_fields.domains']);
    });

    it('blocks creation when mixed-case HTTPS:// input matches stored bare domain', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['example.com']);

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['HTTPS://example.com'],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['custom_fields.domains']);
    });

    it('blocks creation when any domain in a multi-value array matches', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['taken.com']);

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['fresh.com', 'taken.com'],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['custom_fields.domains']);
    });

    it('blocks creation when array contains https:// version of a stored domain', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['example.com']);

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['https://example.com', 'other.com'],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['custom_fields.domains']);
    });
});

describe('Edit record — link field uniqueness via Livewire', function (): void {
    it('allows saving when domain belongs to the same record being edited', function (): void {
        $post = Post::factory()->create();
        storeLinkValueForPost($post, $this->linkField, ['mysite.com']);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'domains' => ['mysite.com'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    });

    it('blocks saving when domain already exists on a different record', function (): void {
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();
        storeLinkValueForPost($post1, $this->linkField, ['taken.com']);

        livewire(EditPost::class, ['record' => $post2->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'domains' => ['taken.com'],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.domains']);
    });

    it('allows saving with https:// prefix when own stored value is bare domain', function (): void {
        $post = Post::factory()->create();
        storeLinkValueForPost($post, $this->linkField, ['mysite.com']);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'domains' => ['https://mysite.com'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    });

    it('blocks saving with https:// prefix when another record owns the bare domain', function (): void {
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();
        storeLinkValueForPost($post1, $this->linkField, ['taken.com']);

        livewire(EditPost::class, ['record' => $post2->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'domains' => ['https://taken.com'],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.domains']);
    });
});

describe('Create record — text field uniqueness via Livewire', function (): void {
    it('creates a record when text value is unique', function (): void {
        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'slug' => 'unique-slug',
                ],
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();
    });

    it('blocks creation when same text value already exists', function (): void {
        $existing = Post::factory()->create();
        storeTextValueForPost($existing, $this->textField, 'taken-slug');

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'slug' => 'taken-slug',
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['custom_fields.slug']);
    });

    it('allows creation when a different text value exists', function (): void {
        $existing = Post::factory()->create();
        storeTextValueForPost($existing, $this->textField, 'other-slug');

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'slug' => 'new-slug',
                ],
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();
    });
});

describe('Edit record — text field uniqueness via Livewire', function (): void {
    it('allows saving when text value belongs to the same record', function (): void {
        $post = Post::factory()->create();
        storeTextValueForPost($post, $this->textField, 'my-slug');

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'slug' => 'my-slug',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    });

    it('blocks saving when text value exists on a different record', function (): void {
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();
        storeTextValueForPost($post1, $this->textField, 'taken-slug');

        livewire(EditPost::class, ['record' => $post2->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'slug' => 'taken-slug',
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.slug']);
    });
});

describe('Morph alias resolution', function (): void {
    beforeEach(function (): void {
        Relation::morphMap([
            'post' => Post::class,
        ]);
    });

    afterEach(function (): void {
        Relation::morphMap([], false);
    });

    it('resolves morph aliases stored in entity_type without fatal error', function (): void {
        $section = CustomFieldSection::factory()
            ->forEntityType('post')
            ->create(['active' => true]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->getKey(),
            'entity_type' => 'post',
            'code' => 'unique_code',
            'name' => 'Unique Code',
            'type' => 'text',
            'settings' => new CustomFieldSettingsData(
                unique_per_entity_type: true,
            ),
        ]);

        $existingPost = Post::factory()->create();
        CustomFieldValue::factory()->create([
            'custom_field_id' => $field->getKey(),
            'entity_type' => 'post',
            'entity_id' => $existingPost->getKey(),
            'text_value' => 'taken-value',
        ]);

        $rule = new UniqueCustomFieldValue($field);
        $errors = [];

        $rule->validate(
            'custom_fields.unique_code',
            'taken-value',
            function (string $message) use (&$errors): void {
                $errors[] = $message;
            }
        );

        expect($errors)->not->toBeEmpty();
    });

    it('allows unique values when entity_type is a morph alias', function (): void {
        $section = CustomFieldSection::factory()
            ->forEntityType('post')
            ->create(['active' => true]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->getKey(),
            'entity_type' => 'post',
            'code' => 'unique_code',
            'name' => 'Unique Code',
            'type' => 'text',
            'settings' => new CustomFieldSettingsData(
                unique_per_entity_type: true,
            ),
        ]);

        $rule = new UniqueCustomFieldValue($field);
        $errors = [];

        $rule->validate(
            'custom_fields.unique_code',
            'fresh-value',
            function (string $message) use (&$errors): void {
                $errors[] = $message;
            }
        );

        expect($errors)->toBeEmpty();
    });
});
