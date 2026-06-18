<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Livewire\Component;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

function mountedActionReferenceOptions(Component $instance): array
{
    $method = new ReflectionMethod($instance, 'getMountedActionSchema');
    $method->setAccessible(true);

    /** @var Schema $schema */
    $schema = $method->invoke($instance);

    /** @var Select $select */
    $select = $schema->getComponent(
        fn ($component): bool => $component instanceof Select
            && str($component->getStatePath())->endsWith('validation_rules.min_date.field_reference'),
        withHidden: true,
    );

    return $select->getOptions();
}

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->entityType = User::class;

    $this->section = CustomFieldSection::factory()
        ->forEntityType($this->entityType)
        ->create();

    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_VALIDATION_RULES,
            CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )
    );

    $this->startField = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'Start Date',
            'code' => 'start_date',
        ]);
});

it('lists sibling date fields in the reference dropdown when creating a new field', function (): void {
    $component = livewire(ManageCustomFieldSection::class, [
        'section' => $this->section,
        'entityType' => $this->entityType,
    ])->mountAction('createField')->setActionData([
        'type' => 'date',
        'name' => 'End Date',
        'code' => 'end_date',
        'validation_rules' => [
            'min_date' => ['preset' => 'custom_field', 'anchor' => 'custom_field'],
        ],
    ])->instance();

    expect(mountedActionReferenceOptions($component))
        ->toBe(['start_date' => 'Start Date']);
});

it('lists sibling date fields in the reference dropdown when editing a field', function (): void {
    $endField = CustomField::factory()
        ->ofType('date')
        ->create([
            'custom_field_section_id' => $this->section->getKey(),
            'entity_type' => $this->entityType,
            'name' => 'End Date',
            'code' => 'end_date',
        ]);

    $component = livewire(ManageCustomField::class, ['field' => $endField])
        ->mountAction('edit')
        ->setActionData([
            'validation_rules' => [
                'min_date' => ['preset' => 'custom_field', 'anchor' => 'custom_field'],
            ],
        ])->instance();

    expect(mountedActionReferenceOptions($component))
        ->toBe(['start_date' => 'Start Date']);
});
