<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Relaticle\CustomFields\Enums\AvatarShape;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;

it('AvatarShape::Circle routes getLabel through translator', function (): void {
    Lang::addLines([
        'custom-fields.enums.avatar_shape.circle' => 'SENTINEL_CIRCLE',
    ], App::getLocale(), 'custom-fields');

    expect(AvatarShape::Circle->getLabel())->toBe('SENTINEL_CIRCLE');
});

it('AvatarShape::Square routes getLabel through translator', function (): void {
    Lang::addLines([
        'custom-fields.enums.avatar_shape.square' => 'SENTINEL_SQUARE',
    ], App::getLocale(), 'custom-fields');

    expect(AvatarShape::Square->getLabel())->toBe('SENTINEL_SQUARE');
});

it('ConditionSource::CustomField routes getLabel through translator', function (): void {
    Lang::addLines([
        'custom-fields.enums.condition_source.custom_field' => 'SENTINEL_CF',
    ], App::getLocale(), 'custom-fields');

    expect(ConditionSource::CustomField->getLabel())->toBe('SENTINEL_CF');
});

it('ConditionSource::ModelAttribute routes getLabel through translator', function (): void {
    Lang::addLines([
        'custom-fields.enums.condition_source.model_attribute' => 'SENTINEL_MA',
    ], App::getLocale(), 'custom-fields');

    expect(ConditionSource::ModelAttribute->getLabel())->toBe('SENTINEL_MA');
});

it('CustomFieldSectionType routes every case getLabel through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.custom_field_section_type.{$key}" => 'SENTINEL_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(CustomFieldSectionType::from($case)->getLabel())->toBe('SENTINEL_'.$key);
})->with([
    ['section', 'section'],
    ['fieldset', 'fieldset'],
    ['headless', 'headless'],
]);
