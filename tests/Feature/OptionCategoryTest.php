<?php

declare(strict_types=1);

use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\CustomFieldData;
use Relaticle\CustomFields\Data\CustomFieldOptionSettingsData;
use Relaticle\CustomFields\Data\CustomFieldSectionData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\OptionCategory;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\StatusFieldType;
use Relaticle\CustomFields\Filament\Integration\Migrations\CustomFieldsMigrator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Spatie\LaravelData\Exceptions\CannotCastEnum;

dataset('option categories', fn (): array => array_map(
    fn (OptionCategory $category): array => [$category],
    OptionCategory::cases(),
));

function statusFieldForCategories(): CustomField
{
    return CustomField::factory()->ofType(StatusFieldType::KEY)->create();
}

it('treats completed and cancelled as terminal categories', function (): void {
    expect(OptionCategory::Unstarted->isTerminal())->toBeFalse()
        ->and(OptionCategory::Started->isTerminal())->toBeFalse()
        ->and(OptionCategory::Completed->isTerminal())->toBeTrue()
        ->and(OptionCategory::Cancelled->isTerminal())->toBeTrue();
});

it('round-trips a category through the stored option settings', function (OptionCategory $category): void {
    $option = statusFieldForCategories()->options()->create([
        'name' => 'Some option',
        'sort_order' => 0,
        'settings' => ['category' => $category->value],
    ]);

    expect($option->fresh()->settings->category)->toBe($category);
})->with('option categories');

it('stores the category as its backed value in the settings json', function (): void {
    $option = statusFieldForCategories()->options()->create([
        'name' => 'Closed Won',
        'sort_order' => 0,
        'settings' => new CustomFieldOptionSettingsData(category: OptionCategory::Completed),
    ]);

    $stored = json_decode($option->fresh()->getRawOriginal('settings'), true, flags: JSON_THROW_ON_ERROR);

    expect($stored['category'])->toBe('completed');
});

it('keeps the category null when an option is saved without one', function (): void {
    $option = statusFieldForCategories()->options()->create([
        'name' => 'Untagged',
        'sort_order' => 0,
    ]);

    expect($option->fresh()->settings->category)->toBeNull();
});

it('clears the category back to null', function (): void {
    $option = statusFieldForCategories()->options()->create([
        'name' => 'Done',
        'sort_order' => 0,
        'settings' => ['category' => OptionCategory::Completed->value],
    ]);

    $option->update(['settings' => ['category' => null]]);

    expect($option->fresh()->settings->category)->toBeNull();
});

it('fails validation on an unknown category', function (): void {
    expect(fn (): CustomFieldOptionSettingsData => CustomFieldOptionSettingsData::validateAndCreate([
        'category' => 'archived',
    ]))->toThrow(ValidationException::class);
});

it('refuses to store an unknown category', function (): void {
    $field = statusFieldForCategories();

    expect(fn () => $field->options()->create([
        'name' => 'Archived',
        'sort_order' => 0,
        'settings' => ['category' => 'archived'],
    ]))->toThrow(CannotCastEnum::class);

    expect(CustomFields::newOptionModel()->query()->count())->toBe(0);
});

it('returns the options of one category in sort order', function (): void {
    $field = statusFieldForCategories();
    $field->options()->createMany([
        ['name' => 'Won Back', 'sort_order' => 3, 'settings' => ['category' => 'completed']],
        ['name' => 'Closed Won', 'sort_order' => 2, 'settings' => ['category' => 'completed']],
        ['name' => 'Closed Lost', 'sort_order' => 4, 'settings' => ['category' => 'cancelled']],
        ['name' => 'Discovery', 'sort_order' => 1],
    ]);

    expect($field->optionsInCategory(OptionCategory::Completed)->pluck('name')->all())
        ->toBe(['Closed Won', 'Won Back'])
        ->and($field->optionsInCategory(OptionCategory::Cancelled)->pluck('name')->all())
        ->toBe(['Closed Lost'])
        ->and($field->optionsInCategory(OptionCategory::Started))->toBeEmpty()
        ->and($field->optionsInCategory(OptionCategory::Unstarted))->toBeEmpty();
});

