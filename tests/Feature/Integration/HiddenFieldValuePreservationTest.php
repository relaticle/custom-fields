<?php

declare(strict_types=1);

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FieldTypeSystem\FieldTypeConfigurator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;

beforeEach(function (): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SYSTEM_SECTIONS)
        ->enable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
        ->enable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
    );

    $this->setEntityConditionRelations([Post::class => ['tagModels' => 'Tags']]);

    $this->actingAs(User::factory()->create());

    $this->section = CustomFieldSection::factory()->create([
        'name' => 'Details',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $this->tag = Tag::factory()->create();

    $this->note = hfvField($this->section, 'note');
});

afterEach(function (): void {
    BackendVisibilityService::clearCache();
});

final class HfvSectionScopedForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Post $record;

    /** @var array<int, int> */
    public array $sectionIds = [];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $submitted = [];

    /**
     * @param  array<int, int>  $sectionIds
     */
    public function mount(Post $record, array $sectionIds): void
    {
        $this->record = $record;
        $this->sectionIds = $sectionIds;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([CustomFields::form()->forModel($this->record)->onlySections($this->sectionIds)->build()])
            ->statePath('data');
    }

    public function submit(): void
    {
        $this->submitted = $this->form->getState();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

function hfvTaggedVisibility(Tag $tag): array
{
    return [
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'tagModels',
            'operator' => VisibilityOperator::IS_IN,
            'value' => [$tag->getKey()],
            'source' => ConditionSource::RelationAttribute,
        ]],
    ];
}

function hfvField(CustomFieldSection $section, string $code, array $validationRules = [], ?array $visibility = null): CustomField
{
    return CustomField::factory()->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'name' => ucfirst(str_replace('_', ' ', $code)),
        'code' => $code,
        'type' => 'text',
        'validation_rules' => $validationRules,
        'settings' => $visibility ? ['visibility' => $visibility] : null,
    ]);
}

function hfvHiddenSection(Tag $tag): CustomFieldSection
{
    return CustomFieldSection::factory()->create([
        'name' => 'Tagged Details',
        'entity_type' => Post::class,
        'active' => true,
        'settings' => ['visibility' => hfvTaggedVisibility($tag)],
    ]);
}

function hfvSave(Post $post, array $customFields = []): mixed
{
    return livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['title' => 'Edited title', 'custom_fields' => ['note' => 'Visible note', ...$customFields]])
        ->call('save');
}

it('keeps the value of a field hidden by a relation condition', function (): void {
    $field = hfvField($this->section, 'tagged_note', visibility: hfvTaggedVisibility($this->tag));
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Keep me');

    hfvSave($post)->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('Keep me')
        ->and($post->fresh()->getCustomFieldValue($this->note))->toBe('Visible note');
});

it('keeps the values inside a section hidden by a relation condition', function (): void {
    $field = hfvField(hfvHiddenSection($this->tag), 'tagged_note');
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Keep me');

    hfvSave($post)->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('Keep me');
});

it('saves a field shown by its relation condition', function (): void {
    $field = hfvField($this->section, 'tagged_note', visibility: hfvTaggedVisibility($this->tag));
    $post = Post::factory()->create();
    $post->tagModels()->attach($this->tag);
    $post->saveCustomFieldValue($field, 'Old');

    hfvSave($post, ['tagged_note' => 'New'])->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('New');
});

it('still keeps the value of a field the browser hides by a condition on another field', function (): void {
    $trigger = hfvField($this->section, 'has_pet');
    $field = hfvField($this->section, 'pet_name', visibility: [
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'has_pet',
            'operator' => VisibilityOperator::EQUALS,
            'value' => 'Yes',
        ]],
    ]);
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($trigger, 'Yes');
    $post->saveCustomFieldValue($field, 'Rex');

    hfvSave($post, ['has_pet' => 'No'])->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('Rex');
});

it('clears a visible field the user emptied', function (): void {
    $field = hfvField($this->section, 'summary');
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Old');

    hfvSave($post, ['summary' => null])->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBeNull()
        ->and($post->fresh()->getCustomFieldValue($this->note))->toBe('Visible note');
});

it('does not require a field hidden by a relation condition', function (): void {
    hfvField($this->section, 'tagged_note', ['required' => true], hfvTaggedVisibility($this->tag));

    hfvSave(Post::factory()->create())->assertHasNoFormErrors();
});

it('does not require a field inside a section hidden by a relation condition', function (): void {
    hfvField(hfvHiddenSection($this->tag), 'tagged_note', ['required' => true]);

    hfvSave(Post::factory()->create())->assertHasNoFormErrors();
});

it('requires a field shown by its relation condition', function (): void {
    hfvField($this->section, 'tagged_note', ['required' => true], hfvTaggedVisibility($this->tag));
    $post = Post::factory()->create();
    $post->tagModels()->attach($this->tag);

    hfvSave($post, ['tagged_note' => null])
        ->assertHasFormErrors(['custom_fields.tagged_note' => 'required']);
});

it('ignores a value the browser sends for a field hidden by a relation condition', function (): void {
    $field = hfvField($this->section, 'tagged_note', visibility: hfvTaggedVisibility($this->tag));
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Keep me');

    hfvSave($post, ['tagged_note' => 'Injected'])->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('Keep me');
});

it('ignores a value the browser sends for a field inside a hidden section', function (): void {
    $field = hfvField(hfvHiddenSection($this->tag), 'tagged_note');
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Keep me');

    hfvSave($post, ['tagged_note' => 'Injected'])->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('Keep me');
});

it('ignores a blank value the browser sends for a hidden field', function (): void {
    $field = hfvField($this->section, 'tagged_note', visibility: hfvTaggedVisibility($this->tag));
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Keep me');

    hfvSave($post, ['tagged_note' => null])->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('Keep me');
});

