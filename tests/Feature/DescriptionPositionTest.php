<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\DescriptionPosition;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;

it('has the correct backing values', function (): void {
    expect(DescriptionPosition::BELOW->value)->toBe('below');
    expect(DescriptionPosition::ABOVE->value)->toBe('above');
});

it('returns translatable labels', function (): void {
    expect(DescriptionPosition::BELOW->getLabel())->toBeString()->not->toBeEmpty();
    expect(DescriptionPosition::ABOVE->getLabel())->toBeString()->not->toBeEmpty();
});

it('has FIELD_DESCRIPTION_POSITION feature flag disabled by default', function (): void {
    expect(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION))->toBeFalse();
});

it('can enable FIELD_DESCRIPTION_POSITION feature flag', function (): void {
    $config = FeatureConfigurator::configure()
        ->enable(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION);

    config(['custom-fields.features' => $config]);

    expect(FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION))->toBeTrue();
});

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

it('renders description below field by default', function (): void {
    $this->actingAs(User::factory()->create());

    $config = FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_DESCRIPTION,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        );
    config(['custom-fields.features' => $config]);

    $section = CustomFieldSection::factory()->create([
        'entity_type' => Post::class,
    ]);

    CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Test Field',
        'code' => 'test_field',
        'type' => 'text',
        'entity_type' => Post::class,
        'settings' => new CustomFieldSettingsData(
            description: 'Help text below',
        ),
    ]);

    livewire(CreatePost::class)
        ->assertSuccessful()
        ->assertSeeHtml('Help text below');
});

it('renders description above field when position is ABOVE and feature enabled', function (): void {
    $this->actingAs(User::factory()->create());

    $config = FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_DESCRIPTION,
            CustomFieldsFeature::FIELD_DESCRIPTION_POSITION,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        );
    config(['custom-fields.features' => $config]);

    $section = CustomFieldSection::factory()->create([
        'entity_type' => Post::class,
    ]);

    CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Test Field Above',
        'code' => 'test_field_above',
        'type' => 'text',
        'entity_type' => Post::class,
        'settings' => new CustomFieldSettingsData(
            description: 'Help text above',
            descriptionPosition: DescriptionPosition::ABOVE,
        ),
    ]);

    livewire(CreatePost::class)
        ->assertSuccessful()
        ->assertSeeHtml('Help text above');
});

it('ignores description position when FIELD_DESCRIPTION_POSITION feature is disabled', function (): void {
    $this->actingAs(User::factory()->create());

    $config = FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_DESCRIPTION,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )
        ->disable(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION);
    config(['custom-fields.features' => $config]);

    $section = CustomFieldSection::factory()->create([
        'entity_type' => Post::class,
    ]);

    CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Test Field Fallback',
        'code' => 'test_field_fallback',
        'type' => 'text',
        'entity_type' => Post::class,
        'settings' => new CustomFieldSettingsData(
            description: 'Should render below despite ABOVE setting',
            descriptionPosition: DescriptionPosition::ABOVE,
        ),
    ]);

    livewire(CreatePost::class)
        ->assertSuccessful()
        ->assertSeeHtml('Should render below despite ABOVE setting');
});
