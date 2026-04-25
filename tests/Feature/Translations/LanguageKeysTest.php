<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

it('has translation key', function (string $key): void {
    expect(Lang::has('custom-fields::custom-fields.'.$key))->toBeTrue();
})->with([
    // enums.*
    'enums.avatar_shape.circle',
    'enums.avatar_shape.square',
    'enums.condition_source.custom_field',
    'enums.condition_source.model_attribute',
    'enums.custom_field_section_type.section',
    'enums.custom_field_section_type.fieldset',
    'enums.custom_field_section_type.headless',
    'enums.custom_field_width.25',
    'enums.custom_field_width.33',
    'enums.custom_field_width.50',
    'enums.custom_field_width.66',
    'enums.custom_field_width.75',
    'enums.custom_field_width.100',
    'enums.date_anchor.today',
    'enums.date_anchor.fixed_date',
    'enums.date_anchor.custom_field',
    'enums.date_anchor.record_created',
    'enums.date_offset_direction.before',
    'enums.date_offset_direction.after',
    'enums.date_unit.days',
    'enums.date_unit.weeks',
    'enums.date_unit.months',
    'enums.date_unit.quarters',
    'enums.date_unit.years',
    'enums.entity_feature.labels.custom_fields',
    'enums.entity_feature.labels.lookup_source',
    'enums.entity_feature.labels.scoped_management',
    'enums.entity_feature.descriptions.custom_fields',
    'enums.entity_feature.descriptions.lookup_source',
    'enums.entity_feature.descriptions.scoped_management',
    'enums.visibility_logic.all',
    'enums.visibility_logic.any',
    'enums.visibility_mode.always_visible',
    'enums.visibility_mode.show_when',
    'enums.visibility_mode.hide_when',
    'enums.visibility_operator.equals',
    'enums.visibility_operator.not_equals',
    'enums.visibility_operator.contains',
    'enums.visibility_operator.not_contains',
    'enums.visibility_operator.greater_than',
    'enums.visibility_operator.less_than',
    'enums.visibility_operator.is_empty',
    'enums.visibility_operator.is_not_empty',
    'enums.description_position.below',
    'enums.description_position.above',

    // visibility.*
    'visibility.heading',
    'visibility.mode',
    'visibility.logic',
    'visibility.conditions',
    'visibility.source',
    'visibility.field',
    'visibility.operator',
    'visibility.value',

    // date_constraint.*
    'date_constraint.constraint',
    'date_constraint.offset_value',
    'date_constraint.unit',
    'date_constraint.direction',
    'date_constraint.reference_field',
    'date_constraint.date',

    // field.actions.* (ManageCustomField row actions)
    'field.actions.activate',
    'field.actions.deactivate',
    'field.actions.duplicate',
    'field.actions.edit_modal_heading',
    'field.actions.delete_modal_heading',
    'field.actions.delete_modal_description',

    // section.actions.* (ManageCustomFieldSection row actions)
    'section.actions.activate',
    'section.actions.deactivate',
    'section.actions.edit_modal_heading',
    'section.actions.delete_modal_heading',
    'section.actions.delete_modal_description',

    // import.* and file_upload.*
    'import.multi_value_helper',
    'file_upload.placeholder',
]);

it('preserves backwards-compatible description_position keys', function (string $key): void {
    expect(Lang::has('custom-fields::custom-fields.'.$key))->toBeTrue();
})->with([
    'field.form.description_position_options.below',
    'field.form.description_position_options.above',
]);
