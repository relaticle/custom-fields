<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\DescriptionPosition;

it('has the correct backing values', function (): void {
    expect(DescriptionPosition::BELOW->value)->toBe('below');
    expect(DescriptionPosition::ABOVE->value)->toBe('above');
});

it('returns translatable labels', function (): void {
    expect(DescriptionPosition::BELOW->getLabel())->toBeString()->not->toBeEmpty();
    expect(DescriptionPosition::ABOVE->getLabel())->toBeString()->not->toBeEmpty();
});

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;

it('has FIELD_DESCRIPTION_POSITION feature flag disabled by default', function (): void {
    expect(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION))->toBeFalse();
});

it('can enable FIELD_DESCRIPTION_POSITION feature flag', function (): void {
    $config = FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION);

    config(['custom-fields.features' => $config]);

    expect(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION))->toBeTrue();
});

use Relaticle\CustomFields\Data\CustomFieldSettingsData;

it('includes descriptionPosition as null by default in settings', function (): void {
    $settings = new CustomFieldSettingsData;

    expect($settings->descriptionPosition)->toBeNull();
});

it('stores descriptionPosition enum in settings', function (): void {
    $settings = new CustomFieldSettingsData(
        descriptionPosition: DescriptionPosition::ABOVE,
    );

    expect($settings->descriptionPosition)->toBe(DescriptionPosition::ABOVE);
});

it('serializes and deserializes descriptionPosition through settings', function (): void {
    $settings = new CustomFieldSettingsData(
        descriptionPosition: DescriptionPosition::ABOVE,
    );

    $array = $settings->toArray();
    $restored = CustomFieldSettingsData::from($array);

    expect($restored->descriptionPosition)->toBe(DescriptionPosition::ABOVE);
});
