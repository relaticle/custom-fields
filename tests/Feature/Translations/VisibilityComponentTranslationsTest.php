<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

it('visibility.* translation keys exist', function (string $key): void {
    expect(Lang::has("custom-fields::custom-fields.visibility.{$key}"))->toBeTrue();
})->with([
    'heading', 'mode', 'logic', 'conditions', 'source', 'field', 'operator', 'value',
]);

it('VisibilityComponent source has no hardcoded English labels or class-name bugs', function (): void {
    $source = file_get_contents(__DIR__.'/../../../src/Filament/Management/Forms/Components/VisibilityComponent.php');

    // Class-name bugs must be gone.
    expect($source)->not->toContain("->label('Condition VisibilityLogic')");
    expect($source)->not->toContain("->label('VisibilityOperator')");

    // Hardcoded English labels must be gone.
    foreach ([
        "->label('Visibility')",
        "->label('Conditions')",
        "->label('Source')",
        "->label('Field')",
        "->label('Value')",
        "Fieldset::make('Conditional Visibility')",
    ] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }

    // All translation keys must be referenced.
    foreach ([
        'visibility.heading',
        'visibility.mode',
        'visibility.logic',
        'visibility.conditions',
        'visibility.source',
        'visibility.field',
        'visibility.operator',
        'visibility.value',
    ] as $key) {
        expect($source)->toContain("custom-fields::custom-fields.{$key}");
    }
});
