<?php

declare(strict_types=1);

use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\CustomFieldOptionSettingsData;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RelationshipFieldType;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\FieldTypes\SystemFirstFieldType;
use Relaticle\CustomFields\Tests\Fixtures\FieldTypes\SystemProbeFieldType;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    // Arrange: Create authenticated user for all tests
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Set up common test entity types for all tests
    $this->postEntityType = Post::class;
    $this->userEntityType = User::class;
});

describe('ManageCustomFieldSection - Field Management', function (): void {
    beforeEach(function (): void {
        $this->section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();
    });

    it('can update field order within a section', function (): void {
        // Arrange - use enhanced factory methods
        $field1 = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'sort_order' => 0,
            ]);
        $field2 = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'sort_order' => 1,
            ]);

        // Act
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->call('updateFieldsOrder', $this->section->getKey(), [$field2->getKey(), $field1->getKey()]);

        // Assert - use enhanced expectations
        expect($field2->fresh())->sort_order->toBe(0);
        expect($field1->fresh())->sort_order->toBe(1);
    });

    it('can move inactive fields within and between sections', function (): void {
        // Arrange - Create an inactive field
        $inactiveField = CustomField::factory()
            ->ofType('text')
            ->inactive()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'sort_order' => 0,
            ]);

        // Create another active field
        $activeField = CustomField::factory()
            ->ofType('number')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'sort_order' => 1,
            ]);

        // Verify initial state
        expect($inactiveField->fresh())->isActive()->toBeFalse();
        expect($activeField->fresh())->isActive()->toBeTrue();

        // Act - Test that the updateFieldsOrder method can handle inactive fields
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->call('updateFieldsOrder', $this->section->getKey(), [$activeField->getKey(), $inactiveField->getKey()]);

        // Assert - Both active and inactive fields can be reordered
        expect($activeField->fresh())->sort_order->toBe(0);
        expect($inactiveField->fresh())->sort_order->toBe(1);

        // Verify the inactive field is still inactive but with new position
        expect($inactiveField->fresh())->isActive()->toBeFalse();
    });

    it('can move fields between active and inactive sections', function (): void {
        // Arrange - Create an active section and an inactive section
        $activeSection = $this->section; // Default section is active
        $inactiveSection = CustomFieldSection::factory()
            ->inactive()
            ->forEntityType($this->userEntityType)
            ->create();

        // Create a field in the active section
        $field = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $activeSection->getKey(),
                'entity_type' => $this->userEntityType,
                'sort_order' => 0,
            ]);

        // Act - Move field from active section to inactive section
        livewire(ManageCustomFieldSection::class, [
            'section' => $inactiveSection,
            'entityType' => $this->userEntityType,
        ])->call('updateFieldsOrder', $inactiveSection->getKey(), [$field->getKey()]);

        // Assert - Field can be moved to inactive section
        expect($field->fresh())->custom_field_section_id->toBe($inactiveSection->getKey());
        expect($field->fresh())->sort_order->toBe(0);

        // Act - Move field back from inactive section to active section
        livewire(ManageCustomFieldSection::class, [
            'section' => $activeSection,
            'entityType' => $this->userEntityType,
        ])->call('updateFieldsOrder', $activeSection->getKey(), [$field->getKey()]);

        // Assert - Field can be moved back to active section
        expect($field->fresh())->custom_field_section_id->toBe($activeSection->getKey());
        expect($field->fresh())->sort_order->toBe(0);
    });

    it('properly updates section fields collection when moving fields into empty sections', function (): void {
        // Arrange - Create an empty section and a field in another section
        $emptySection = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();

        $field = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'sort_order' => 0,
            ]);

        // Verify initial state - empty section has no fields
        $emptySectionComponent = livewire(ManageCustomFieldSection::class, [
            'section' => $emptySection,
            'entityType' => $this->userEntityType,
        ]);

        expect($emptySectionComponent->fields)->toHaveCount(0);

        // Act - Move field to the empty section
        $emptySectionComponent->call('updateFieldsOrder', $emptySection->getKey(), [$field->getKey()]);

        // Assert - The section's fields collection is updated to include the moved field
        expect($emptySectionComponent->fields)->toHaveCount(1);
        expect($emptySectionComponent->fields->first()->id)->toBe($field->getKey());

        // Verify the field was actually moved in the database
        expect($field->fresh())->custom_field_section_id->toBe($emptySection->getKey());
    });

    it('can update field width', function (): void {
        // Arrange
        $field = CustomField::factory()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'width' => 50,
                'type' => 'text',
            ]);

        // Act
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->call('fieldWidthUpdated', $field->getKey(), 100);

        // Assert
        $this->assertDatabaseHas(CustomField::class, [
            'id' => $field->getKey(),
            'width' => 100,
        ]);
    });

    it('notifies instead of 500ing when a drag would create a duplicate code within the target section', function (): void {
        $targetSection = $this->section;
        $sourceSection = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();

        $existingField = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $targetSection->getKey(),
                'entity_type' => $this->userEntityType,
                'code' => 'shared_code',
                'sort_order' => 0,
            ]);

        $draggedField = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $sourceSection->getKey(),
                'entity_type' => $this->userEntityType,
                'code' => 'shared_code',
                'sort_order' => 0,
            ]);

        livewire(ManageCustomFieldSection::class, [
            'section' => $targetSection,
            'entityType' => $this->userEntityType,
        ])
            ->call('updateFieldsOrder', $targetSection->getKey(), [$existingField->getKey(), $draggedField->getKey()])
            ->assertNotified();

        expect($draggedField->fresh())
            ->custom_field_section_id->toBe($sourceSection->getKey());
    });

});

