<?php

declare(strict_types=1);

it('ManageCustomField row actions use translated labels and modal headings', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3).'/src/Livewire/ManageCustomField.php');

    // Every Action::make in this file must be followed by ->label(__(...)).
    preg_match_all('/Action::make\([^)]+\)\s*((?:\s*->[^(]+\([^)]*\))*)/', $source, $matches);
    foreach ($matches[0] as $actionChain) {
        expect($actionChain)->toMatch('/->label\(\s*__\(/');
    }

    // Edit and delete actions must set explicit modal heading/description.
    expect($source)
        ->toContain('custom-fields::custom-fields.field.actions.edit_modal_heading')
        ->toContain('custom-fields::custom-fields.field.actions.delete_modal_heading')
        ->toContain('custom-fields::custom-fields.field.actions.delete_modal_description')
        ->toContain('custom-fields::custom-fields.field.actions.duplicate');
});
