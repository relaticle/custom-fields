<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;

it('configures and checks features correctly', function (): void {
    $config = FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
            CustomFieldsFeature::UI_TABLE_COLUMNS,
            CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE
        )
        ->disable(
            CustomFieldsFeature::FIELD_ENCRYPTION,
            CustomFieldsFeature::SYSTEM_MULTI_TENANCY
        );

    config(['custom-fields.features' => $config]);

    expect(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY))->toBeTrue();
    expect(FeatureManager::isEnabled(CustomFieldsFeature::UI_TABLE_COLUMNS))->toBeTrue();
    expect(FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE))->toBeTrue();
    expect(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_ENCRYPTION))->toBeFalse();
    expect(FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY))->toBeFalse();
});

it('handles feature enabling and disabling', function (): void {
    $config = FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::FIELD_ENCRYPTION)
        ->disable(CustomFieldsFeature::FIELD_ENCRYPTION); // Should override

    config(['custom-fields.features' => $config]);

    expect(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_ENCRYPTION))->toBeFalse();
});

it('can toggle UI_FIELD_WIDTH_CONTROL feature', function (): void {
    $config = FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::UI_FIELD_WIDTH_CONTROL);

    config(['custom-fields.features' => $config]);

    expect(FeatureManager::isEnabled(CustomFieldsFeature::UI_FIELD_WIDTH_CONTROL))->toBeTrue();

    $config = FeatureConfigurator::configure()
        ->disable(CustomFieldsFeature::UI_FIELD_WIDTH_CONTROL);

    config(['custom-fields.features' => $config]);

    expect(FeatureManager::isEnabled(CustomFieldsFeature::UI_FIELD_WIDTH_CONTROL))->toBeFalse();
});

it('falls back to the package default for a flag the host did not list', function (): void {
    config(['custom-fields.features' => FeatureConfigurator::configure()]);

    expect(FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_RELATIONSHIPS))->toBeTrue()
        ->and(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS))->toBeTrue()
        ->and(FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY))->toBeFalse()
        ->and(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_MULTI_VALUE))->toBeFalse();

    config(['custom-fields.features' => FeatureConfigurator::configure()
        ->disable(CustomFieldsFeature::SYSTEM_RELATIONSHIPS)
        ->enable(CustomFieldsFeature::FIELD_MULTI_VALUE),
    ]);

    expect(FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_RELATIONSHIPS))->toBeFalse()
        ->and(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_MULTI_VALUE))->toBeTrue()
        ->and(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS))->toBeTrue();
});

it('keeps the package defaults and the shipped config in step', function (): void {
    $shipped = shippedFeatureConfigurator();

    foreach (CustomFieldsFeature::cases() as $feature) {
        expect($feature->isEnabledByDefault())
            ->toBe($shipped->isEnabled($feature), $feature->value.' disagrees with the shipped config.');
    }
});

it('lists every feature flag explicitly in the shipped config', function (): void {
    $shipped = shippedFeatureConfigurator();

    $configured = (new ReflectionProperty(FeatureConfigurator::class, 'features'))->getValue($shipped);

    $cases = array_map(
        fn (CustomFieldsFeature $feature): string => $feature->value,
        CustomFieldsFeature::cases(),
    );

    expect(array_keys($configured))->toEqualCanonicalizing($cases);
});

it('ships the feature defaults reviewed for 4.0', function (): void {
    config(['custom-fields.features' => shippedFeatureConfigurator()]);

    $actual = [];

    foreach (CustomFieldsFeature::cases() as $feature) {
        $actual[$feature->value] = FeatureManager::isEnabled($feature);
    }

    expect($actual)->toEqual([
        CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY->value => true,
        CustomFieldsFeature::FIELD_ENCRYPTION->value => true,
        CustomFieldsFeature::FIELD_OPTION_COLORS->value => true,
        CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE->value => false,
        CustomFieldsFeature::FIELD_MULTI_VALUE->value => false,
        CustomFieldsFeature::FIELD_UNIQUE_VALUE->value => false,
        CustomFieldsFeature::FIELD_VALIDATION_RULES->value => true,
        CustomFieldsFeature::FIELD_DESCRIPTION->value => true,
        CustomFieldsFeature::FIELD_DESCRIPTION_POSITION->value => true,
        CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS->value => false,
        CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY->value => true,
        CustomFieldsFeature::UI_TABLE_COLUMNS->value => true,
        CustomFieldsFeature::UI_TABLE_FILTERS->value => true,
        CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS->value => true,
        CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS_HIDDEN_DEFAULT->value => false,
        CustomFieldsFeature::UI_FIELD_WIDTH_CONTROL->value => true,
        CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL->value => true,
        CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE->value => true,
        CustomFieldsFeature::SYSTEM_MULTI_TENANCY->value => false,
        CustomFieldsFeature::SYSTEM_SECTIONS->value => true,
        CustomFieldsFeature::SYSTEM_RELATIONSHIPS->value => true,
    ]);
});