describe('ManageCustomField - Field Actions', function (): void {
    beforeEach(function (): void {
        $this->section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();

        $this->field = CustomField::factory()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'type' => 'text',
            ]);
    });

    it('can activate an inactive field', function (): void {
        // Arrange
        $inactiveField = CustomField::factory()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'active' => false,
                'type' => 'text',
            ]);

        // Act
        livewire(ManageCustomField::class, [
            'field' => $inactiveField,
        ])->callAction('activate');

        // Assert
        $this->assertDatabaseHas(CustomField::class, [
            'id' => $inactiveField->getKey(),
            'active' => true,
        ]);
    });

    it('can deactivate an active field', function (): void {
        // Act
        livewire(ManageCustomField::class, [
            'field' => $this->field,
        ])->callAction('deactivate');

        // Assert
        $this->assertDatabaseHas(CustomField::class, [
            'id' => $this->field->getKey(),
            'active' => false,
        ]);
    });

    it('can delete an inactive non-system field', function (): void {
        // Arrange
        $deletableField = CustomField::factory()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'active' => false,
                'system_defined' => false,
                'type' => 'text',
            ]);

        // Act
        livewire(ManageCustomField::class, [
            'field' => $deletableField,
        ])->callAction('delete');

        // Assert
        $this->assertDatabaseMissing(CustomField::class, [
            'id' => $deletableField->getKey(),
        ]);
    });

    it('hides delete action for an active field with stored values', function (): void {
        CustomFields::newValueModel()->create([
            'custom_field_id' => $this->field->getKey(),
            'entity_type' => $this->userEntityType,
            'entity_id' => 1,
            'string_value' => 'test value',
        ]);

        livewire(ManageCustomField::class, [
            'field' => $this->field,
        ])->assertActionHidden('delete');
    });

    it('cannot delete a system-defined field', function (): void {
        // Arrange
        $systemField = CustomField::factory()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'active' => false,
                'system_defined' => true,
                'type' => 'text',
            ]);

        // Act & Assert - delete action is hidden for system-defined fields
        livewire(ManageCustomField::class, [
            'field' => $systemField,
        ])->assertActionHidden('delete');
    });

    it('can delete an active field with no values without deactivating first', function (): void {
        $field = CustomField::factory()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'active' => true,
                'system_defined' => false,
                'type' => 'text',
            ]);

        livewire(ManageCustomField::class, [
            'field' => $field,
        ])->callAction('delete');

        $this->assertDatabaseMissing(CustomField::class, [
            'id' => $field->getKey(),
        ]);
    });

    it('cannot delete an active field that has stored values', function (): void {
        $field = CustomField::factory()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'active' => true,
                'system_defined' => false,
                'type' => 'text',
            ]);

        CustomFields::newValueModel()->create([
            'custom_field_id' => $field->getKey(),
            'entity_type' => $this->userEntityType,
            'entity_id' => 1,
            'string_value' => 'test value',
        ]);

        livewire(ManageCustomField::class, [
            'field' => $field,
        ])->assertActionHidden('delete');
    });

    it('can duplicate a field', function (): void {
        $field = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'name' => 'Original Field',
                'code' => 'original_field',
            ]);

        livewire(ManageCustomField::class, [
            'field' => $field,
        ])->callAction('duplicate');

        $clone = CustomField::query()
            ->withDeactivated()
            ->where('code', 'original-field-copy')
            ->first();

        expect($clone)
            ->not->toBeNull()
            ->name->toBe('Original Field (Copy)')
            ->type->toBe('text')
            ->entity_type->toBe($this->userEntityType)
            ->custom_field_section_id->toBe($this->section->getKey())
            ->system_defined->toBeFalse()
            ->active->toBeTrue();
    });

    it('can duplicate a select field with all options', function (): void {
        $field = CustomField::factory()
            ->ofType('select')
            ->withOptions(['Alpha', 'Bravo', 'Charlie'])
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'name' => 'My Select',
                'code' => 'my_select',
            ]);

        livewire(ManageCustomField::class, [
            'field' => $field->fresh(),
        ])->callAction('duplicate');

        $clone = CustomField::query()
            ->withDeactivated()
            ->where('code', 'my-select-copy')
            ->first();

        expect($clone)->not->toBeNull();
        expect($clone->options)->toHaveCount(3);
        expect($clone->options->pluck('name')->sort()->values()->all())
            ->toBe(['Alpha', 'Bravo', 'Charlie']);
    });

    it('can duplicate a select field and preserve option settings', function (): void {
        $field = CustomField::factory()
            ->ofType('select')
            ->withOptions(['Red', 'Blue'])
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'name' => 'Color Picker',
                'code' => 'color_picker',
            ]);

        $field->options->first()->update(['settings' => new CustomFieldOptionSettingsData(color: '#ff0000')]);
        $field->options->last()->update(['settings' => new CustomFieldOptionSettingsData(color: '#0000ff')]);

        livewire(ManageCustomField::class, [
            'field' => $field->fresh(),
        ])->callAction('duplicate');

        $clone = CustomField::query()
            ->withDeactivated()
            ->where('code', 'color-picker-copy')
            ->first();

        expect($clone)->not->toBeNull();
        expect($clone->options)->toHaveCount(2);

        $clonedOptions = $clone->options->sortBy('sort_order')->values();

        expect($clonedOptions[0]->settings->color)->toBe('#ff0000');
        expect($clonedOptions[1]->settings->color)->toBe('#0000ff');
    });

    it('generates unique code when duplicating a field with existing copy', function (): void {
        $field = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'name' => 'My Field',
                'code' => 'my_field',
            ]);

        livewire(ManageCustomField::class, [
            'field' => $field,
        ])->callAction('duplicate');

        livewire(ManageCustomField::class, [
            'field' => $field,
        ])->callAction('duplicate');

        expect(CustomField::query()->withDeactivated()->where('code', 'my-field-copy')->exists())->toBeTrue();
        expect(CustomField::query()->withDeactivated()->where('code', 'my-field-copy-2')->exists())->toBeTrue();
    });

    it('cannot duplicate a system-defined field', function (): void {
        $systemField = CustomField::factory()
            ->ofType('text')
            ->systemDefined()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        livewire(ManageCustomField::class, [
            'field' => $systemField,
        ])->assertActionHidden('duplicate');
    });

    it('creates a tags input field without asking for an option nobody typed', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])
            ->callAction('createField', [
                'name' => 'Labels',
                'code' => 'labels',
                'type' => 'tags-input',
                'entity_type' => $this->userEntityType,
            ])
            ->assertHasNoActionErrors();

        $field = CustomField::query()->withoutGlobalScopes()->where('code', 'labels')->firstOrFail();

        expect($field->type)->toBe('tags-input')
            ->and($field->options)->toBeEmpty();
    });

    it('refuses to create a select field with no options', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])
            ->callAction('createField', [
                'name' => 'Stage',
                'code' => 'stage',
                'type' => 'select',
                'entity_type' => $this->userEntityType,
            ])
            ->assertHasActionErrors(['options' => 'required_unless']);

        expect(CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->exists())->toBeFalse();
    });

    it('sets sort_order on options when creating a select field via storeField', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])
            ->callAction('createField', [
                'name' => 'Status Field',
                'code' => 'status_field',
                'type' => 'select',
                'entity_type' => $this->userEntityType,
                'options' => [
                    ['name' => 'Charlie'],
                    ['name' => 'Alpha'],
                    ['name' => 'Bravo'],
                ],
            ]);

        $field = CustomField::query()
            ->withoutGlobalScopes()
            ->where('code', 'status_field')
            ->first();

        expect($field)->not->toBeNull();

        $options = $field->options;

        expect($options)->toHaveCount(3)
            ->and($options->pluck('name')->all())->toBe(['Charlie', 'Alpha', 'Bravo'])
            ->and($options->pluck('sort_order')->all())->each->not->toBeNull();
    });
});

