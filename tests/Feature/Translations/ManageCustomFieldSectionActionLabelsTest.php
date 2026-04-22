<?php

declare(strict_types=1);

it('ManageCustomFieldSection row actions use translated labels and modal headings', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3).'/src/Livewire/ManageCustomFieldSection.php');

    preg_match_all('/Action::make\([^)]+\)\s*((?:\s*->[^(]+\([^)]*\))*)/', $source, $matches);
    foreach ($matches[0] as $actionChain) {
        expect($actionChain)->toMatch('/->label\(\s*__\(/');
    }

    expect($source)
        ->toContain('custom-fields::custom-fields.section.actions.edit_modal_heading')
        ->toContain('custom-fields::custom-fields.section.actions.delete_modal_heading')
        ->toContain('custom-fields::custom-fields.section.actions.delete_modal_description')
        ->toContain('custom-fields::custom-fields.section.actions.activate')
        ->toContain('custom-fields::custom-fields.section.actions.deactivate');
});
