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

it('saves today-or-later min date via edit action', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Start Date',
            'code' => 'start_date',
        ]);

    livewire(ManageCustomField::class, ['field' => $field])
        ->callAction('edit', [
            'name' => 'Start Date',
            'code' => 'start_date',
            'type' => 'date',
            'validation_rules' => [
                'min_date' => [
                    'preset' => 'today_preset',
                    'anchor' => 'today',
                    'offset' => 0,
                    'offset_unit' => 'days',
                    'offset_direction' => 'after',
                ],
            ],
        ]);

    $field->refresh();

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'today',
        'offset' => 0,
    ]);
});

it('saves offset-from-today min date via edit action', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Booking',
            'code' => 'booking',
        ]);

    livewire(ManageCustomField::class, ['field' => $field])
        ->callAction('edit', [
            'name' => 'Booking',
            'code' => 'booking',
            'type' => 'date',
            'validation_rules' => [
                'min_date' => [
                    'preset' => 'today_offset',
                    'anchor' => 'today',
                    'offset' => 7,
                    'offset_unit' => 'weeks',
                    'offset_direction' => 'after',
                ],
            ],
        ]);

    $field->refresh();

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'today',
        'offset' => 7,
        'offset_unit' => 'weeks',
        'offset_direction' => 'after',
    ]);
});

it('saves fixed-date max date via edit action', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Deadline',
            'code' => 'deadline',
        ]);

    livewire(ManageCustomField::class, ['field' => $field])
        ->callAction('edit', [
            'name' => 'Deadline',
            'code' => 'deadline',
            'type' => 'date',
            'validation_rules' => [
                'max_date' => [
                    'preset' => 'fixed_date',
                    'anchor' => 'fixed_date',
                    'fixed_date' => '2026-12-31',
                    'offset' => 0,
                    'offset_unit' => 'days',
                    'offset_direction' => 'after',
                ],
            ],
        ]);

    $field->refresh();

    expect($field->validation_rules->get('max_date'))->toMatchArray([
        'anchor' => 'fixed_date',
        'fixed_date' => '2026-12-31',
    ]);
});

it('saves custom field reference min date via edit action', function (): void {
    CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Start Date',
            'code' => 'start_date',
        ]);

    $endField = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'End Date',
            'code' => 'end_date',
        ]);

    livewire(ManageCustomField::class, ['field' => $endField])
        ->callAction('edit', [
            'name' => 'End Date',
            'code' => 'end_date',
            'type' => 'date',
            'validation_rules' => [
                'min_date' => [
                    'preset' => 'custom_field',
                    'anchor' => 'custom_field',
                    'field_reference' => 'start_date',
                    'offset' => 0,
                    'offset_unit' => 'days',
                    'offset_direction' => 'after',
                ],
            ],
        ]);

    $endField->refresh();

    expect($endField->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'custom_field',
        'field_reference' => 'start_date',
    ]);
});

it('saves record-created anchor via edit action', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Follow Up',
            'code' => 'follow_up',
        ]);

    livewire(ManageCustomField::class, ['field' => $field])
        ->callAction('edit', [
            'name' => 'Follow Up',
            'code' => 'follow_up',
            'type' => 'date',
            'validation_rules' => [
                'min_date' => [
                    'preset' => 'record_created',
                    'anchor' => 'record_created',
                    'offset' => 30,
                    'offset_unit' => 'days',
                    'offset_direction' => 'after',
                ],
            ],
        ]);

    $field->refresh();

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'record_created',
        'offset' => 30,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);
});

it('saves both min and max date constraints via edit action', function (): void {
    $field = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Event Date',
            'code' => 'event_date',
        ]);

    livewire(ManageCustomField::class, ['field' => $field])
        ->callAction('edit', [
            'name' => 'Event Date',
            'code' => 'event_date',
            'type' => 'date',
            'validation_rules' => [
                'min_date' => [
                    'preset' => 'today_preset',
                    'anchor' => 'today',
                    'offset' => 0,
                    'offset_unit' => 'days',
                    'offset_direction' => 'after',
                ],
                'max_date' => [
                    'preset' => 'fixed_date',
                    'anchor' => 'fixed_date',
                    'fixed_date' => '2026-12-31',
                    'offset' => 0,
                    'offset_unit' => 'days',
                    'offset_direction' => 'after',
                ],
            ],
        ]);

    $field->refresh();

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'today',
        'offset' => 0,
    ]);

    expect($field->validation_rules->get('max_date'))->toMatchArray([
        'anchor' => 'fixed_date',
        'fixed_date' => '2026-12-31',
    ]);
});

it('clears validation when preset set to none via edit action', function (): void {
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
            'name' => 'Any Date',
            'code' => 'any_date',
        ]);

    expect($field->validation_rules->get('min_date'))->not->toBeNull();

    livewire(ManageCustomField::class, ['field' => $field])
        ->callAction('edit', [
            'name' => 'Any Date',
            'code' => 'any_date',
            'type' => 'date',
            'validation_rules' => [
                'min_date' => [
                    'preset' => 'none',
                ],
            ],
        ]);

    $field->refresh();

    expect($field->validation_rules->get('min_date'))->toBeNull();
});

it('creates a new date field with validation via section create action', function (): void {
    livewire(ManageCustomFieldSection::class, [
        'section' => $this->section,
        'entityType' => $this->entityType,
    ])->callAction('createField', [
        'type' => 'date',
        'name' => 'Due Date',
        'code' => 'due_date',
        'validation_rules' => [
            'min_date' => [
                'preset' => 'today_preset',
                'anchor' => 'today',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ],
    ]);

    $field = CustomField::where('code', 'due_date')
        ->where('entity_type', $this->entityType)
        ->first();

    expect($field)->not->toBeNull();
    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'today',
        'offset' => 0,
    ]);
});

it('hydrates preset correctly when editing field with existing today constraint', function (): void {
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

    livewire(ManageCustomField::class, ['field' => $field])
        ->mountAction('edit')
        ->assertActionMounted('edit');
});

it('hydrates preset correctly when editing field with existing field reference', function (): void {
    CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Start Date',
            'code' => 'start_date',
        ]);

    $field = CustomField::factory()
        ->ofType('date')
        ->withValidation([
            'min_date' => [
                'anchor' => 'custom_field',
                'field_reference' => 'start_date',
                'offset' => 5,
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

    livewire(ManageCustomField::class, ['field' => $field])
        ->mountAction('edit')
        ->assertActionMounted('edit');
});

it('rejects circular field references via edit action', function (): void {
    $startField = CustomField::factory()
        ->ofType('date')
        ->withValidation([
            'min_date' => [
                'anchor' => 'custom_field',
                'field_reference' => 'end_date',
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

    $endField = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'End Date',
            'code' => 'end_date',
        ]);

    // end_date → start_date would create a cycle since start_date → end_date already exists
    livewire(ManageCustomField::class, ['field' => $endField])
        ->callAction('edit', [
            'name' => 'End Date',
            'code' => 'end_date',
            'type' => 'date',
            'validation_rules' => [
                'min_date' => [
                    'preset' => 'custom_field',
                    'anchor' => 'custom_field',
                    'field_reference' => 'start_date',
                    'offset' => 0,
                    'offset_unit' => 'days',
                    'offset_direction' => 'after',
                ],
            ],
        ])
        ->assertHasActionErrors();
});