it("keeps another field's options out of the category result", function (): void {
    $field = statusFieldForCategories();
    $field->options()->create(['name' => 'Closed Won', 'sort_order' => 1, 'settings' => ['category' => 'completed']]);

    $otherField = statusFieldForCategories();
    $otherField->options()->create(['name' => 'Done', 'sort_order' => 1, 'settings' => ['category' => 'completed']]);

    expect($field->optionsInCategory(OptionCategory::Completed)->pluck('name')->all())->toBe(['Closed Won']);
});

it('filters options by category through the query builder', function (): void {
    $field = statusFieldForCategories();
    $field->options()->createMany([
        ['name' => 'Closed Won', 'sort_order' => 1, 'settings' => ['category' => 'completed']],
        ['name' => 'Won Back', 'sort_order' => 2, 'settings' => ['category' => 'completed']],
        ['name' => 'Closed Lost', 'sort_order' => 3, 'settings' => ['category' => 'cancelled']],
        ['name' => 'Discovery', 'sort_order' => 4],
    ]);

    $completed = CustomFields::newOptionModel()->query()->whereCategory(OptionCategory::Completed)->get();

    expect($completed->pluck('name')->all())->toEqualCanonicalizing(['Closed Won', 'Won Back'])
        ->and(CustomFields::newOptionModel()->query()->whereCategory(OptionCategory::Cancelled)->count())->toBe(1)
        ->and(CustomFields::newOptionModel()->query()->whereCategory(OptionCategory::Unstarted)->count())->toBe(0);
});

function configureOptionFeatures(bool $colors = false): void
{
    $configurator = FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
        CustomFieldsFeature::UI_TABLE_COLUMNS,
        CustomFieldsFeature::UI_TABLE_FILTERS,
        CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
        CustomFieldsFeature::SYSTEM_SECTIONS,
    );

    if ($colors) {
        $configurator = $configurator->enable(CustomFieldsFeature::FIELD_OPTION_COLORS);
    }

    config(['custom-fields.features' => $configurator]);
}

function renameFirstOption(CustomField $field, string $name): void
{
    $page = livewire(ManageCustomField::class, ['field' => $field])
        ->mountAction('edit')
        ->assertActionMounted('edit');

    $component = $page->instance();
    $itemKey = array_key_first($component->{$component->getMountedActionSchemaName()}->getRawState()['options']);

    $page->set('mountedActions.0.data.options.'.$itemKey.'.name', $name)
        ->callMountedAction()
        ->assertHasNoActionErrors();
}

/**
 * @return array{repeater: Repeater, categorySelects: list<string>}
 */
function mountedOptionsRepeater(CustomField $field): array
{
    $page = livewire(ManageCustomField::class, ['field' => $field])
        ->mountAction('edit')
        ->assertActionMounted('edit');

    $component = $page->instance();
    $schema = $component->{$component->getMountedActionSchemaName()};

    /** @var Repeater $repeater */
    $repeater = $schema->getFlatComponents(withHidden: true)['options'];

    $categorySelects = collect($schema->getFlatComponents())
        ->keys()
        ->filter(fn (string $key): bool => str_ends_with($key, 'settings.category'))
        ->values()
        ->all();

    return ['repeater' => $repeater, 'categorySelects' => $categorySelects];
}

it('offers a category column on a status field', function (): void {
    $field = CustomField::factory()->ofType(StatusFieldType::KEY)->withOptions(['Discovery', 'Closed Won'])->create();

    $mounted = mountedOptionsRepeater($field);

    expect($mounted['repeater']->getTableColumns())->toHaveCount(3)
        ->and($mounted['categorySelects'])->toHaveCount(2);
});

