<?php

declare(strict_types=1);

use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

mutates(FieldForm::class);

afterEach(function (): void {
    FieldForm::flushSchemaExtensions();
});

function panelsCheckboxList(): CheckboxList
{
    return CheckboxList::make('settings.additional.hidden_in_panels')
        ->label('Hide from')
        ->options(['portal' => 'Portal']);
}

/**
 * @return array<int, string>
 */
function generalTabStatePaths(): array
{
    $tabs = FieldForm::schema()[0];

    $generalTab = $tabs->getDefaultChildComponents()[0];

    return array_map(
        fn (Component $component): string => $component->getStatePath(isAbsolute: false) ?? class_basename($component),
        $generalTab->getDefaultChildComponents(),
    );
}

it('builds the same general tab whether or not an extension was registered and then flushed', function (): void {
    $baseline = generalTabStatePaths();

    FieldForm::extendSchemaUsing(fn (array $schema, ?CustomFieldSection $s): array => [...$schema, panelsCheckboxList()]);

    expect(generalTabStatePaths())->toBe([...$baseline, 'settings.additional.hidden_in_panels']);

    FieldForm::flushSchemaExtensions();

    expect(generalTabStatePaths())->toBe($baseline);
});

it('passes the section to the extension and appends its components', function (): void {
    $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['code' => 'main']);
    $seen = null;

    FieldForm::extendSchemaUsing(function (array $schema, ?CustomFieldSection $s) use (&$seen): array {
        $seen = $s;

        return [...$schema, panelsCheckboxList()];
    });

    FieldForm::schema(section: $section);

    expect($seen?->is($section))->toBeTrue();
});

it('receives a null section when the form is built without one', function (): void {
    $seen = 'unset';

    FieldForm::extendSchemaUsing(function (array $schema, ?CustomFieldSection $s) use (&$seen): array {
        $seen = $s;

        return $schema;
    });

    FieldForm::schema();

    expect($seen)->toBeNull();
});

it('persists an extension component into settings.additional on create', function (): void {
    $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['code' => 'main']);

    FieldForm::extendSchemaUsing(fn (array $schema, ?CustomFieldSection $s): array => [...$schema, panelsCheckboxList()]);

    livewire(ManageCustomFieldSection::class, ['section' => $section, 'entityType' => Post::class])
        ->callAction('createField', [
            'name' => 'Reviewer Notes',
            'code' => 'reviewer_notes',
            'type' => 'text',
            'settings' => ['additional' => ['hidden_in_panels' => ['portal']]],
        ])
        ->assertHasNoActionErrors();

    $field = CustomField::query()->where('code', 'reviewer_notes')->sole();

    expect($field->settings->additional)->toBe(['hidden_in_panels' => ['portal']]);
});

it('persists an extension component into settings.additional on edit', function (): void {
    $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['code' => 'main']);
    $field = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'entity_type' => Post::class,
        'name' => 'Reviewer Notes',
        'code' => 'reviewer_notes',
        'type' => 'text',
    ]);

    FieldForm::extendSchemaUsing(fn (array $schema, ?CustomFieldSection $s): array => [...$schema, panelsCheckboxList()]);

    livewire(ManageCustomField::class, ['field' => $field])
        ->callAction('edit', data: [
            'name' => 'Reviewer Notes',
            'code' => 'reviewer_notes',
            'type' => 'text',
            'settings' => ['additional' => ['hidden_in_panels' => ['portal']]],
        ])
        ->assertHasNoActionErrors();

    expect($field->refresh()->settings->additional)->toBe(['hidden_in_panels' => ['portal']]);
});
