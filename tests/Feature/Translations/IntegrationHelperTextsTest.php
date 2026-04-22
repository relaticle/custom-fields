<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

it('import and file_upload translation keys exist', function (): void {
    expect(Lang::has('custom-fields::custom-fields.import.multi_value_helper'))->toBeTrue();
    expect(Lang::has('custom-fields::custom-fields.file_upload.placeholder'))->toBeTrue();
});

it('ImportColumnConfigurator and FileUploadComponent use translated strings', function (): void {
    $importer = file_get_contents(dirname(__DIR__, 3).'/src/Filament/Integration/Support/Imports/ImportColumnConfigurator.php');
    $fileUpload = file_get_contents(dirname(__DIR__, 3).'/src/Filament/Integration/Components/Forms/FileUploadComponent.php');

    expect($importer)->not->toContain("->helperText('Separate multiple values with commas')");
    expect($importer)->toContain('custom-fields::custom-fields.import.multi_value_helper');

    expect($fileUpload)->not->toContain("->placeholder('Choose a file or drag and drop')");
    expect($fileUpload)->toContain('custom-fields::custom-fields.file_upload.placeholder');
});