it('offers no category column on a multi-choice field', function (): void {
    $field = CustomField::factory()->ofType('multi-select')->withOptions(['Discovery', 'Closed Won'])->create();

    $mounted = mountedOptionsRepeater($field);

    expect($mounted['repeater']->getTableColumns())->toHaveCount(2)
        ->and($mounted['categorySelects'])->toBeEmpty();
});

it('offers no category column on a select field', function (): void {
    $field = CustomField::factory()->ofType('select')->withOptions(['Discovery', 'Closed Won'])->create();

    $mounted = mountedOptionsRepeater($field);

    expect($mounted['repeater']->getTableColumns())->toHaveCount(2)
        ->and($mounted['categorySelects'])->toBeEmpty();
});

it('saves a category chosen in the field editor', function (): void {
    $section = CustomFieldSection::factory()->forEntityType(User::class)->create();

    livewire(ManageCustomFieldSection::class, [
        'section' => $section,
        'entityType' => User::class,
    ])
        ->callAction('createField', [
            'name' => 'Stage',
            'code' => 'stage',
            'type' => StatusFieldType::KEY,
            'entity_type' => User::class,
            'options' => [
                ['name' => 'Discovery', 'settings' => ['category' => 'started']],
                ['name' => 'Closed Won', 'settings' => ['category' => 'completed']],
            ],
        ])
        ->assertHasNoActionErrors();

    $field = CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->firstOrFail();

    expect($field->options->pluck('settings.category')->all())
        ->toBe([OptionCategory::Started, OptionCategory::Completed]);
});

it('clears a category back to none in the field editor', function (): void {
    $field = CustomField::factory()->ofType(StatusFieldType::KEY)->create();
    $option = $field->options()->create([
        'name' => 'Closed Won',
        'sort_order' => 1,
        'settings' => ['category' => 'completed'],
    ]);

    $page = livewire(ManageCustomField::class, ['field' => $field])
        ->mountAction('edit')
        ->assertActionMounted('edit');

    $component = $page->instance();
    $itemKey = array_key_first($component->{$component->getMountedActionSchemaName()}->getRawState()['options']);

    $page->set('mountedActions.0.data.options.'.$itemKey.'.settings.category', null)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($option->fresh()->settings->category)->toBeNull();
});

it('seeds categories through the migrator options payload', function (): void {
    app(CustomFieldsMigrator::class)->new(
        model: User::class,
        fieldData: new CustomFieldData(
            name: 'Stage',
            code: 'stage',
            type: StatusFieldType::KEY,
        ),
    )->options([
        'Discovery',
        ['name' => 'Closed Won', 'category' => OptionCategory::Completed, 'color' => '#16a34a'],
        ['name' => 'Closed Lost', 'category' => 'cancelled'],
    ])->create();

    $field = CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->firstOrFail();

    expect($field->options->pluck('name')->all())->toBe(['Discovery', 'Closed Won', 'Closed Lost'])
        ->and($field->options->pluck('settings.category')->all())
        ->toBe([null, OptionCategory::Completed, OptionCategory::Cancelled])
        ->and($field->options->pluck('settings.color')->all())->toBe([null, '#16a34a', null])
        ->and($field->optionsInCategory(OptionCategory::Completed)->pluck('name')->all())->toBe(['Closed Won']);
});

it('rejects a migrator option array without a name', function (): void {
    $migrator = app(CustomFieldsMigrator::class)->new(
        model: User::class,
        fieldData: new CustomFieldData(
            name: 'Stage',
            code: 'stage',
            type: 'select',
        ),
    )->options([['category' => 'completed']]);

    expect(fn (): CustomField => $migrator->create())->toThrow(InvalidArgumentException::class);

    expect(CustomFields::newOptionModel()->query()->count())->toBe(0);
});

