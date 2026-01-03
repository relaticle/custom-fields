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
