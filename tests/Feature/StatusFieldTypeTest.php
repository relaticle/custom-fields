<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\StatusFieldType;
use Relaticle\CustomFields\Filament\Management\Forms\Components\TypeField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ListPosts;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

function stageFieldOfType(string $type): CustomField
{
    livewire(ManageCustomFieldSection::class, [
        'section' => sectionForEntity(Post::class),
        'entityType' => Post::class,
    ])
        ->callAction('createField', [
            'name' => 'Stage',
            'code' => 'stage',
            'type' => $type,
            'entity_type' => Post::class,
            'options' => [
                ['name' => 'Discovery'],
                ['name' => 'Closed Won'],
            ],
        ])
        ->assertHasNoActionErrors();

    return CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->firstOrFail();
}

it('registers status as a single-choice type that carries option categories', function (): void {
    $status = CustomFieldsType::getFieldType(StatusFieldType::KEY);
    $select = CustomFieldsType::getFieldType('select');

    expect($status?->dataType)->toBe(FieldDataType::SINGLE_CHOICE)
        ->and($status?->carriesOptionCategories)->toBeTrue()
        ->and($select?->carriesOptionCategories)->toBeFalse()
        ->and($status?->encryptable)->toBeFalse()
        ->and($select?->encryptable)->toBeTrue()
        ->and($status?->searchable)->toBe($select?->searchable)
        ->and($status?->sortable)->toBe($select?->sortable)
        ->and($status?->filterable)->toBe($select?->filterable)
        ->and($status?->tableColumn)->toBe($select?->tableColumn)
        ->and($status?->tableFilter)->toBe($select?->tableFilter)
        ->and($status?->formComponent)->toBe($select?->formComponent)
        ->and($status?->infolistEntry)->toBe($select?->infolistEntry);
});

it('creates the field, holds a value, filters and sorts by it', function (string $type): void {
    $field = stageFieldOfType($type);

    [$discovery, $closedWon] = $field->options()->orderBy('sort_order')->get()->all();

    $early = Post::factory()->create();
    $won = Post::factory()->create();

    $early->saveCustomFieldValue($field, (string) $discovery->getKey());
    $won->saveCustomFieldValue($field, (string) $closedWon->getKey());

    expect($field->type)->toBe($type)
        ->and($early->fresh()->getCustomFieldValue($field))->toEqual($discovery->getKey());

    livewire(ListPosts::class)
        ->assertTableColumnExists('custom_fields.stage')
        ->assertCanRenderTableColumn('custom_fields.stage')
        ->assertTableColumnFormattedStateSet('custom_fields.stage', 'Closed Won', $won)
        ->filterTable('custom_fields.stage', [$closedWon->getKey()])
        ->assertCanSeeTableRecords([$won])
        ->assertCanNotSeeTableRecords([$early])
        ->resetTableFilters()
        ->sortTable('custom_fields.stage', 'asc')
        ->assertCanSeeTableRecords([$early, $won], inOrder: true)
        ->sortTable('custom_fields.stage', 'desc')
        ->assertCanSeeTableRecords([$won, $early], inOrder: true);
})->with(['select', StatusFieldType::KEY]);

it('lists status in the type picker with its own description, in both flavors', function (string $flavor): void {
    config()->set('custom-fields.ui.flavor', $flavor);

    $schema = Schema::make(livewire(ManageFieldsTable::class, ['entityType' => Post::class])->instance())
        ->statePath('data')
        ->components([TypeField::make('type')]);

    $field = $schema->getComponent(fn (mixed $component): bool => $component instanceof TypeField, withHidden: true);

    expect($field)->toBeInstanceOf(TypeField::class);

    $choices = collect($field->getTypeChoices())->keyBy('key');

    expect($choices->has(StatusFieldType::KEY))->toBeTrue()
        ->and($choices[StatusFieldType::KEY]['label'])->toBe('Status')
        ->and($choices[StatusFieldType::KEY]['description'])
        ->toBe('One choice from a list of workflow states you define.')
        ->and($choices['select']['description'])->toBe('One choice from a list you define.');

    expect(array_keys($field->getEnabledOptions()))->toContain(StatusFieldType::KEY)
        ->and($field->getEnabledOptions()[StatusFieldType::KEY])->toContain('Status');
})->with(['polished', 'native']);