describe('Enhanced field management with datasets', function (): void {
    beforeEach(function (): void {
        $this->section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();
    });

    it('can handle field state transitions correctly', function (): void {
        $field = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        // Initially active
        expect($field)->toBeActive();

        // Deactivate
        livewire(ManageCustomField::class, [
            'field' => $field,
        ])->callAction('deactivate');

        expect($field->fresh())->toBeInactive();

        // Reactivate
        livewire(ManageCustomField::class, [
            'field' => $field->fresh(),
        ])->callAction('activate');

        expect($field->fresh())->toBeActive();
    });

    it('validates field deletion restrictions correctly', function (): void {
        // System-defined field cannot be deleted - action is hidden
        $systemField = CustomField::factory()
            ->ofType('text')
            ->systemDefined()
            ->inactive()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        livewire(ManageCustomField::class, [
            'field' => $systemField,
        ])->assertActionHidden('delete');

        // Active field with values cannot be deleted
        $activeField = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        CustomFields::newValueModel()->create([
            'custom_field_id' => $activeField->getKey(),
            'entity_type' => $this->userEntityType,
            'entity_id' => 1,
            'string_value' => 'test value',
        ]);

        livewire(ManageCustomField::class, [
            'field' => $activeField,
        ])->assertActionHidden('delete');

        // Only inactive, non-system fields can be deleted
        $deletableField = CustomField::factory()
            ->ofType('text')
            ->inactive()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        livewire(ManageCustomField::class, [
            'field' => $deletableField,
        ])->callAction('delete');

        expect(CustomField::find($deletableField->id))->toBeNull();
    });

    it('handles complex field configurations with options', function (): void {
        $selectField = CustomField::factory()
            ->ofType('select')
            ->withOptions([
                'Option 1',
                'Option 2',
                'Option 3',
            ])
            ->withValidation(['required' => true])
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        expect($selectField)
            ->toHaveFieldType('select')
            ->toHaveCorrectComponent('Select')
            ->toHaveValidationRule('required')
            ->and($selectField->options)->toHaveCount(3);
    });
});