it('does not store a value the browser sends for a hidden field that is empty', function (): void {
    $field = hfvField($this->section, 'tagged_note', visibility: hfvTaggedVisibility($this->tag));
    $post = Post::factory()->create();

    hfvSave($post, ['tagged_note' => 'Injected'])->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBeNull();
});

it('keeps an encrypted value hidden by a relation condition', function (): void {
    $field = hfvField($this->section, 'secret_note', visibility: hfvTaggedVisibility($this->tag));
    $field->update(['settings' => [...$field->settings->toArray(), 'encrypted' => true]]);
    $field = $field->fresh();

    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Keep me');

    hfvSave($post)->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('Keep me');
});

it('saves a field with a relation condition from the create form', function (): void {
    $field = hfvField($this->section, 'tagged_note', visibility: hfvTaggedVisibility($this->tag));

    livewire(CreatePost::class)
        ->fillForm([
            'title' => 'A title',
            'author_id' => User::factory()->create()->getKey(),
            'rating' => 5,
            'custom_fields' => ['tagged_note' => 'Typed on create'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Post::query()->latest('id')->first()->getCustomFieldValue($field))->toBe('Typed on create');
});

it('saves every field when conditional visibility is disabled', function (): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SYSTEM_SECTIONS)
        ->disable(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
        ->disable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
    );
    $field = hfvField($this->section, 'tagged_note', visibility: hfvTaggedVisibility($this->tag));
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, 'Old');

    hfvSave($post, ['tagged_note' => 'New'])->assertHasNoFormErrors();

    expect($post->fresh()->getCustomFieldValue($field))->toBe('New');
});

it('writes back the exact stored value of a hidden field of every type', function (string $type, Closure $value): void {
    config()->set('custom-fields.field_type_configuration', FieldTypeConfigurator::configure()
        ->enabled([])
        ->disabled([])
        ->discover(true)
        ->cache(enabled: false));

    $field = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Hidden '.$type,
        'code' => 'hidden_'.str_replace('-', '_', $type),
        'type' => $type,
        'lookup_type' => $type === 'record' ? Post::class : null,
        'settings' => ['visibility' => hfvTaggedVisibility($this->tag)],
    ]);

    if (in_array($type, ['select', 'multi-select', 'radio', 'checkbox-list', 'toggle-buttons'], true)) {
        $field->options()->createMany([['name' => 'One', 'sort_order' => 1], ['name' => 'Two', 'sort_order' => 2]]);
    }

    $field = $field->fresh();
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, $value($field, $post));

    $columns = ['string_value', 'text_value', 'boolean_value', 'integer_value', 'float_value', 'date_value', 'datetime_value', 'json_value'];
    $storedRow = fn (): array => array_intersect_key(
        CustomFieldValue::query()->where('custom_field_id', $field->getKey())->firstOrFail()->getRawOriginal(),
        array_flip($columns),
    );
    $before = $storedRow();

    hfvSave($post)->assertHasNoFormErrors();

    expect(array_filter($before, fn (mixed $column): bool => $column !== null))->not->toBeEmpty()
        ->and($storedRow())->toBe($before);
})->with([
    'text' => ['text', fn (): string => 'Hello'],
    'textarea' => ['textarea', fn (): string => "Line one\nLine two"],
    'number' => ['number', fn (): int => 42],
    'currency' => ['currency', fn (): float => 19.99],
    'date' => ['date', fn (): string => '2026-03-04'],
    'date-time' => ['date-time', fn (): string => '2026-03-04 10:20:00'],
    'email' => ['email', fn (): string => 'someone@example.com'],
    'phone' => ['phone', fn (): string => '+14155550123'],
    'link' => ['link', fn (): array => ['https://example.com']],
    'color-picker' => ['color-picker', fn (): string => '#ff0000'],
    'markdown-editor' => ['markdown-editor', fn (): string => '# Title'],
    'rich-editor' => ['rich-editor', fn (): string => '<p>Hello</p>'],
    'select' => ['select', fn (CustomField $field): int => $field->options->first()->getKey()],
    'multi-select' => ['multi-select', fn (CustomField $field): array => $field->options->pluck('id')->all()],
    'radio' => ['radio', fn (CustomField $field): int => $field->options->last()->getKey()],
    'checkbox' => ['checkbox', fn (): bool => true],
    'checkbox-list' => ['checkbox-list', fn (CustomField $field): array => $field->options->pluck('id')->all()],
    'toggle' => ['toggle', fn (): bool => true],
    'toggle-buttons' => ['toggle-buttons', fn (CustomField $field): int => $field->options->first()->getKey()],
    'tags-input' => ['tags-input', fn (): array => ['alpha', 'beta']],
    'file-upload' => ['file-upload', fn (): string => 'uploads/report.pdf'],
    'record' => ['record', fn (CustomField $field, Post $post): array => [Post::factory()->create()->getKey()]],
]);

it('keeps the stored value of the hidden field itself when another section shares its code', function (): void {
    $olderSection = CustomFieldSection::factory()->create(['name' => 'Version 1', 'entity_type' => Post::class, 'active' => true]);
    $currentSection = CustomFieldSection::factory()->create(['name' => 'Version 2', 'entity_type' => Post::class, 'active' => true]);
    $olderIncome = hfvField($olderSection, 'income');
    $currentIncome = hfvField($currentSection, 'income', visibility: hfvTaggedVisibility($this->tag));
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($currentIncome, 'Version 2 value');
    $post->saveCustomFieldValue($olderIncome, 'Version 1 value');

    livewire(HfvSectionScopedForm::class, ['record' => $post, 'sectionIds' => [$currentSection->getKey()]])
        ->call('submit')
        ->assertSet('submitted.custom_fields.income', 'Version 2 value');
});
