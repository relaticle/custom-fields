<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Integration\Factories\SectionComponentFactory;
use Relaticle\CustomFields\Filament\Integration\Factories\SectionInfolistsFactory;
use Relaticle\CustomFields\Filament\Management\Pages\CustomFieldsManagementPage;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

it('defaults a new section width to 100', function (): void {
    $section = CustomFieldSection::factory()->create(['entity_type' => Post::class]);

    expect($section->width)->toBe(CustomFieldWidth::_100);
});

it('casts the stored section width to the CustomFieldWidth enum', function (): void {
    $section = CustomFieldSection::factory()
        ->width(CustomFieldWidth::_50)
        ->create(['entity_type' => Post::class]);

    expect($section->refresh()->width)->toBe(CustomFieldWidth::_50);

    $this->assertDatabaseHas(CustomFieldSection::class, [
        'id' => $section->id,
        'width' => '50',
    ]);
});

it('has UI_SECTION_WIDTH_CONTROL disabled by default', function (): void {
    expect(FeatureManager::isEnabled(CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL))->toBeFalse();
});

it('renders a fractional column span when the flag is on and width is non-100', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SYSTEM_SECTIONS, CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL)]);

    $section = CustomFieldSection::factory()
        ->width(CustomFieldWidth::_50)
        ->create(['entity_type' => Post::class]);

    $component = app(SectionComponentFactory::class)->create($section);

    expect($component->getColumnSpan('lg'))->toBe(6);
});

it('renders full width when the flag is on but width is 100', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SYSTEM_SECTIONS, CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL)]);

    $section = CustomFieldSection::factory()
        ->width(CustomFieldWidth::_100)
        ->create(['entity_type' => Post::class]);

    $component = app(SectionComponentFactory::class)->create($section);

    expect($component->getColumnSpan('default'))->toBe('full');
});

it('ignores section width when the flag is off', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SYSTEM_SECTIONS)]);

    $section = CustomFieldSection::factory()
        ->width(CustomFieldWidth::_50)
        ->create(['entity_type' => Post::class]);

    $component = app(SectionComponentFactory::class)->create($section);

    expect($component->getColumnSpan('default'))->toBe('full');
});

it('renders a legacy NULL-width section at full width without error', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SYSTEM_SECTIONS, CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL)]);

    $section = CustomFieldSection::factory()->create(['entity_type' => Post::class]);
    CustomFieldSection::query()->whereKey($section->id)->update(['width' => null]);
    $section = CustomFieldSection::query()->findOrFail($section->id);

    expect($section->width)->toBeNull();

    $component = app(SectionComponentFactory::class)->create($section);

    expect($component->getColumnSpan('default'))->toBe('full');
});

it('applies the same width rules on the infolist path', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SYSTEM_SECTIONS, CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL)]);

    $section = CustomFieldSection::factory()
        ->width(CustomFieldWidth::_50)
        ->create(['entity_type' => Post::class]);

    $component = app(SectionInfolistsFactory::class)->create($section);

    expect($component->getColumnSpan('lg'))->toBe(6);
});

it('persists a chosen section width from the management form when the flag is on', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_SECTIONS,
        CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
        CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL,
    )]);

    $this->actingAs(User::factory()->create());

    livewire(CustomFieldsManagementPage::class)
        ->call('setCurrentEntityType', Post::class)
        ->callAction('createSection', [
            'name' => 'Function Info',
            'code' => 'function_info',
            'width' => CustomFieldWidth::_50->value,
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(CustomFieldSection::class, [
        'code' => 'function_info',
        'entity_type' => Post::class,
        'width' => '50',
    ]);
});

it('does not persist section width from the form when the flag is off', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_SECTIONS,
        CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
    )]);

    $this->actingAs(User::factory()->create());

    livewire(CustomFieldsManagementPage::class)
        ->call('setCurrentEntityType', Post::class)
        ->callAction('createSection', [
            'name' => 'Responsibility Info',
            'code' => 'responsibility_info',
            'width' => CustomFieldWidth::_50->value,
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(CustomFieldSection::class, [
        'code' => 'responsibility_info',
        'entity_type' => Post::class,
        'width' => '100',
    ]);
});

it('drops a submitted width for a headless section because the field is hidden for that type', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_SECTIONS,
        CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
        CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL,
    )]);

    $this->actingAs(User::factory()->create());

    livewire(CustomFieldsManagementPage::class)
        ->call('setCurrentEntityType', Post::class)
        ->callAction('createSection', [
            'name' => 'Headless Info',
            'code' => 'headless_info',
            'type' => CustomFieldSectionType::HEADLESS->value,
            'width' => CustomFieldWidth::_50->value,
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(CustomFieldSection::class, [
        'code' => 'headless_info',
        'entity_type' => Post::class,
        'type' => CustomFieldSectionType::HEADLESS->value,
        'width' => '100',
    ]);
});

it('persists a submitted width for a fieldset section because the field is shown for that type', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_SECTIONS,
        CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
        CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL,
    )]);

    $this->actingAs(User::factory()->create());

    livewire(CustomFieldsManagementPage::class)
        ->call('setCurrentEntityType', Post::class)
        ->callAction('createSection', [
            'name' => 'Fieldset Info',
            'code' => 'fieldset_info',
            'type' => CustomFieldSectionType::FIELDSET->value,
            'width' => CustomFieldWidth::_50->value,
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(CustomFieldSection::class, [
        'code' => 'fieldset_info',
        'entity_type' => Post::class,
        'type' => CustomFieldSectionType::FIELDSET->value,
        'width' => '50',
    ]);
});

it('formats a legacy NULL width as 100 when opening the edit form', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_SECTIONS,
        CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
        CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL,
    )]);

    $this->actingAs(User::factory()->create());

    $section = CustomFieldSection::factory()->create(['entity_type' => Post::class]);
    CustomFieldSection::query()->whereKey($section->id)->update(['width' => null]);
    $section = CustomFieldSection::query()->findOrFail($section->id);

    $component = livewire(ManageCustomFieldSection::class, [
        'section' => $section,
        'entityType' => Post::class,
    ])->mountAction('edit');

    expect($component->get('mountedActions.0.data.width'))->toBe(CustomFieldWidth::_100->value);
});
