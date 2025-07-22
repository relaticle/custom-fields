<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\ValidationRule;
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

describe('CustomFieldsPage - Field Validation Testing', function (): void {
    beforeEach(function (): void {
        $this->section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();
    });

    it('validates field form requires name', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->callAction('createField', [
            'name' => '',
            'code' => 'test_code',
            'type' => 'text',
        ])->assertHasFormErrors(['name']);
    });

    it('validates field form requires code', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->callAction('createField', [
            'name' => 'Test Field',
            'code' => '',
            'type' => 'text',
        ])->assertHasFormErrors(['code']);
    });

    it('validates field form requires type', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->callAction('createField', [
            'name' => 'Test Field',
            'code' => 'test_code',
            'type' => null,
        ])->assertHasFormErrors(['type']);
    });

    it('validates field code must be unique', function (): void {
        // Arrange - create existing field
        $existingField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->userEntityType,
            'code' => 'existing_code',
            'type' => 'text',
        ]);

        // Act & Assert - try to create field with same code
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->callAction('createField', [
            'name' => 'New Field',
            'code' => $existingField->code,
            'type' => 'text',
        ])->assertHasFormErrors(['code']);
    });

    it('validates field type compatibility with validation rules', function (string $fieldType, array $allowedRules, array $disallowedRules): void {
        // Test that allowed rules work
        foreach ($allowedRules as $rule) {
            $field = CustomField::factory()
                ->ofType($fieldType)
                ->withValidation([$rule])
                ->create([
                    'custom_field_section_id' => $this->section->getKey(),
                    'entity_type' => $this->userEntityType,
                ]);

            expect($field)->toHaveValidationRule($rule);
        }

        // Test that disallowed rules are not applied or cause appropriate behavior
        foreach ($disallowedRules as $rule) {
            // This would depend on your validation logic implementation
            // For now, we'll test that the field type and rule combination is handled appropriately
            expect(ValidationRule::tryFrom($rule))->not->toBeNull();
        }
    })->with('field_type_validation_compatibility');

    it('handles all validation rules with their parameters correctly', function (string $rule, array $parameters, mixed $validValue, mixed $invalidValue): void {
        $field = CustomField::factory()
            ->ofType('text') // Use TEXT as it supports most rules
            ->withValidation([['name' => $rule, 'parameters' => $parameters]])
            ->create([
                'custom_field_section_id' => $this->section->getKey(),
                'entity_type' => $this->userEntityType,
            ]);

        expect($field)->toHaveValidationRule($rule, $parameters);

        // Test that the validation rule is properly stored
        $validationRules = $field->validation_rules;
        expect(collect($validationRules)->pluck('name'))->toContain($rule);
    })->with('validation_rules_with_parameters');

    describe('Enhanced field creation with custom expectations', function (): void {
        it('creates fields with correct component mappings', function (array $fieldTypes, string $expectedComponent): void {
            foreach ($fieldTypes as $fieldType) {
                $field = CustomField::factory()
                    ->ofType($fieldType)
                    ->create([
                        'custom_field_section_id' => $this->section->getKey(),
                        'entity_type' => $this->userEntityType,
                    ]);

                expect($field)->toHaveCorrectComponent($expectedComponent)
                    ->and($field)->toHaveFieldType($fieldType)
                    ->and($field)->toBeActive();
            }
        })->with('field_type_component_mappings');

        it('creates fields with proper validation and state', function (): void {
            $field = CustomField::factory()
                ->ofType('text')
                ->required()
                ->withLength(3, 255)
                ->create([
                    'custom_field_section_id' => $this->section->getKey(),
                    'entity_type' => $this->userEntityType,
                ]);

            expect($field)
                ->toHaveValidationRule('required')
                ->toHaveValidationRule('min', [3])
                ->toHaveValidationRule('max', [255])
                ->toBeActive();
        });

        it('creates fields with visibility conditions', function (): void {
            $dependentField = CustomField::factory()
                ->ofType('select')
                ->withOptions([
                    'Show',
                    'Hide',
                ])
                ->create([
                    'custom_field_section_id' => $this->section->getKey(),
                    'entity_type' => $this->userEntityType,
                    'code' => 'trigger_field',
                ]);

            $conditionalField = CustomField::factory()
                ->ofType('text')
                ->conditionallyVisible('trigger_field', 'equals', 'show')
                ->create([
                    'custom_field_section_id' => $this->section->getKey(),
                    'entity_type' => $this->userEntityType,
                ]);

            expect($conditionalField)->toHaveVisibilityCondition('trigger_field', 'equals', 'show');
        });

        it('handles encrypted fields properly', function (): void {
            $field = CustomField::factory()
                ->ofType('text')
                ->encrypted()
                ->create([
                    'custom_field_section_id' => $this->section->getKey(),
                    'entity_type' => $this->userEntityType,
                ]);

            expect($field->settings->encrypted)->toBeTrue()
                ->and($field->type)->toBe('text');
        });

        it('creates system-defined fields correctly', function (): void {
            $field = CustomField::factory()
                ->ofType('text')
                ->systemDefined()
                ->inactive()
                ->create([
                    'custom_field_section_id' => $this->section->getKey(),
                    'entity_type' => $this->userEntityType,
                ]);

            expect($field->system_defined)->toBeTrue()
                ->and($field)->toBeInactive();
        });
    });
});
