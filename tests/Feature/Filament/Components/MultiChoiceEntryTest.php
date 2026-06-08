<?php

declare(strict_types=1);

use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Filament\Infolists\Components\ViewEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\MultiChoiceEntry;
use Relaticle\CustomFields\Models\CustomField;

describe('MultiChoiceEntry', function (): void {
    it('renders a checkbox-list view entry for option-backed fields (checkbox-list)', function (): void {
        $field = CustomField::factory()->ofType('checkbox-list')->create();

        $entry = app(MultiChoiceEntry::class)->make($field);

        expect($entry)
            ->toBeInstanceOf(ViewEntry::class)
            ->getView()->toBe('custom-fields::infolists.checkbox-list-entry');
    });

    it('renders a badge text entry for arbitrary-value fields (tags-input)', function (): void {
        $field = CustomField::factory()->ofType('tags-input')->create();

        $entry = app(MultiChoiceEntry::class)->make($field);

        expect($entry)
            ->toBeInstanceOf(BaseTextEntry::class)
            ->isBadge()->toBeTrue();
    });
});