describe('Custom Fields Management Workflow - Phase 2.1', function (): void {
    beforeEach(function (): void {
        $this->section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();
    });

    it('can complete full field lifecycle management', function (): void {
        // Step 1: Create section
        $section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create([
                'name' => 'Test Section',
                'code' => 'test_section',
            ]);

        expect($section)
            ->toBeActive()
            ->name->toBe('Test Section')
            ->code->toBe('test_section');

        // Step 2: Create field with validation
        $field = CustomField::factory()
            ->ofType('text')
            ->required()
            ->withLength(3, 255)
            ->create([
                'custom_field_section_id' => $section->getKey(),
                'entity_type' => $this->userEntityType,
                'name' => 'Test Field',
                'code' => 'test_field',
            ]);

        expect($field)
            ->toHaveFieldType('text')
            ->toHaveValidationRule('required')
            ->toHaveValidationRule('min_length', 3)
            ->toHaveValidationRule('max_length', 255)
            ->toBeActive();

        // Step 3: Test field usage in forms
        livewire(ManageCustomField::class, [
            'field' => $field,
        ])->assertSuccessful();

        // Step 4: Verify field can be managed through Livewire
        livewire(ManageCustomField::class, [
            'field' => $field,
        ])
            ->assertSuccessful()
            ->assertSee($field->name);

        // Step 5: Deactivate field through Livewire
        livewire(ManageCustomField::class, [
            'field' => $field,
        ])
            ->callAction('deactivate')
            ->assertSuccessful();

        expect($field->fresh())->toBeInactive();

        // Step 6: Reactivate field through Livewire
        livewire(ManageCustomField::class, [
            'field' => $field->fresh(),
        ])
            ->callAction('activate')
            ->assertSuccessful();

        expect($field->fresh())->toBeActive();

        // Step 7: Delete field through Livewire (only if inactive)
        livewire(ManageCustomField::class, [
            'field' => $field->fresh(),
        ])
            ->callAction('deactivate')
            ->assertSuccessful();

        $fieldId = $field->id;
        livewire(ManageCustomField::class, [
            'field' => $field->fresh(),
        ])
            ->callAction('delete')
            ->assertSuccessful();

        expect(CustomField::find($fieldId))->toBeNull();
    });

    it('can handle field interdependencies and validation chains', function (): void {
        // Create a trigger field
        $triggerField = CustomField::factory()
            ->ofType('select')
            ->withOptions([
                'Option A',
                'Option B',
                'Option C',
            ])
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'code' => 'trigger_field',
                'name' => 'Trigger Field',
            ]);

        // Create a dependent field with visibility conditions
        $dependentField = CustomField::factory()
            ->ofType('text')
            ->conditionallyVisible('trigger_field', 'equals', 'a')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'code' => 'dependent_field',
                'name' => 'Dependent Field',
            ]);

        // Test that visibility conditions are properly set
        expect($dependentField)
            ->toHaveVisibilityCondition('trigger_field', 'equals', 'a');

        // Create a chain: Field C depends on Field B which depends on Field A
        $fieldB = CustomField::factory()
            ->ofType('number')
            ->conditionallyVisible('trigger_field', 'equals', 'b')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'code' => 'field_b',
                'name' => 'Field B',
            ]);

        $fieldC = CustomField::factory()
            ->ofType('text')
            ->conditionallyVisible('field_b', 'greater_than', '10')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'code' => 'field_c',
                'name' => 'Field C',
            ]);

        expect($fieldB)->toHaveVisibilityCondition('trigger_field', 'equals', 'b')
            ->and($fieldC)->toHaveVisibilityCondition('field_b', 'greater_than', '10');
    });

    it('validates field type component mappings work end-to-end', function (array $fieldTypes, string $expectedComponent): void {
        // Test each field type in the group
        foreach ($fieldTypes as $fieldType) {
            $field = CustomField::factory()
                ->ofType($fieldType)
                ->create([
                    'custom_field_section_id' => $this->section->getKey(),
                    'entity_type' => $this->userEntityType,
                ]);

            // Test through Livewire component that field renders correct component
            livewire(ManageCustomField::class, [
                'field' => $field,
            ])
                ->assertSuccessful()
                ->assertSee($field->name);

            // Verify field type and component mapping
            expect($field)
                ->toHaveFieldType($fieldType)
                ->toHaveCorrectComponent($expectedComponent);
        }
    })->with('field_type_component_mappings');

    it('can handle custom field type registration and discovery', function (): void {
        // Test that all 18 field types are properly discoverable
        // Test that all 18 field types are properly discoverable
        $fieldTypes = [
            'text', 'number', 'currency', 'checkbox', 'toggle',
            'date', 'datetime', 'textarea', 'rich-editor', 'markdown-editor',
            'link', 'color-picker', 'select', 'multi_select', 'radio',
            'checkbox-list', 'tags-input', 'toggle-buttons',
        ];
        expect($fieldTypes)->toHaveCount(18);

        // Test each field type can be created and managed
        foreach ($fieldTypes as $fieldType) {
            $field = CustomField::factory()
                ->ofType($fieldType)
                ->create([
                    'custom_field_section_id' => $this->section->getKey(),
                    'entity_type' => $this->userEntityType,
                ]);

            // Test field management through Livewire
            livewire(ManageCustomField::class, [
                'field' => $field,
            ])
                ->assertSuccessful()
                ->assertSee($field->name);

            expect($field)->toHaveFieldType($fieldType);
        }
    });
    it('loads the stored options into the form when editing a select field', function (): void {
        $selectField = CustomField::factory()
            ->ofType('select')
            ->withOptions([
                'Option 1',
                'Option 2',
            ])
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        $page = livewire(ManageCustomField::class, [
            'field' => $selectField,
        ])
            ->assertSuccessful()
            ->mountAction('edit', ['record' => $selectField->getKey()])
            ->assertActionMounted('edit')
            ->assertSchemaComponentVisible('options');

        $component = $page->instance();
        $schema = $component->{$component->getMountedActionSchemaName()};

        expect(collect($schema->getRawState()['options'])->pluck('name')->all())
            ->toBe(['Option 1', 'Option 2']);
    });

    it('can handle field section management and organization', function (): void {
        // Create multiple sections
        $sections = CustomFieldSection::factory(3)
            ->sequence(
                ['name' => 'Personal Info', 'code' => 'personal'],
                ['name' => 'Professional Info', 'code' => 'professional'],
                ['name' => 'Preferences', 'code' => 'preferences']
            )
            ->forEntityType($this->userEntityType)
            ->create();

        // Create fields in each section
        $sections->each(function ($section, $index): void {
            CustomField::factory(2)
                ->sequence(
                    ['code' => sprintf('field_%d_1', $index), 'sort_order' => 1],
                    ['code' => sprintf('field_%d_2', $index), 'sort_order' => 2]
                )
                ->create([
                    'custom_field_section_id' => $section->getKey(),
                    'entity_type' => $this->userEntityType,
                ]);
        });

        // Test section organization
        expect($sections)->toHaveCount(3);
        $sections->each(function ($section): void {
            expect($section->fields)->toHaveCount(2);
            expect($section->fields->first()->sort_order)->toBe(1);
            expect($section->fields->last()->sort_order)->toBe(2);
        });

        // Test section management
        $section = $sections->first();
        livewire(ManageCustomFieldSection::class, [
            'section' => $section,
            'entityType' => $this->userEntityType,
        ])->assertSuccessful();
    });

    it('can handle system-defined vs user-defined field workflows', function (): void {
        // Create user-defined field
        $userField = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'system_defined' => false,
                'code' => 'user_field',
            ]);

        // Create system-defined field
        $systemField = CustomField::factory()
            ->ofType('text')
            ->systemDefined()
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'system_defined' => true,
                'code' => 'system_field',
            ]);

        // Test that user field can be deleted when inactive through Livewire
        livewire(ManageCustomField::class, [
            'field' => $userField,
        ])
            ->callAction('deactivate')
            ->assertSuccessful()
            ->callAction('delete')
            ->assertSuccessful();

        expect(CustomField::find($userField->id))->toBeNull();

        // Test that system field cannot be deactivated or deleted (actions are hidden)
        livewire(ManageCustomField::class, [
            'field' => $systemField,
        ])
            ->assertActionHidden('deactivate')
            ->assertActionHidden('delete');

        // System field should still exist and remain active
        expect($systemField->fresh())
            ->not->toBeNull()
            ->active->toBeTrue();
    });
});

