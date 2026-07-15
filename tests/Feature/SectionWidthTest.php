<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Integration\Factories\SectionComponentFactory;
use Relaticle\CustomFields\Filament\Integration\Factories\SectionInfolistsFactory;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

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
