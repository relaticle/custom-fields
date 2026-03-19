<?php

declare(strict_types=1);

use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\CurrencyEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\CurrencyColumn;
use Relaticle\CustomFields\Models\CustomField;

describe('CurrencyColumn', function (): void {
    it('creates a text column with currency prefix', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $column = (new CurrencyColumn)->make($field);

        expect($column)
            ->toBeInstanceOf(BaseTextColumn::class)
            ->and($column->getPrefix())->toBe('$');
    });

    it('creates a column for fields with custom decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'validation_rules' => ['decimal_places' => 4],
        ]);

        $column = (new CurrencyColumn)->make($field);

        expect($column)->toBeInstanceOf(BaseTextColumn::class);
    });
});

describe('CurrencyEntry', function (): void {
    it('creates a text entry with currency prefix', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $entry = (new CurrencyEntry())->make($field);

        expect($entry)
            ->toBeInstanceOf(BaseTextEntry::class)
            ->and($entry->getPrefix())->toBe('$');
    });

    it('creates an entry for fields with custom decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'validation_rules' => ['decimal_places' => 0],
        ]);

        $entry = (new CurrencyEntry())->make($field);

        expect($entry)->toBeInstanceOf(BaseTextEntry::class);
    });
});