describe('ManageCustomField - Code Stability On Rename', function (): void {
    beforeEach(function (): void {
        $this->section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();
    });

    it('keeps a persisted fields code stable when the name is renamed', function (): void {
        $field = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'name' => 'HMIS ID',
                'code' => 'hmis_id',
            ]);

        // The code is an identity: stored values, report columns and visibility
        // conditions key on it, and a field cloned onto a new form version shares
        // it with the original. Renaming must not rewrite it, even though the code
        // still matches the slug of the previous name.
        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->set('mountedActions.0.data.name', 'HMIS ID (Q/A testing added)')
            ->assertSet('mountedActions.0.data.code', 'hmis_id')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($field->refresh())
            ->code->toBe('hmis_id')
            ->name->toBe('HMIS ID (Q/A testing added)');
    });
});

describe('ManageFieldsTable - Field Management', function (): void {
    it('creates a tags input field without asking for an option nobody typed', function (): void {
        CustomFieldSection::factory()->forEntityType(Post::class)->create();

        livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->callAction('createField', [
                'name' => 'Labels',
                'code' => 'labels',
                'type' => 'tags-input',
                'entity_type' => Post::class,
            ])
            ->assertHasNoActionErrors();

        $field = CustomField::query()->withoutGlobalScopes()->where('code', 'labels')->firstOrFail();

        expect($field->type)->toBe('tags-input')
            ->and($field->options)->toBeEmpty();
    });

    it('refuses to create a select field with no options', function (): void {
        CustomFieldSection::factory()->forEntityType(Post::class)->create();

        livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->callAction('createField', [
                'name' => 'Stage',
                'code' => 'stage',
                'type' => 'select',
                'entity_type' => Post::class,
            ])
            ->assertHasActionErrors(['options' => 'required_unless']);

        expect(CustomField::query()->withoutGlobalScopes()->where('code', 'stage')->exists())->toBeFalse();
    });

    it('edits a select field without duplicating its stored options', function (): void {
        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create();

        $field = CustomField::factory()
            ->ofType('select')
            ->withOptions(['Option 1', 'Option 2'])
            ->create([
                'custom_field_section_id' => $section->getKey(),
                'entity_type' => Post::class,
            ]);

        livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->mountAction('editField', ['fieldId' => $field->getKey()])
            ->assertActionDataSet(['name' => $field->name])
            ->set('mountedActions.0.data.name', 'Renamed Field')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($field->refresh()->name)->toBe('Renamed Field')
            ->and($field->options()->pluck('name')->all())->toBe(['Option 1', 'Option 2']);
    });
});

