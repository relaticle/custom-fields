<?php

declare(strict_types=1);

use Filament\Support\Enums\Width;
use Relaticle\CustomFields\CustomFieldsPlugin;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;

it('widens the section modal to screen-lg when conditional visibility is enabled', function (): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
    );

    expect(CustomFieldsPlugin::make()->getSectionModalWidth())->toBe(Width::ScreenLarge);
});

it('keeps the narrower 2xl section modal when conditional visibility is disabled', function (): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SYSTEM_SECTIONS)
    );

    expect(CustomFieldsPlugin::make()->getSectionModalWidth())->toBe(Width::TwoExtraLarge);
});

it('honors an explicit plugin override regardless of feature state', function (): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
    );

    expect(CustomFieldsPlugin::make()->sectionModalWidth(Width::FourExtraLarge)->getSectionModalWidth())
        ->toBe(Width::FourExtraLarge);
});

it('resolves a string width configured via config', function (): void {
    config()->set('custom-fields.management.section_modal_width', Width::ThreeExtraLarge->value);

    expect(CustomFieldsPlugin::make()->getSectionModalWidth())->toBe(Width::ThreeExtraLarge);
});

it('prefers the plugin override over the config value', function (): void {
    config()->set('custom-fields.management.section_modal_width', Width::Medium->value);

    expect(CustomFieldsPlugin::make()->sectionModalWidth(fn (): Width => Width::FiveExtraLarge)->getSectionModalWidth())
        ->toBe(Width::FiveExtraLarge);
});
