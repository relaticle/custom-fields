<?php

declare(strict_types=1);

// Tests that FormBuilder::getDependentFieldCodes() does not access the
// non-existent `visibility_conditions` attribute on CustomField, which throws
// MissingAttributeException when Model::preventAccessingMissingAttributes() is
// enabled (Laravel strict mode).  Regression test for issue #149.

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Filament\Integration\Builders\FormBuilder;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
            CustomFieldsFeature::UI_TABLE_COLUMNS,
            CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS,
            CustomFieldsFeature::UI_TABLE_FILTERS,
            CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )
    );

    $this->section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create();
});

afterEach(function (): void {
    // Disable strict mode after each test so other tests are not affected.
    Model::preventAccessingMissingAttributes(false);
});

describe('FormBuilder strict-mode regression (issue #149)', function (): void {
    it('does not throw MissingAttributeException when Model::preventAccessingMissingAttributes() is enabled and a field has visibility conditions', function (): void {
        // Arrange — two custom fields; the second is conditionally visible based on the first.
        $controlField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Control field',
            'code' => 'control_field',
            'type' => 'select',
            'entity_type' => Post::class,
        ]);

        $dependentField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Dependent field',
            'code' => 'dependent_field',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'control_field',
                        'operator' => VisibilityOperator::IS_NOT_EMPTY,
                        'value' => null,
                        'source' => ConditionSource::CustomField,
                    ]],
                ],
            ],
        ]);

        // Enable Laravel strict mode — this is the exact flag that triggers
        // MissingAttributeException on unknown attribute access.
        Model::preventAccessingMissingAttributes();

        // Act — building the form must not throw.
        $builder = app(FormBuilder::class)->forModel(new Post);

        expect(fn () => $builder->values())->not->toThrow(\Illuminate\Database\Eloquent\MissingAttributeException::class);
    });

    it('correctly resolves dependent field codes via CoreVisibilityLogicService in strict mode', function (): void {
        // Arrange
        $controlField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Status',
            'code' => 'status',
            'type' => 'select',
            'entity_type' => Post::class,
        ]);

        $dependentField = CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'name' => 'Priority',
            'code' => 'priority',
            'type' => 'text',
            'entity_type' => Post::class,
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'status',
                        'operator' => VisibilityOperator::IS_NOT_EMPTY,
                        'value' => null,
                        'source' => ConditionSource::CustomField,
                    ]],
                ],
            ],
        ]);

        Model::preventAccessingMissingAttributes();

        // Act — render via Livewire to exercise the full FormContainer → FormBuilder path.
        $test = livewire(CreatePost::class);

        // Assert — the form renders without exception.
        $test->assertSuccessful();
    });
});
