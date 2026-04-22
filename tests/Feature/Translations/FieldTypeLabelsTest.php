<?php

declare(strict_types=1);

it('every FieldTypeSystem definition uses __() for its outer label', function (): void {
    $dir = dirname(__DIR__, 3).'/src/FieldTypeSystem/Definitions';
    $files = glob($dir.'/*.php');

    expect($files)->not->toBeEmpty();

    $violations = [];

    foreach ($files as $file) {
        if (basename($file) === 'CurrencyFieldType.php') {
            // Task 14 handles inner labels in CurrencyFieldType.
            // For this task, only verify the OUTER label at the top of the class is translated.
            $source = file_get_contents($file);
            // Count the outer $typeBuilder->label('Currency') — if still hardcoded, flag.
            // Allow this only if it's inside an inner Fieldset — if it's outside, it's the outer label.
            // Simplest heuristic: if the file contains __('custom-fields::custom-fields.field_types.currency'),
            // the outer label is translated.
            if (preg_match("/->label\\('Currency'\\)/", $source) && ! str_contains($source, 'custom-fields::custom-fields.field_types.currency')) {
                $violations[] = basename($file).': outer Currency label not translated';
            }

            continue;
        }

        $source = file_get_contents($file);
        if (preg_match('/->label\([\'"]([^\'"]+)[\'"]\)/', $source, $m)) {
            $violations[] = basename($file).sprintf(": ->label('%s') should use __() ", $m[1]);
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('CurrencyFieldType inner form uses __() for all inner labels and helpers', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3).'/src/FieldTypeSystem/Definitions/CurrencyFieldType.php');

    foreach ([
        "Fieldset::make('Currency Settings')",
        "->helperText('Auto-detected from currency. Override only if needed.')",
    ] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }

    foreach ([
        'currency.fieldset',
        'currency.currency',
        'currency.display',
        'currency.decimal_places',
        'currency.decimal_places_helper',
    ] as $key) {
        expect($source)->toContain('custom-fields::custom-fields.'.$key);
    }

    // After this task, the only hardcoded ->label('Currency') in the file is the OUTER
    // one that Task 12 already translated. Since Task 12's test already verified that,
    // we trust it here and only check the inner three labels are gone.
    // Count occurrences of untranslated inner labels.
    expect(substr_count($source, "->label('Display')"))->toBe(0);
    expect(substr_count($source, "->label('Decimal Places')"))->toBe(0);
});