function pairedCommentAuthorship(CustomFieldSection $postSection, CustomFieldSection $commentSection): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'comment_authorship',
        fromEntityType: Post::class,
        toEntityType: Comment::class,
        cardinality: RelationshipCardinality::ManyToOne,
        fromField: new FieldSlotData(name: 'Lead Comment', sectionId: $postSection->getKey(), type: RelationshipFieldType::KEY),
        toField: new FieldSlotData(name: 'Leads For', sectionId: $commentSection->getKey(), type: RelationshipFieldType::KEY),
    ));
}

describe('Record field configuration', function (): void {
    beforeEach(function (): void {
        $this->postSection = CustomFieldSection::factory()->forEntityType(Post::class)->create();
        $this->commentSection = CustomFieldSection::factory()->forEntityType(Comment::class)->create();
    });

    it('creates a one-way record field on a definition of its own', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->postSection,
            'entityType' => Post::class,
        ])
            ->callAction('createField', [
                'name' => 'Related Comment',
                'code' => 'related_comment',
                'type' => 'record',
                'entity_type' => Post::class,
                'relationship' => [
                    'target_entity_type' => Comment::class,
                    'cardinality' => RelationshipCardinality::ManyToOne->value,
                ],
            ])
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->from_entity_type)->toBe(Post::class)
            ->and($definition->to_entity_type)->toBe(Comment::class)
            ->and($definition->cardinality)->toBe(RelationshipCardinality::ManyToOne)
            ->and($definition->is_symmetric)->toBeFalse()
            ->and($definition->fromField->code)->toBe('related_comment')
            ->and($definition->to_field_id)->toBeNull()
            ->and(CustomField::query()->count())->toBe(1);
    });

    it('creates the paired field on the target entity when it is named', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->postSection,
            'entityType' => Post::class,
        ])
            ->callAction('createField', [
                'name' => 'Related Comment',
                'code' => 'related_comment',
                'type' => RelationshipFieldType::KEY,
                'entity_type' => Post::class,
                'relationship' => [
                    'target_entity_type' => Comment::class,
                    'cardinality' => RelationshipCardinality::ManyToMany->value,
                    'paired_field_name' => 'Related Post',
                    'paired_section_id' => $this->commentSection->getKey(),
                ],
            ])
            ->assertHasNoActionErrors();

        $definition = CustomFieldRelationship::query()->sole();

        expect(CustomField::query()->count())->toBe(2)
            ->and($definition->fromField->code)->toBe('related_comment')
            ->and($definition->toField->name)->toBe('Related Post')
            ->and($definition->toField->entity_type)->toBe(Comment::class)
            ->and($definition->toField->custom_field_section_id)->toBe($this->commentSection->getKey());
    });

    it('loads the definition into the edit form and keeps the ends where they are', function (): void {
        $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'related_comment',
            fromEntityType: Post::class,
            toEntityType: Comment::class,
            cardinality: RelationshipCardinality::ManyToMany,
            fromField: new FieldSlotData(name: 'Related Comment', sectionId: $this->postSection->getKey()),
        ));

        livewire(ManageCustomField::class, ['field' => $definition->fromField])
            ->mountAction('edit')
            ->assertActionDataSet([
                'relationship.target_entity_type' => Comment::class,
                'relationship.cardinality' => RelationshipCardinality::ManyToMany->value,
                'relationship.is_symmetric' => false,
            ])
            ->set('mountedActions.0.data.relationship.target_entity_type', Post::class)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($definition->refresh()->to_entity_type)->toBe(Comment::class);
    });

    it('gives a duplicated record field a definition of its own', function (): void {
        $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'related_comment',
            fromEntityType: Post::class,
            toEntityType: Comment::class,
            cardinality: RelationshipCardinality::ManyToMany,
            fromField: new FieldSlotData(name: 'Related Comment', sectionId: $this->postSection->getKey()),
        ));

        livewire(ManageCustomField::class, ['field' => $definition->fromField])
            ->callAction('duplicate');

        $copy = CustomField::query()->whereKeyNot($definition->from_field_id)->sole();

        expect(CustomFieldRelationship::query()->count())->toBe(2)
            ->and($copy->targetEntityType())->toBe(Comment::class)
            ->and($copy->relationshipDefinition()->cardinality)->toBe(RelationshipCardinality::ManyToMany);
    });

    it('pairs onto an entity with no sections by creating a default one', function (): void {
        $this->commentSection->delete();

        livewire(ManageCustomFieldSection::class, [
            'section' => $this->postSection,
            'entityType' => Post::class,
        ])
            ->callAction('createField', [
                'name' => 'Related Comment',
                'code' => 'related_comment',
                'type' => RelationshipFieldType::KEY,
                'entity_type' => Post::class,
                'relationship' => [
                    'target_entity_type' => Comment::class,
                    'cardinality' => RelationshipCardinality::ManyToMany->value,
                    'paired_field_name' => 'Related Post',
                ],
            ])
            ->assertHasNoActionErrors();

        $paired = CustomFieldRelationship::query()->sole()->toField;

        expect($paired->name)->toBe('Related Post')
            ->and($paired->section)->not->toBeNull()
            ->and($paired->section->entity_type)->toBe(Comment::class)
            ->and(CustomField::query()->whereKey($paired->getKey())->exists())->toBeTrue();
    });

    it('never offers a symmetric toggle across an entity the host has not registered', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->postSection,
            'entityType' => Post::class,
        ])
            ->mountAction('createField')
            ->set('mountedActions.0.data.type', RelationshipFieldType::KEY)
            ->set('mountedActions.0.data.entity_type', 'ghost_entity')
            ->set('mountedActions.0.data.relationship.target_entity_type', 'other_ghost_entity')
            ->assertSchemaComponentHidden('relationship.is_symmetric');
    });

    it('keeps a duplicated to-end field pointing the way it read', function (): void {
        $definition = pairedCommentAuthorship($this->postSection, $this->commentSection);
        $toField = $definition->toField;

        expect($toField->allowsMultipleRecords())->toBeTrue();

        livewire(ManageCustomField::class, ['field' => $toField])
            ->callAction('duplicate');

        $copy = CustomField::query()
            ->where('entity_type', Comment::class)
            ->whereKeyNot($toField->getKey())
            ->sole();

        expect($copy->targetEntityType())->toBe(Post::class)
            ->and($copy->allowsMultipleRecords())->toBeTrue()
            ->and($copy->relationshipDefinition()->cardinality)->toBe(RelationshipCardinality::OneToMany);
    });

    it('shows and stores a to-end field the cardinality from its own side', function (): void {
        $definition = pairedCommentAuthorship($this->postSection, $this->commentSection);

        livewire(ManageCustomField::class, ['field' => $definition->toField])
            ->mountAction('edit')
            ->assertActionDataSet(['relationship.cardinality' => RelationshipCardinality::OneToMany->value])
            ->set('mountedActions.0.data.relationship.cardinality', RelationshipCardinality::ManyToOne->value)
            ->set('mountedActions.0.data.relationship.keep_first', true)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::OneToMany)
            ->and($definition->toField->allowsMultipleRecords())->toBeFalse();
    });

    it('narrows the cardinality on confirmation, keeping the first linked record', function (): void {
        $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'related_comment',
            fromEntityType: Post::class,
            toEntityType: Comment::class,
            cardinality: RelationshipCardinality::ManyToMany,
            fromField: new FieldSlotData(name: 'Related Comment', sectionId: $this->postSection->getKey()),
        ));

        $field = $definition->fromField;
        [$first, $second] = Comment::factory()->count(2)->create();
        $post = Post::factory()->create(['custom_fields' => [$field->code => [$first->getKey(), $second->getKey()]]]);

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->set('mountedActions.0.data.relationship.allow_multiple', false)
            ->set('mountedActions.0.data.relationship.keep_first', true)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToOne)
            ->and($post->fresh()->getCustomFieldValue($field->fresh()))->toBe([$first->getKey()])
            ->and(CustomFieldLink::query()->whereNotNull('active_until')->count())->toBe(1);
    });
});

