<?php

declare(strict_types=1);

it('every Validation capability uses __() for its label and placeholder', function (): void {
    $dir = dirname(__DIR__, 3).'/src/Validation/Capabilities';
    $files = glob($dir.'/*.php');

    expect($files)->not->toBeEmpty();

    $violations = [];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        if (preg_match_all('/->(label|placeholder)\([\'"]([^\'"]+)[\'"]\)/', $source, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $violations[] = basename($file).sprintf(": ->%s('%s') should use __()", $match[1], $match[2]);
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});
