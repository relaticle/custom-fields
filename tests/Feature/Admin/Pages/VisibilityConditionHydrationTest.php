<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;
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
            CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS,
            CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )
    );
});

it('mounts the edit action without error when a visibility condition has an array value (IS_IN regression)', function (): void {
    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();

    $field = CustomField::factory()
        ->ofType('text')
        ->withVisibility([
            [
                'field_code' => 'some_relation_field',
                'operator' => VisibilityOperator::IS_IN->value,
                'value' => [$tag1->id, $tag2->id],
            ],
        ])
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Relation-gated field',
            'code' => 'relation_gated_field',
        ]);

    livewire(ManageCustomField::class, ['field' => $field])
        ->mountAction('edit')
        ->assertActionMounted('edit');
});
