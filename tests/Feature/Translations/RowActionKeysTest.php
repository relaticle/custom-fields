<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

it('field.actions keys needed by ManageCustomField exist', function (string $key): void {
    expect(Lang::has('custom-fields::custom-fields.field.actions.'.$key))->toBeTrue();
})->with([
    'activate',
    'deactivate',
    'duplicate',
    'edit_modal_heading',
    'delete_modal_heading',
    'delete_modal_description',
]);

it('section.actions keys needed by ManageCustomFieldSection exist', function (string $key): void {
    expect(Lang::has('custom-fields::custom-fields.section.actions.'.$key))->toBeTrue();
})->with([
    'activate',
    'deactivate',
    'edit_modal_heading',
    'delete_modal_heading',
    'delete_modal_description',
]);
