<?php

declare(strict_types=1);

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Relaticle\CustomFields\Enums\AvatarShape;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;
use Relaticle\CustomFields\Enums\DescriptionPosition;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;

it('routes enum getLabel through translator', function (string $enumClass, string $caseValue, string $key): void {
    $sentinel = 'SENTINEL_'.$key;
    Lang::addLines(['custom-fields.'.$key => $sentinel], App::getLocale(), 'custom-fields');

    expect($enumClass::from($caseValue)->getLabel())->toBe($sentinel);
})->with([
    [AvatarShape::class, 'circle', 'enums.avatar_shape.circle'],
    [AvatarShape::class, 'square', 'enums.avatar_shape.square'],

    [ConditionSource::class, 'custom_field', 'enums.condition_source.custom_field'],
    [ConditionSource::class, 'model_attribute', 'enums.condition_source.model_attribute'],

    [CustomFieldSectionType::class, 'section', 'enums.custom_field_section_type.section'],
    [CustomFieldSectionType::class, 'fieldset', 'enums.custom_field_section_type.fieldset'],
    [CustomFieldSectionType::class, 'headless', 'enums.custom_field_section_type.headless'],

    [CustomFieldWidth::class, '25', 'enums.custom_field_width.25'],
    [CustomFieldWidth::class, '33', 'enums.custom_field_width.33'],
    [CustomFieldWidth::class, '50', 'enums.custom_field_width.50'],
    [CustomFieldWidth::class, '66', 'enums.custom_field_width.66'],
    [CustomFieldWidth::class, '75', 'enums.custom_field_width.75'],
    [CustomFieldWidth::class, '100', 'enums.custom_field_width.100'],

    [DateAnchor::class, 'today', 'enums.date_anchor.today'],
    [DateAnchor::class, 'fixed_date', 'enums.date_anchor.fixed_date'],
    [DateAnchor::class, 'custom_field', 'enums.date_anchor.custom_field'],
    [DateAnchor::class, 'record_created', 'enums.date_anchor.record_created'],

    [DateOffsetDirection::class, 'before', 'enums.date_offset_direction.before'],
    [DateOffsetDirection::class, 'after', 'enums.date_offset_direction.after'],

    [DateUnit::class, 'days', 'enums.date_unit.days'],
    [DateUnit::class, 'weeks', 'enums.date_unit.weeks'],
    [DateUnit::class, 'months', 'enums.date_unit.months'],
    [DateUnit::class, 'quarters', 'enums.date_unit.quarters'],
    [DateUnit::class, 'years', 'enums.date_unit.years'],

    [EntityFeature::class, 'custom_fields', 'enums.entity_feature.labels.custom_fields'],
    [EntityFeature::class, 'lookup_source', 'enums.entity_feature.labels.lookup_source'],
    [EntityFeature::class, 'scoped_management', 'enums.entity_feature.labels.scoped_management'],

    [VisibilityLogic::class, 'all', 'enums.visibility_logic.all'],
    [VisibilityLogic::class, 'any', 'enums.visibility_logic.any'],

    [VisibilityMode::class, 'always_visible', 'enums.visibility_mode.always_visible'],
    [VisibilityMode::class, 'show_when', 'enums.visibility_mode.show_when'],
    [VisibilityMode::class, 'hide_when', 'enums.visibility_mode.hide_when'],

    [VisibilityOperator::class, 'equals', 'enums.visibility_operator.equals'],
    [VisibilityOperator::class, 'not_equals', 'enums.visibility_operator.not_equals'],
    [VisibilityOperator::class, 'contains', 'enums.visibility_operator.contains'],
    [VisibilityOperator::class, 'not_contains', 'enums.visibility_operator.not_contains'],
    [VisibilityOperator::class, 'greater_than', 'enums.visibility_operator.greater_than'],
    [VisibilityOperator::class, 'less_than', 'enums.visibility_operator.less_than'],
    [VisibilityOperator::class, 'is_empty', 'enums.visibility_operator.is_empty'],
    [VisibilityOperator::class, 'is_not_empty', 'enums.visibility_operator.is_not_empty'],

    [DescriptionPosition::class, 'below', 'enums.description_position.below'],
    [DescriptionPosition::class, 'above', 'enums.description_position.above'],
]);

it('routes EntityFeature getDescription through translator', function (string $caseValue, string $key): void {
    $sentinel = 'SENTINEL_DESC_'.$caseValue;
    Lang::addLines(['custom-fields.'.$key => $sentinel], App::getLocale(), 'custom-fields');

    expect(EntityFeature::from($caseValue)->getDescription())->toBe($sentinel);
})->with([
    ['custom_fields', 'enums.entity_feature.descriptions.custom_fields'],
    ['lookup_source', 'enums.entity_feature.descriptions.lookup_source'],
    ['scoped_management', 'enums.entity_feature.descriptions.scoped_management'],
]);

it('CustomFieldWidth implements HasLabel', function (): void {
    expect(is_subclass_of(CustomFieldWidth::class, HasLabel::class))->toBeTrue();
});
