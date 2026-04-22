<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

it('has enums.avatar_shape translation keys', function (): void {
    expect(Lang::has('custom-fields::custom-fields.enums.avatar_shape.circle'))->toBeTrue();
    expect(Lang::has('custom-fields::custom-fields.enums.avatar_shape.square'))->toBeTrue();
});

it('has enums.condition_source translation keys', function (): void {
    expect(Lang::has('custom-fields::custom-fields.enums.condition_source.custom_field'))->toBeTrue();
    expect(Lang::has('custom-fields::custom-fields.enums.condition_source.model_attribute'))->toBeTrue();
});

it('has enums.custom_field_section_type translation keys', function (): void {
    expect(Lang::has('custom-fields::custom-fields.enums.custom_field_section_type.section'))->toBeTrue();
    expect(Lang::has('custom-fields::custom-fields.enums.custom_field_section_type.fieldset'))->toBeTrue();
    expect(Lang::has('custom-fields::custom-fields.enums.custom_field_section_type.headless'))->toBeTrue();
});

it('has enums.custom_field_width translation keys', function (): void {
    foreach (['25', '33', '50', '66', '75', '100'] as $width) {
        expect(Lang::has('custom-fields::custom-fields.enums.custom_field_width.'.$width))->toBeTrue();
    }
});

it('has enums.date_anchor translation keys', function (): void {
    foreach (['today', 'fixed_date', 'custom_field', 'record_created'] as $case) {
        expect(Lang::has('custom-fields::custom-fields.enums.date_anchor.'.$case))->toBeTrue();
    }
});

it('has enums.date_offset_direction translation keys', function (): void {
    foreach (['before', 'after'] as $case) {
        expect(Lang::has('custom-fields::custom-fields.enums.date_offset_direction.'.$case))->toBeTrue();
    }
});

it('has enums.date_unit translation keys', function (): void {
    foreach (['days', 'weeks', 'months', 'quarters', 'years'] as $case) {
        expect(Lang::has('custom-fields::custom-fields.enums.date_unit.'.$case))->toBeTrue();
    }
});

it('has enums.entity_feature translation keys', function (): void {
    foreach (['custom_fields', 'lookup_source', 'scoped_management'] as $case) {
        expect(Lang::has('custom-fields::custom-fields.enums.entity_feature.labels.'.$case))->toBeTrue();
        expect(Lang::has('custom-fields::custom-fields.enums.entity_feature.descriptions.'.$case))->toBeTrue();
    }
});

it('has enums.visibility_logic translation keys', function (): void {
    foreach (['all', 'any'] as $case) {
        expect(Lang::has('custom-fields::custom-fields.enums.visibility_logic.'.$case))->toBeTrue();
    }
});

it('has enums.visibility_mode translation keys', function (): void {
    foreach (['always_visible', 'show_when', 'hide_when'] as $case) {
        expect(Lang::has('custom-fields::custom-fields.enums.visibility_mode.'.$case))->toBeTrue();
    }
});

it('has enums.visibility_operator translation keys', function (): void {
    foreach (['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'is_empty', 'is_not_empty'] as $case) {
        expect(Lang::has('custom-fields::custom-fields.enums.visibility_operator.'.$case))->toBeTrue();
    }
});

it('has enums.description_position translation keys', function (): void {
    foreach (['below', 'above'] as $case) {
        expect(Lang::has('custom-fields::custom-fields.enums.description_position.'.$case))->toBeTrue();
    }
});
