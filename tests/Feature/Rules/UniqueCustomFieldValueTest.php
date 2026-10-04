<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Exceptions\UniqueCustomFieldValueTaken;
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

describe('Soft-deleted records', function (): void {
    it('allows a domain only a soft-deleted record holds', function (): void {
        $trashed = Post::factory()->create();
        storeLinkValueForPost($trashed, $this->linkField, ['example.com']);
        $trashed->delete();

        livewire(CreatePost::class)
            ->fillForm(validPostData([
                'custom_fields' => [
                    'domains' => ['example.com'],
                ],
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();
    });

    it('allows a text value only a soft-deleted record holds', function (): void {
        $trashed = Post::factory()->create();
        storeTextValueForPost($trashed, $this->textField, 'my-slug');
        $trashed->delete();

        $validator = validator(['slug' => 'my-slug'], ['slug' => [new UniqueCustomFieldValue($this->textField)]]);

        expect($validator->passes())->toBeTrue();
    });

    it('still blocks a value an active record holds next to a soft-deleted one', function (): void {
        $trashed = Post::factory()->create();
        storeTextValueForPost($trashed, $this->textField, 'my-slug');
        $trashed->delete();
        storeTextValueForPost(Post::factory()->create(), $this->textField, 'my-slug');

        $validator = validator(['slug' => 'my-slug'], ['slug' => [new UniqueCustomFieldValue($this->textField)]]);

        expect($validator->fails())->toBeTrue();
    });
});

describe('Restoring soft-deleted records', function (): void {
    it('refuses to restore a record whose unique value an active record now holds', function (): void {
        $trashed = Post::factory()->create();
        storeTextValueForPost($trashed, $this->textField, 'my-slug');
        $trashed->delete();
        storeTextValueForPost(Post::factory()->create(), $this->textField, 'my-slug');

        expect(fn () => $trashed->restore())->toThrow(UniqueCustomFieldValueTaken::class, 'The value "my-slug" is already assigned to another record.')
            ->and($trashed->fresh()->trashed())->toBeTrue();
    });

    it('restores a record when only another trashed record holds its value', function (): void {
        $trashed = Post::factory()->create();
        storeTextValueForPost($trashed, $this->textField, 'my-slug');
        $trashed->delete();

        $otherTrashed = Post::factory()->create();
        storeTextValueForPost($otherTrashed, $this->textField, 'my-slug');
        $otherTrashed->delete();

        expect($trashed->restore())->toBeTrue()
            ->and($trashed->fresh()->trashed())->toBeFalse();
    });

    it('lists only the values of a multi-value field that an active record took', function (): void {
        $trashed = Post::factory()->create();
        storeLinkValueForPost($trashed, $this->linkField, ['kept.com', 'taken.com']);
        $trashed->delete();
        storeLinkValueForPost(Post::factory()->create(), $this->linkField, ['taken.com']);

        $taken = $trashed->takenUniqueCustomFieldValues();

        expect($taken)->toHaveCount(1)
            ->and($taken->first()['customField']->is($this->linkField))->toBeTrue()
            ->and($taken->first()['value'])->toBe('taken.com');
    });

    it('does not report a refused restore as an error', function (): void {
        expect(is_subclass_of(UniqueCustomFieldValueTaken::class, ShouldntReport::class))->toBeTrue();
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

describe('Non-scalar value handling', function (): void {
    it('does not crash when link field receives array-of-objects instead of array-of-strings', function (): void {
        $rule = new UniqueCustomFieldValue($this->linkField);
        $errors = [];

        $rule->validate(
            'custom_fields.domains',
            [['url' => 'https://example.com']],
            function (string $message) use (&$errors): void {
                $errors[] = $message;
            }
        );

        expect($errors)->toBeEmpty();
    });

    it('does not crash when text field receives an array instead of a string', function (): void {
        $rule = new UniqueCustomFieldValue($this->textField);
        $errors = [];

        $rule->validate(
            'custom_fields.slug',
            ['nested', 'array'],
            function (string $message) use (&$errors): void {
                $errors[] = $message;
            }
        );

        expect($errors)->toBeEmpty();
    });

    it('still validates scalar values after skipping non-scalar ones', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['taken.com']);

        $rule = new UniqueCustomFieldValue($this->linkField);
        $errors = [];

        $rule->validate(
            'custom_fields.domains',
            [['url' => 'https://skip.me'], 'taken.com'],
            function (string $message) use (&$errors): void {
                $errors[] = $message;
            }
        );

        expect($errors)->not->toBeEmpty();
    });
});

describe('Query efficiency', function (): void {
    it('issues a single query when validating a multi-value link field with many values', function (): void {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $rule = new UniqueCustomFieldValue($this->linkField);
            $errors = [];

            $rule->validate(
                'custom_fields.domains',
                ['a.com', 'b.com', 'c.com', 'd.com', 'e.com'],
                function (string $message) use (&$errors): void {
                    $errors[] = $message;
                }
            );

            $queries = count(array_filter(
                DB::getQueryLog(),
                static fn (array $entry): bool => str_contains($entry['query'], 'custom_field_values'),
            ));

            expect($queries)->toBe(1);
            expect($errors)->toBeEmpty();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    });

    it('issues a single query when validating a multi-value field where one value already exists', function (): void {
        $existing = Post::factory()->create();
        storeLinkValueForPost($existing, $this->linkField, ['taken.com']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $rule = new UniqueCustomFieldValue($this->linkField);
            $errors = [];

            $rule->validate(
                'custom_fields.domains',
                ['fresh.com', 'another.com', 'taken.com', 'more.com', 'last.com'],
                function (string $message) use (&$errors): void {
                    $errors[] = $message;
                }
            );

            $queries = count(array_filter(
                DB::getQueryLog(),
                static fn (array $entry): bool => str_contains($entry['query'], 'custom_field_values'),
            ));

            expect($queries)->toBe(1);
            expect($errors)->not->toBeEmpty();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
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

describe('Grandfathered values on save', function (): void {
    it('lets a record keep a unique value another record already shares', function (): void {
        $kept = Post::factory()->create();
        storeLinkValueForPost($kept, $this->linkField, ['acme.com']);
        storeLinkValueForPost(Post::factory()->create(), $this->linkField, ['acme.com']);

        $validator = validator(['v' => ['acme.com']], ['v' => [new UniqueCustomFieldValue($this->linkField, $kept->getKey(), exceptHeldValues: true)]]);

        expect($validator->passes())->toBeTrue();
    });

    it('lets a record keep a shared value submitted in another format', function (): void {
        $kept = Post::factory()->create();
        storeLinkValueForPost($kept, $this->linkField, ['acme.com']);
        storeLinkValueForPost(Post::factory()->create(), $this->linkField, ['acme.com']);

        $validator = validator(['v' => ['https://acme.com']], ['v' => [new UniqueCustomFieldValue($this->linkField, $kept->getKey(), exceptHeldValues: true)]]);

        expect($validator->passes())->toBeTrue();
    });

    it('rejects a value the record did not hold before in any format', function (): void {
        $taken = Post::factory()->create();
        $editing = Post::factory()->create();
        storeLinkValueForPost($taken, $this->linkField, ['acme.com']);

        $validator = validator(['v' => ['https://acme.com']], ['v' => [new UniqueCustomFieldValue($this->linkField, $editing->getKey(), exceptHeldValues: true)]]);

        expect($validator->passes())->toBeFalse();
    });

    it('rejects a newly added taken value next to a kept shared one', function (): void {
        $editing = Post::factory()->create();
        storeLinkValueForPost($editing, $this->linkField, ['shared.com']);
        storeLinkValueForPost(Post::factory()->create(), $this->linkField, ['shared.com', 'taken.com']);

        $validator = validator(['v' => ['shared.com', 'taken.com']], ['v' => [new UniqueCustomFieldValue($this->linkField, $editing->getKey(), exceptHeldValues: true)]]);

        expect($validator->passes())->toBeFalse();
    });

    it('keeps rejecting a shared value for the record that holds it when no exception is asked for', function (): void {
        $kept = Post::factory()->create();
        storeLinkValueForPost($kept, $this->linkField, ['acme.com']);
        storeLinkValueForPost(Post::factory()->create(), $this->linkField, ['acme.com']);

        $validator = validator(['v' => ['acme.com']], ['v' => [new UniqueCustomFieldValue($this->linkField, $kept->getKey())]]);

        expect($validator->passes())->toBeFalse();
    });

    it('treats a text value the record holds as its own', function (): void {
        $kept = Post::factory()->create();
        storeTextValueForPost($kept, $this->textField, 'my-slug');
        storeTextValueForPost(Post::factory()->create(), $this->textField, 'my-slug');

        $validator = validator(['v' => 'my-slug'], ['v' => [new UniqueCustomFieldValue($this->textField, $kept->getKey(), exceptHeldValues: true)]]);

        expect($validator->passes())->toBeTrue();
    });

    it('normalizes candidates with the field setting so a domain variant catches a pasted url', function (): void {
        $domainField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => Post::class,
            'code' => 'company_domain',
            'name' => 'Company domain',
            'type' => 'link',
            'settings' => new CustomFieldSettingsData(
                allow_multiple: true,
                max_values: 5,
                unique_per_entity_type: true,
                additional: ['link_variant' => 'domain'],
            ),
        ]);
        storeLinkValueForPost(Post::factory()->create(), $domainField, ['acme.com']);
        $editing = Post::factory()->create();

        $validator = validator(['v' => ['https://www.acme.com/pricing']], ['v' => [new UniqueCustomFieldValue($domainField, $editing->getKey(), exceptHeldValues: true)]]);

        expect($validator->passes())->toBeFalse();
    });

    it('matches a held value stored in a legacy format against the domain variant', function (): void {
        $domainField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => Post::class,
            'code' => 'company_domain',
            'name' => 'Company domain',
            'type' => 'link',
            'settings' => new CustomFieldSettingsData(
                allow_multiple: true,
                max_values: 5,
                unique_per_entity_type: true,
                additional: ['link_variant' => 'domain'],
            ),
        ]);
        $kept = Post::factory()->create();
        storeLinkValueForPost($kept, $domainField, ['www.acme.com']);
        storeLinkValueForPost(Post::factory()->create(), $domainField, ['acme.com']);

        $validator = validator(['v' => ['https://acme.com/about']], ['v' => [new UniqueCustomFieldValue($domainField, $kept->getKey(), exceptHeldValues: true)]]);

        expect($validator->passes())->toBeTrue();
    });

    it('still blocks restoring a trashed record whose value is taken', function (): void {
        $trashed = Post::factory()->create();
        $trashed->saveCustomFieldValue($this->linkField, ['acme.com']);
        $trashed->delete();
        Post::factory()->create()->saveCustomFieldValue($this->linkField, ['acme.com']);

        expect($trashed->takenUniqueCustomFieldValues())->not->toBeEmpty();
    });

    it('saves an edit that leaves a shared unique value untouched', function (): void {
        $kept = Post::factory()->create();
        storeLinkValueForPost($kept, $this->linkField, ['acme.com']);
        storeLinkValueForPost(Post::factory()->create(), $this->linkField, ['acme.com']);

        livewire(EditPost::class, ['record' => $kept->getKey()])
            ->fillForm(['title' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($kept->fresh()->title)->toBe('Renamed');
    });

    it('blocks an edit that adds a taken value next to a kept shared one', function (): void {
        $kept = Post::factory()->create();
        storeLinkValueForPost($kept, $this->linkField, ['acme.com']);
        storeLinkValueForPost(Post::factory()->create(), $this->linkField, ['acme.com']);
        storeLinkValueForPost(Post::factory()->create(), $this->linkField, ['taken.com']);

        livewire(EditPost::class, ['record' => $kept->getKey()])
            ->fillForm([
                'custom_fields' => [
                    'domains' => ['acme.com', 'taken.com'],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.domains']);
    });
});

describe('Values stored before normalization', function (): void {
    beforeEach(function (): void {
        $this->domainField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => Post::class,
            'code' => 'company_domain',
            'name' => 'Company domain',
            'type' => 'link',
            'settings' => new CustomFieldSettingsData(
                allow_multiple: true,
                max_values: 5,
                unique_per_entity_type: true,
                additional: ['link_variant' => 'domain'],
            ),
        ]);
    });

    it('blocks a bare domain when another record stored it as a full url', function (): void {
        storeLinkValueForPost(Post::factory()->create(), $this->domainField, ['https://www.acme.com/']);

        $validator = validator(['v' => ['acme.com']], ['v' => [new UniqueCustomFieldValue($this->domainField)]]);

        expect($validator->passes())->toBeFalse();
    });

    it('blocks a pasted url when another record stored the bare domain', function (): void {
        storeLinkValueForPost(Post::factory()->create(), $this->domainField, ['acme.com']);

        $validator = validator(['v' => ['HTTPS://www.Acme.com/x']], ['v' => [new UniqueCustomFieldValue($this->domainField)]]);

        expect($validator->passes())->toBeFalse();
    });

    it('blocks a url when another record stored the host with www', function (): void {
        storeLinkValueForPost(Post::factory()->create(), $this->domainField, ['www.acme.com']);

        $validator = validator(['v' => ['https://www.acme.com']], ['v' => [new UniqueCustomFieldValue($this->domainField)]]);

        expect($validator->passes())->toBeFalse();
    });

    it('allows the record to keep the legacy spelling it stored itself', function (): void {
        $own = Post::factory()->create();
        storeLinkValueForPost($own, $this->domainField, ['https://www.acme.com/']);

        $validator = validator(['v' => ['acme.com']], ['v' => [new UniqueCustomFieldValue($this->domainField, $own->getKey())]]);

        expect($validator->passes())->toBeTrue();
    });

    it('allows a domain that no stored spelling matches', function (): void {
        storeLinkValueForPost(Post::factory()->create(), $this->domainField, ['https://www.other.com/', 'acme.org']);

        $validator = validator(['v' => ['acme.com']], ['v' => [new UniqueCustomFieldValue($this->domainField)]]);

        expect($validator->passes())->toBeTrue();
    });

    it('still compares phone numbers by their E.164 form', function (): void {
        $phoneField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => Post::class,
            'code' => 'mobile',
            'name' => 'Mobile',
            'type' => 'phone',
            'settings' => new CustomFieldSettingsData(
                allow_multiple: true,
                max_values: 5,
                unique_per_entity_type: true,
            ),
        ]);
        storeLinkValueForPost(Post::factory()->create(), $phoneField, ['+14155550100']);

        $taken = validator(['v' => ['+1 (415) 555-0100']], ['v' => [new UniqueCustomFieldValue($phoneField)]]);
        $free = validator(['v' => ['+1 (415) 555-0199']], ['v' => [new UniqueCustomFieldValue($phoneField)]]);

        expect($taken->passes())->toBeFalse()
            ->and($free->passes())->toBeTrue();
    });
});