it('keeps a stored category when a select option is renamed', function (): void {
    configureOptionFeatures(colors: true);

    $field = CustomField::factory()->ofType('select')->withOptions(['Closed Won'])->create();
    $option = $field->options()->first();
    $option->update(['settings' => ['color' => '#16a34a', 'category' => 'completed']]);

    renameFirstOption($field->fresh(), 'Won');

    expect($option->fresh()->name)->toBe('Won')
        ->and($option->fresh()->settings->category)->toBe(OptionCategory::Completed);
});

it('keeps a stored color when option colors are hidden and the option is renamed', function (): void {
    configureOptionFeatures(colors: true);

    $field = CustomField::factory()->ofType(StatusFieldType::KEY)->withOptions(['Closed Won'])->create();
    $option = $field->options()->first();
    $option->update(['settings' => ['color' => '#16a34a', 'category' => 'completed']]);

    configureOptionFeatures();

    renameFirstOption($field->fresh(), 'Won');

    expect($option->fresh()->settings->color)->toBe('#16a34a')
        ->and($option->fresh()->settings->category)->toBe(OptionCategory::Completed);
});

it('keeps stored option settings when a multi-choice option is renamed', function (): void {
    configureOptionFeatures(colors: true);

    $field = CustomField::factory()->ofType('multi-select')->withOptions(['Closed Won'])->create();
    $option = $field->options()->first();
    $option->update(['settings' => ['color' => '#16a34a', 'category' => 'completed']]);

    renameFirstOption($field->fresh(), 'Won');

    expect($option->fresh()->settings->color)->toBe('#16a34a')
        ->and($option->fresh()->settings->category)->toBe(OptionCategory::Completed);
});

it('shows the option colors toggle for a multi-select field', function (): void {
    configureOptionFeatures(colors: true);

    $field = CustomField::factory()->ofType('multi-select')->withOptions(['Closed Won'])->create();

    livewire(ManageCustomField::class, ['field' => $field])
        ->mountAction('edit', ['record' => $field->getKey()])
        ->assertActionMounted('edit')
        ->assertSchemaComponentVisible('settings.enable_option_colors');
});

it('rejects a migrator category on a field whose options are not states', function (string $type): void {
    $migrator = app(CustomFieldsMigrator::class)->new(
        model: User::class,
        fieldData: new CustomFieldData(
            name: 'Tags',
            code: 'tags',
            type: $type,
        ),
    )->options([['name' => 'Closed Won', 'category' => OptionCategory::Completed]]);

    expect(fn (): CustomField => $migrator->create())
        ->toThrow(InvalidArgumentException::class, 'the options of [tags] are not workflow states');

    expect(CustomFields::newOptionModel()->query()->count())->toBe(0);
})->with(['select', 'multi-select']);

it('rejects an unknown key in a migrator option array', function (): void {
    $migrator = app(CustomFieldsMigrator::class)->new(
        model: User::class,
        fieldData: new CustomFieldData(
            name: 'Stage',
            code: 'stage',
            type: 'select',
        ),
    )->options([['name' => 'Closed Won', 'categorie' => 'completed']]);

    expect(fn (): CustomField => $migrator->create())->toThrow(InvalidArgumentException::class);

    expect(CustomFields::newOptionModel()->query()->count())->toBe(0);
});

