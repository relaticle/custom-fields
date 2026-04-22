<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Relaticle\CustomFields\Enums\AvatarShape;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;
use Relaticle\CustomFields\Enums\DescriptionPosition;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;

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

it('DateAnchor routes every case getLabel through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.date_anchor.{$key}" => 'SENTINEL_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(DateAnchor::from($case)->getLabel())->toBe('SENTINEL_'.$key);
})->with([
    ['today', 'today'],
    ['fixed_date', 'fixed_date'],
    ['custom_field', 'custom_field'],
    ['record_created', 'record_created'],
]);

it('DateUnit routes every case getLabel through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.date_unit.{$key}" => 'SENTINEL_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(DateUnit::from($case)->getLabel())->toBe('SENTINEL_'.$key);
})->with([
    ['days', 'days'],
    ['weeks', 'weeks'],
    ['months', 'months'],
    ['quarters', 'quarters'],
    ['years', 'years'],
]);

it('DateOffsetDirection routes every case getLabel through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.date_offset_direction.{$key}" => 'SENTINEL_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(DateOffsetDirection::from($case)->getLabel())->toBe('SENTINEL_'.$key);
})->with([
    ['before', 'before'],
    ['after', 'after'],
]);

it('VisibilityLogic routes every case getLabel through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.visibility_logic.{$key}" => 'SENTINEL_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(VisibilityLogic::from($case)->getLabel())->toBe('SENTINEL_'.$key);
})->with([
    ['all', 'all'],
    ['any', 'any'],
]);

it('VisibilityMode routes every case getLabel through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.visibility_mode.{$key}" => 'SENTINEL_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(VisibilityMode::from($case)->getLabel())->toBe('SENTINEL_'.$key);
})->with([
    ['always_visible', 'always_visible'],
    ['show_when', 'show_when'],
    ['hide_when', 'hide_when'],
]);

it('VisibilityOperator routes every case getLabel through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.visibility_operator.{$key}" => 'SENTINEL_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(VisibilityOperator::from($case)->getLabel())->toBe('SENTINEL_'.$key);
})->with([
    ['equals', 'equals'],
    ['not_equals', 'not_equals'],
    ['contains', 'contains'],
    ['not_contains', 'not_contains'],
    ['greater_than', 'greater_than'],
    ['less_than', 'less_than'],
    ['is_empty', 'is_empty'],
    ['is_not_empty', 'is_not_empty'],
]);

it('EntityFeature routes every case getLabel through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.entity_feature.labels.{$key}" => 'SENTINEL_LABEL_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(EntityFeature::from($case)->getLabel())->toBe('SENTINEL_LABEL_'.$key);
})->with([
    ['custom_fields', 'custom_fields'],
    ['lookup_source', 'lookup_source'],
    ['scoped_management', 'scoped_management'],
]);

it('EntityFeature routes every case getDescription through translator', function (string $case, string $key): void {
    Lang::addLines([
        "custom-fields.enums.entity_feature.descriptions.{$key}" => 'SENTINEL_DESC_'.$key,
    ], App::getLocale(), 'custom-fields');

    expect(EntityFeature::from($case)->getDescription())->toBe('SENTINEL_DESC_'.$key);
})->with([
    ['custom_fields', 'custom_fields'],
    ['lookup_source', 'lookup_source'],
    ['scoped_management', 'scoped_management'],
]);

it('DescriptionPosition::BELOW routes to new enums key', function (): void {
    Lang::addLines([
        'custom-fields.enums.description_position.below' => 'SENTINEL_BELOW',
    ], App::getLocale(), 'custom-fields');

    expect(DescriptionPosition::BELOW->getLabel())->toBe('SENTINEL_BELOW');
});

it('DescriptionPosition::ABOVE routes to new enums key', function (): void {
    Lang::addLines([
        'custom-fields.enums.description_position.above' => 'SENTINEL_ABOVE',
    ], App::getLocale(), 'custom-fields');

    expect(DescriptionPosition::ABOVE->getLabel())->toBe('SENTINEL_ABOVE');
});

it('BC: old field.form.description_position_options keys still exist', function (): void {
    expect(Lang::has('custom-fields::custom-fields.field.form.description_position_options.below'))->toBeTrue();
    expect(Lang::has('custom-fields::custom-fields.field.form.description_position_options.above'))->toBeTrue();
});
