<?php

declare(strict_types=1);

// FormContainer and InfolistContainer defer schema generation to a closure. That
// closure must resolve the component Filament is evaluating rather than capturing
// the instance `make()` built, because `clone` copies the closure by reference:
// a cloned container would otherwise generate its schema from the original, which
// Filament never assigns a container to.
//
// Filament 5.6 masked this — `HasComponents::cloneComponents()` called
// `->container($this)->getClone()`, so the original was containerised before being
// cloned. Filament 5.7 reordered that to `->getClone()->container($this)`, leaving
// the original uninitialised and turning every cloned container into
// "Typed property Filament\Schemas\Components\Component::$container must not be
// accessed before initialization".
//
// Repeaters and repeatable entries clone their child schema per item, so this is
// the user-facing path: custom fields nested in a repeater.
//
// The crash is the loud symptom, but the underlying defect predates 5.7: a clone
// generating its schema from the original also resolves the *original's* model, so
// on 5.6 it silently rendered the wrong entity's fields. The last test pins that
// down, and holds regardless of how Filament clones components in future.

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Filament\Integration\Builders\FormContainer;
use Relaticle\CustomFields\Filament\Integration\Builders\InfolistContainer;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $section = CustomFieldSection::factory()->forEntityType(Post::class)->create();

    CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Cloned field',
        'code' => 'cloned_field',
        'type' => 'text',
        'entity_type' => Post::class,
    ]);

    $this->livewire = livewire(CreatePost::class)->instance();
});

it('renders form custom fields inside a repeater, whose item schemas are cloned', function (): void {
    $schema = Schema::make($this->livewire)
        ->model(Post::class)
        ->statePath('data')
        ->components([
            Repeater::make('items')
                ->schema([CustomFields::form()->build()]),
        ]);

    $repeater = $schema->getComponents()[0];
    $repeater->rawState([['cloned_field' => null]]);

    $fieldNames = collect($repeater->getChildSchemas())
        ->flatMap(fn (Schema $itemSchema): array => $itemSchema->getFlatComponents())
        ->map(fn (object $component): string => $component::class);

    expect($fieldNames)->toContain(FormContainer::class)
        ->and($fieldNames)->toContain(TextInput::class);
});

it('regenerates a cloned form container against the clone, not the original', function (): void {
    $schema = Schema::make($this->livewire)
        ->model(Post::class)
        ->components([CustomFields::form()->build()]);

    $componentNames = collect($schema->getClone()->getFlatComponents())
        ->map(fn (object $component): string => $component::class);

    expect($componentNames)->toContain(FormContainer::class)
        ->and($componentNames)->toContain(TextInput::class);
});

it('regenerates a cloned infolist container against the clone, not the original', function (): void {
    $schema = Schema::make($this->livewire)
        ->record(Post::factory()->create())
        ->components([CustomFields::infolist()->build()]);

    $componentNames = collect($schema->getClone()->getFlatComponents())
        ->map(fn (object $component): string => $component::class);

    expect($componentNames)->toContain(InfolistContainer::class)
        ->and($componentNames)->toContain(TextEntry::class);
});

it('generates a cloned container against the model of the schema it now belongs to', function (): void {
    $commentSection = CustomFieldSection::factory()->forEntityType(Comment::class)->create();

    CustomField::factory()->create([
        'custom_field_section_id' => $commentSection->id,
        'name' => 'Comment only',
        'code' => 'comment_only',
        'type' => 'text',
        'entity_type' => Comment::class,
    ]);

    $container = CustomFields::form()->build();

    // Containerise the original under a Post form, then clone it into a Comment
    // form — the shape Filament's own cloneComponents() produces.
    Schema::make($this->livewire)->model(Post::class)->components([$container])->getComponents();

    $commentSchema = Schema::make($this->livewire)
        ->model(Comment::class)
        ->components([$container->getClone()]);

    $fieldNames = collect($commentSchema->getFlatComponents())
        ->filter(fn (object $component): bool => $component instanceof Field)
        ->map(fn (Field $component): string => $component->getName())
        ->implode(',');

    expect($fieldNames)->toContain('comment_only')
        ->and($fieldNames)->not->toContain('cloned_field');
});