it('reads a category from the loaded options relation without querying again', function (): void {
    $field = statusFieldForCategories();
    $field->options()->createMany([
        ['name' => 'Discovery', 'sort_order' => 1],
        ['name' => 'Closed Won', 'sort_order' => 2, 'settings' => ['category' => 'completed']],
    ]);

    $loaded = CustomField::query()->withoutGlobalScopes()->with('options')->findOrFail($field->getKey());

    DB::enableQueryLog();
    $completed = $loaded->optionsInCategory(OptionCategory::Completed);
    DB::disableQueryLog();

    expect($completed->pluck('name')->all())->toBe(['Closed Won'])
        ->and($completed->modelKeys())->toHaveCount(1)
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('applies categories when the migrator updates an existing field', function (): void {
    app(CustomFieldsMigrator::class)->new(
        model: User::class,
        fieldData: new CustomFieldData(
            name: 'Stage',
            code: 'stage',
            type: StatusFieldType::KEY,
            section: new CustomFieldSectionData(name: 'Pipeline', code: 'pipeline'),
        ),
    )->options(['Discovery', 'Closed Won'])->create();

    app(CustomFieldsMigrator::class)
        ->find(User::class, 'stage')
        ->options([
            'Discovery',
            ['name' => 'Closed Won', 'category' => OptionCategory::Completed],
        ])
        ->update(['name' => 'Stage']);

    $field = CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->firstOrFail();

    expect($field->options->pluck('settings.category')->all())->toBe([null, OptionCategory::Completed]);
});

it('keeps the options, their ids and a stored value when a select becomes a status field', function (): void {
    app(CustomFieldsMigrator::class)->new(
        model: Post::class,
        fieldData: new CustomFieldData(
            name: 'Stage',
            code: 'stage',
            type: 'select',
            section: new CustomFieldSectionData(name: 'Pipeline', code: 'pipeline'),
        ),
    )->options(['Discovery', 'Closed Won'])->create();

    $field = CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->firstOrFail();
    $optionIds = $field->options()->orderBy('sort_order')->pluck('id')->all();
    $closedWon = $field->options()->where('name', 'Closed Won')->sole();

    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, (string) $closedWon->getKey());

    app(CustomFieldsMigrator::class)->find(Post::class, 'stage')->update(['type' => StatusFieldType::KEY]);
    $closedWon->update(['settings' => ['category' => OptionCategory::Completed->value]]);

    $converted = CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->firstOrFail();

    expect($converted->type)->toBe(StatusFieldType::KEY)
        ->and($converted->options()->orderBy('sort_order')->pluck('id')->all())->toEqual($optionIds)
        ->and($post->fresh()->getCustomFieldValue($converted))->toEqual($closedWon->getKey())
        ->and($converted->optionsInCategory(OptionCategory::Completed)->pluck('name')->all())->toBe(['Closed Won'])
        ->and(mountedOptionsRepeater($converted)['categorySelects'])->toHaveCount(2);
});

it('lets a system-defined select become a status field through the migrator recipe', function (): void {
    app(CustomFieldsMigrator::class)->new(
        model: Post::class,
        fieldData: new CustomFieldData(
            name: 'Stage',
            code: 'stage',
            type: 'select',
            section: new CustomFieldSectionData(name: 'Pipeline', code: 'pipeline'),
            systemDefined: true,
        ),
    )->options(['Discovery', 'Closed Won'])->create();

    $field = CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->firstOrFail();
    $optionIds = $field->options()->orderBy('sort_order')->pluck('id')->all();
    $closedWon = $field->options()->where('name', 'Closed Won')->sole();

    $post = Post::factory()->create();
    $post->saveCustomFieldValue($field, (string) $closedWon->getKey());

    app(CustomFieldsMigrator::class)->find(Post::class, 'stage')->update(['type' => StatusFieldType::KEY]);

    $converted = CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->firstOrFail();

    expect($converted->type)->toBe(StatusFieldType::KEY)
        ->and($converted->system_defined)->toBeTrue()
        ->and($converted->options()->orderBy('sort_order')->pluck('id')->all())->toEqual($optionIds)
        ->and($post->fresh()->getCustomFieldValue($converted))->toEqual($closedWon->getKey());
});

it('still refuses to turn a system-defined select into a text field', function (): void {
    $field = CustomField::factory()->ofType('select')->systemDefined()->withOptions(['Discovery'])->create();

    expect(fn () => $field->update(['type' => 'text']))
        ->toThrow(RuntimeException::class, 'Cannot modify name, code, or type of system-defined fields.');

    expect($field->fresh()->type)->toBe('select');
});
