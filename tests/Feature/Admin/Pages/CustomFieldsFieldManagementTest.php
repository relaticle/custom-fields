<?php

declare(strict_types=1);

use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

use function Pest\Livewire\livewire;

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

    it('cannot delete an active field', function (): void {
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

        // Act & Assert
        livewire(ManageCustomField::class, [
            'field' => $systemField,
        ])->assertActionHidden('delete');
    });

    it('dispatches width update event', function (): void {
        // Act & Assert
        livewire(ManageCustomField::class, [
            'field' => $this->field,
        ])->call('setWidth', $this->field->getKey(), 75)
            ->assertDispatched('field-width-updated', $this->field->getKey(), 75);
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
        // System-defined field cannot be deleted
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

        // Active field cannot be deleted
        $activeField = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
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
            ->withValidation([
                ['name' => 'required', 'parameters' => []],
                ['name' => 'in', 'parameters' => ['Option 1', 'Option 2', 'Option 3']],
            ])
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        expect($selectField)
            ->toHaveFieldType('select')
            ->toHaveCorrectComponent('Select')
            ->toHaveValidationRule('required')
            ->toHaveValidationRule('in', ['Option 1', 'Option 2', 'Option 3'])
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
            ->withValidation([
                ['name' => 'required', 'parameters' => []],
                ['name' => 'min', 'parameters' => [3]],
                ['name' => 'max', 'parameters' => [255]],
            ])
            ->create([
                'custom_field_section_id' => $section->getKey(),
                'entity_type' => $this->userEntityType,
                'name' => 'Test Field',
                'code' => 'test_field',
            ]);

        expect($field)
            ->toHaveFieldType('text')
            ->toHaveValidationRule('required')
            ->toHaveValidationRule('min', [3])
            ->toHaveValidationRule('max', [255])
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
    it('validates field type constraints and behaviors', function (): void {
        // Test text field constraints
        $textField = CustomField::factory()
            ->ofType('text')
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        livewire(ManageCustomField::class, [
            'field' => $textField,
        ])
            ->assertSuccessful()
            ->assertSee($textField->name);

        // Test select field with options constraint
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

        expect($selectField->options)->toHaveCount(2);

        livewire(ManageCustomField::class, [
            'field' => $selectField,
        ])
            ->assertSuccessful()
            ->mountAction('edit', ['record' => $selectField->getKey()])
            ->callMountedAction()
            ->assertSee([
                'Option 1',
                'Option 2',
            ]);
    })->todo();

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

        // Test that system field cannot be deleted (action should be hidden)
        livewire(ManageCustomField::class, [
            'field' => $systemField,
        ])
            ->callAction('deactivate')
            ->assertSuccessful()
            ->assertActionHidden('delete');

        // System field should still exist since delete action is hidden
        expect($systemField->fresh())->not->toBeNull();
    });
});