describe('System-only field types', function (): void {
    beforeEach(function (): void {
        CustomFieldsType::register([SystemProbeFieldType::class, SystemFirstFieldType::class]);

        $this->section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();
    });

    it('keeps a system-only type out of the selectable types', function (): void {
        expect(CustomFieldsType::toCollection()->pluck('key'))->toContain('system-probe')
            ->and(CustomFieldsType::toCollection()->selectable()->pluck('key'))->not->toContain('system-probe')
            ->and(CustomFieldsType::toCollection()->selectable()->pluck('key'))->toContain('text');
    });

    it('keeps the type of the field being edited selectable', function (): void {
        expect(CustomFieldsType::toCollection()->selectable('system-probe')->pluck('key'))->toContain('system-probe');
    });

    it('rejects a system-only type sent to the create form', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])
            ->callAction('createField', [
                'name' => 'Probe',
                'code' => 'probe',
                'type' => 'system-probe',
                'entity_type' => $this->userEntityType,
            ])
            ->assertHasActionErrors(['type']);

        expect(CustomField::query()->withoutGlobalScopes()->where('code', 'probe')->exists())->toBeFalse();
    });

    it('still creates a field of a selectable type', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])
            ->callAction('createField', [
                'name' => 'Plain',
                'code' => 'plain',
                'type' => 'text',
                'entity_type' => $this->userEntityType,
            ])
            ->assertHasNoActionErrors();
    });

    it('cannot duplicate a field of a system-only type', function (): void {
        $field = CustomField::factory()
            ->ofType('system-probe')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'system_defined' => false,
            ]);

        livewire(ManageCustomField::class, [
            'field' => $field,
        ])->assertActionHidden('duplicate');
    });

    it('defaults the create form to the first selectable type', function (): void {
        $firstSelectable = CustomFieldsType::toCollection()->selectable()->first()->key;

        expect(CustomFieldsType::toCollection()->first()->key)->toBe('system-first');

        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])
            ->mountAction('createField')
            ->assertSet('mountedActions.0.data.type', $firstSelectable);
    });

    it('mounts and saves the edit form of a field of a system-only type', function (): void {
        $field = CustomField::factory()
            ->ofType('system-probe')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'name' => 'Probe',
                'code' => 'probe',
                'system_defined' => false,
            ]);

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->assertSet('mountedActions.0.data.type', 'system-probe')
            ->set('mountedActions.0.data.name', 'Probe renamed')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($field->refresh())
            ->type->toBe('system-probe')
            ->name->toBe('Probe renamed');
    });

    it('shows the system-only type of the field being edited', function (): void {
        $field = CustomField::factory()
            ->ofType('system-probe')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
                'system_defined' => false,
            ]);

        $component = livewire(ManageCustomField::class, ['field' => $field])->mountAction('edit');

        expect($component->instance()->getSchemaComponent('mountedActionSchema0.type')->getOptions())->toHaveKey('system-probe');
    });

    it('leaves a system-only type out of the type search results', function (): void {
        $component = livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->mountAction('createField');

        $search = fn (string $term): array => collect($component->instance()->callSchemaComponentMethod('mountedActionSchema0.type', 'getSearchResultsForJs', ['search' => $term]))
            ->pluck('value')
            ->all();

        expect($search('probe'))->not->toContain('system-probe')
            ->and($search('system'))->not->toContain('system-first')
            ->and($search('text'))->toContain('text');
    });
});
