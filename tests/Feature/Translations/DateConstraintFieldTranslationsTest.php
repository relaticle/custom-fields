<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

it('date_constraint.* translation keys exist', function (string $key): void {
    expect(Lang::has('custom-fields::custom-fields.date_constraint.'.$key))->toBeTrue();
})->with([
    'constraint', 'offset_value', 'unit', 'direction', 'reference_field', 'date',
]);

it('DateConstraintField source uses translation keys', function (): void {
    $source = file_get_contents(__DIR__.'/../../../src/Filament/Management/Forms/Components/DateConstraintField.php');

    foreach ([
        "->label('Constraint')",
        "->label('Offset value')",
        "->label('Unit')",
        "->label('Direction')",
        "->label('Reference field')",
        "->label('Date')",
    ] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }

    foreach ([
        'date_constraint.constraint',
        'date_constraint.offset_value',
        'date_constraint.unit',
        'date_constraint.direction',
        'date_constraint.reference_field',
        'date_constraint.date',
    ] as $key) {
        expect($source)->toContain('custom-fields::custom-fields.'.$key);
    }
});
