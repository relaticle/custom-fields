<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Relaticle\CustomFields\CustomFieldsPlugin;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Livewire\ManageCustomFieldWidth;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $this->allowed = true;
    $allowed = &$this->allowed;
    CustomFieldsPlugin::get()->authorize(static function () use (&$allowed): bool {
        return $allowed;
    });

    $this->section = CustomFieldSection::factory()->forEntityType(User::class)->create();
    $this->field = CustomField::factory()->ofType('text')->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => User::class,
        'name' => 'Original name',
        'sort_order' => 5,
    ]);
});

afterEach(function (): void {
    CustomFieldsPlugin::get()->authorize(true);
});

it('refuses to mount :dataset without permission to manage fields', function (string $component): void {
    $this->allowed = false;

    $parameters = match ($component) {
        ManageFieldsTable::class => ['entityType' => User::class],
        ManageCustomField::class => ['field' => $this->field],
        ManageCustomFieldSection::class => ['section' => $this->section, 'entityType' => User::class],
        ManageCustomFieldWidth::class => ['selectedWidth' => CustomFieldWidth::_100, 'fieldId' => $this->field->getKey()],
    };

    livewire($component, $parameters)->assertForbidden();
})->with([
    'the fields table' => ManageFieldsTable::class,
    'a field' => ManageCustomField::class,
    'a section' => ManageCustomFieldSection::class,
    'a field width' => ManageCustomFieldWidth::class,
]);

it('stops a fields table mounted with permission from deleting once permission is withdrawn', function (): void {
    $table = livewire(ManageFieldsTable::class, ['entityType' => User::class]);
    $this->allowed = false;

    $table->call('mountAction', 'deleteField', ['fieldId' => $this->field->getKey()])
        ->assertForbidden();

    expect(CustomField::query()->withDeactivated()->whereKey($this->field->getKey())->exists())->toBeTrue();
});

it('stops a fields table mounted with permission from editing once permission is withdrawn', function (): void {
    $table = livewire(ManageFieldsTable::class, ['entityType' => User::class]);
    $this->allowed = false;

    $table->call('mountAction', 'editField', ['fieldId' => $this->field->getKey()])
        ->assertForbidden();

    expect($this->field->fresh()->name)->toBe('Original name');
});

it('stops a fields table mounted with permission from reordering once permission is withdrawn', function (): void {
    $table = livewire(ManageFieldsTable::class, ['entityType' => User::class]);
    $this->allowed = false;

    $table->call('updateFieldsOrder', [$this->field->getKey()])->assertForbidden();

    expect($this->field->fresh()->sort_order)->toBe(5);
});

it('stops a fields table mounted with permission from resizing once permission is withdrawn', function (): void {
    $table = livewire(ManageFieldsTable::class, ['entityType' => User::class]);
    $this->allowed = false;

    $table->call('fieldWidthUpdated', $this->field->getKey(), 25)->assertForbidden();

    expect($this->field->fresh()->width)->not->toBe(CustomFieldWidth::_25);
});

it('stops a field mounted with permission from deleting once permission is withdrawn', function (): void {
    $component = livewire(ManageCustomField::class, ['field' => $this->field]);
    $this->allowed = false;

    $component->call('mountAction', 'delete')->assertForbidden();

    expect(CustomField::query()->withDeactivated()->whereKey($this->field->getKey())->exists())->toBeTrue();
});

it('stops a section mounted with permission from reordering once permission is withdrawn', function (): void {
    $component = livewire(ManageCustomFieldSection::class, ['section' => $this->section, 'entityType' => User::class]);
    $this->allowed = false;

    $component->call('updateFieldsOrder', $this->section->getKey(), [$this->field->getKey()])->assertForbidden();

    expect($this->field->fresh()->sort_order)->toBe(5);
});

it('lets a fields table with permission delete and reorder fields', function (): void {
    $other = CustomField::factory()->ofType('text')->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => User::class,
        'sort_order' => 9,
    ]);

    livewire(ManageFieldsTable::class, ['entityType' => User::class])
        ->call('updateFieldsOrder', [$other->getKey()])
        ->callAction(TestAction::make('deleteField')->arguments(['fieldId' => $this->field->getKey()]))
        ->assertSuccessful();

    expect($other->fresh()->sort_order)->toBe(0)
        ->and(CustomField::query()->withDeactivated()->whereKey($this->field->getKey())->exists())->toBeFalse();
});
