<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->entityType = User::class;

    $this->section = CustomFieldSection::factory()
        ->forEntityType($this->entityType)
        ->create();

    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
            CustomFieldsFeature::FIELD_VALIDATION_RULES,
            CustomFieldsFeature::UI_TABLE_COLUMNS,
            CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS,
            CustomFieldsFeature::UI_TABLE_FILTERS,
            CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )
    );
});

it('persists a date field with today-or-later min date validation', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->withValidation([
            'min_date' => [
                'anchor' => 'today',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ])
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Start Date',
            'code' => 'start_date',
        ]);

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'today',
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    livewire(ManageCustomField::class, [
        'field' => $field,
    ])->assertSuccessful()
        ->assertSee('Start Date');
});

it('persists a date field with fixed date max date validation', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->withValidation([
            'max_date' => [
                'anchor' => 'fixed_date',
                'fixed_date' => '2026-12-31',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ])
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Deadline',
            'code' => 'deadline',
        ]);

    expect($field->validation_rules->get('max_date'))->toMatchArray([
        'anchor' => 'fixed_date',
        'fixed_date' => '2026-12-31',
    ]);

    livewire(ManageCustomField::class, [
        'field' => $field,
    ])->assertSuccessful()
        ->assertSee('Deadline');
});

it('persists a date field with custom field reference min date', function (): void {
    CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Start Date',
            'code' => 'start_date',
        ]);

    $endDateField = CustomField::factory()
        ->ofType('date')
        ->withValidation([
            'min_date' => [
                'anchor' => 'custom_field',
                'field_reference' => 'start_date',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ])
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'End Date',
            'code' => 'end_date',
        ]);

    expect($endDateField->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'custom_field',
        'field_reference' => 'start_date',
    ]);

    livewire(ManageCustomField::class, [
        'field' => $endDateField,
    ])->assertSuccessful()
        ->assertSee('End Date');
});

it('persists a date field with record created anchor', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->withValidation([
            'min_date' => [
                'anchor' => 'record_created',
                'offset' => 30,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ])
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Follow Up',
            'code' => 'follow_up',
        ]);

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'record_created',
        'offset' => 30,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    livewire(ManageCustomField::class, [
        'field' => $field,
    ])->assertSuccessful()
        ->assertSee('Follow Up');
});

it('persists a date field with offset from today', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->withValidation([
            'min_date' => [
                'anchor' => 'today',
                'offset' => 7,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ])
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Future Event',
            'code' => 'future_event',
        ]);

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'today',
        'offset' => 7,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    livewire(ManageCustomField::class, [
        'field' => $field,
    ])->assertSuccessful()
        ->assertSee('Future Event');
});

it('persists a date field with no validation restrictions', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Any Date',
            'code' => 'any_date',
        ]);

    expect($field->type)->toBe('date');

    $minDate = $field->validation_rules->get('min_date');
    $maxDate = $field->validation_rules->get('max_date');

    expect($minDate)->toBeNull()
        ->and($maxDate)->toBeNull();

    livewire(ManageCustomField::class, [
        'field' => $field,
    ])->assertSuccessful()
        ->assertSee('Any Date');
});

it('renders the management section with date fields having validation', function (): void {
    CustomField::factory()
        ->ofType('date')
        ->withValidation([
            'min_date' => [
                'anchor' => 'today',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
            'max_date' => [
                'anchor' => 'fixed_date',
                'fixed_date' => '2026-12-31',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ])
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Event Date',
            'code' => 'event_date',
        ]);

    livewire(ManageCustomFieldSection::class, [
        'section' => $this->section,
        'entityType' => $this->entityType,
    ])->assertSuccessful()
        ->assertSee('Event Date');
});
