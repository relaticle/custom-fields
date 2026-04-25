<?php

declare(strict_types=1);

$srcRoot = dirname(__DIR__, 3).'/src';

it('source file has no hardcoded English labels and references expected translation keys', function (string $relativePath, array $forbidden, array $required) use ($srcRoot): void {
    $source = file_get_contents($srcRoot.'/'.$relativePath);

    foreach ($forbidden as $substring) {
        expect($source)->not->toContain($substring);
    }

    foreach ($required as $key) {
        expect($source)->toContain('custom-fields::custom-fields.'.$key);
    }
})->with([
    'VisibilityComponent' => [
        'relativePath' => 'Filament/Management/Forms/Components/VisibilityComponent.php',
        'forbidden' => [
            "->label('Condition VisibilityLogic')",
            "->label('VisibilityOperator')",
            "->label('Visibility')",
            "->label('Conditions')",
            "->label('Source')",
            "->label('Field')",
            "->label('Value')",
            "Fieldset::make('Conditional Visibility')",
        ],
        'required' => [
            'visibility.heading',
            'visibility.mode',
            'visibility.logic',
            'visibility.conditions',
            'visibility.source',
            'visibility.field',
            'visibility.operator',
            'visibility.value',
        ],
    ],
    'DateConstraintField' => [
        'relativePath' => 'Filament/Management/Forms/Components/DateConstraintField.php',
        'forbidden' => [
            "->label('Constraint')",
            "->label('Offset value')",
            "->label('Unit')",
            "->label('Direction')",
            "->label('Reference field')",
            "->label('Date')",
        ],
        'required' => [
            'date_constraint.constraint',
            'date_constraint.offset_value',
            'date_constraint.unit',
            'date_constraint.direction',
            'date_constraint.reference_field',
            'date_constraint.date',
        ],
    ],
    'ImportColumnConfigurator' => [
        'relativePath' => 'Filament/Integration/Support/Imports/ImportColumnConfigurator.php',
        'forbidden' => [
            "->helperText('Separate multiple values with commas')",
        ],
        'required' => [
            'import.multi_value_helper',
        ],
    ],
    'FileUploadComponent' => [
        'relativePath' => 'Filament/Integration/Components/Forms/FileUploadComponent.php',
        'forbidden' => [
            "->placeholder('Choose a file or drag and drop')",
        ],
        'required' => [
            'file_upload.placeholder',
        ],
    ],
    'ManageCustomField' => [
        'relativePath' => 'Livewire/ManageCustomField.php',
        'forbidden' => [],
        'required' => [
            'field.actions.edit_modal_heading',
            'field.actions.duplicate',
            'field.actions.activate',
            'field.actions.deactivate',
            'field.actions.delete_modal_heading',
            'field.actions.delete_modal_description',
        ],
    ],
    'ManageCustomFieldSection' => [
        'relativePath' => 'Livewire/ManageCustomFieldSection.php',
        'forbidden' => [],
        'required' => [
            'section.actions.edit_modal_heading',
            'section.actions.activate',
            'section.actions.deactivate',
            'section.actions.delete_modal_heading',
            'section.actions.delete_modal_description',
        ],
    ],
    'ManageFieldsTable' => [
        'relativePath' => 'Livewire/ManageFieldsTable.php',
        'forbidden' => [],
        'required' => [
            'field.actions.edit_modal_heading',
            'field.actions.activate',
            'field.actions.deactivate',
            'field.actions.delete_modal_heading',
            'field.actions.delete_modal_description',
        ],
    ],
    'CurrencyFieldType inner form' => [
        'relativePath' => 'FieldTypeSystem/Definitions/CurrencyFieldType.php',
        'forbidden' => [
            "Fieldset::make('Currency Settings')",
            "->helperText('Auto-detected from currency. Override only if needed.')",
            "->label('Display')",
            "->label('Decimal Places')",
        ],
        'required' => [
            'currency.fieldset',
            'currency.currency',
            'currency.display',
            'currency.decimal_places',
            'currency.decimal_places_helper',
        ],
    ],
]);

it('every FieldTypeSystem definition uses __() for its outer label', function () use ($srcRoot): void {
    $files = glob($srcRoot.'/FieldTypeSystem/Definitions/*.php');

    expect($files)->not->toBeEmpty();

    $violations = [];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        if (basename($file) === 'CurrencyFieldType.php') {
            // Inner labels live inside a Fieldset and are covered by the dataset above.
            // Here we only verify the OUTER class-level label is translated.
            if (preg_match("/->label\\('Currency'\\)/", $source) && ! str_contains($source, 'custom-fields::custom-fields.field_types.currency')) {
                $violations[] = basename($file).': outer Currency label not translated';
            }

            continue;
        }

        if (preg_match('/->label\([\'"]([^\'"]+)[\'"]\)/', $source, $m)) {
            $violations[] = basename($file).sprintf(": ->label('%s') should use __()", $m[1]);
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('every Validation capability uses __() for label and placeholder', function () use ($srcRoot): void {
    $files = glob($srcRoot.'/Validation/Capabilities/*.php');

    expect($files)->not->toBeEmpty();

    $violations = [];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        if (preg_match_all('/->(label|placeholder)\([\'"]([^\'"]+)[\'"]\)/', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $violations[] = basename($file).sprintf(": ->%s('%s') should use __()", $match[1], $match[2]);
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});
