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
