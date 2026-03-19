<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\CurrencyFieldType;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CurrencyComponent;
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

        $entry = (new CurrencyEntry)->make($field);

        expect($entry)
            ->toBeInstanceOf(BaseTextEntry::class)
            ->and($entry->getPrefix())->toBe('$');
    });

    it('creates an entry for fields with custom decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'validation_rules' => ['decimal_places' => 0],
        ]);

        $entry = (new CurrencyEntry)->make($field);

        expect($entry)->toBeInstanceOf(BaseTextEntry::class);
    });
});

describe('CurrencyComponent', function (): void {
    it('creates a text input with currency prefix', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $component = app(CurrencyComponent::class)->create($field);

        expect($component)
            ->toBeInstanceOf(TextInput::class)
            ->and($component->getPrefixLabel())->toBe('$');
    });

    it('has no hardcoded default value', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $component = app(CurrencyComponent::class)->create($field);

        expect($component->getDefaultState())->toBeNull();
    });

    it('uses step matching default 2 decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $component = app(CurrencyComponent::class)->create($field);

        expect((float) $component->getStep())->toBe(0.01);
    });

    it('uses step matching configured decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'validation_rules' => ['decimal_places' => 4],
        ]);

        $component = app(CurrencyComponent::class)->create($field);

        expect((float) $component->getStep())->toBe(0.0001);
    });

    it('uses step of 1 for zero decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'validation_rules' => ['decimal_places' => 0],
        ]);

        $component = app(CurrencyComponent::class)->create($field);

        expect((int) $component->getStep())->toBe(1);
    });
});

describe('CurrencyFieldType Export Transformer', function (): void {
    it('formats values with 2 decimal places for CSV export', function (): void {
        $transformer = (new CurrencyFieldType)->configure()->getExportTransformer();

        expect($transformer(1234.5))->toBe('1234.50')
            ->and($transformer(0))->toBe('0.00')
            ->and($transformer(99.999))->toBe('100.00')
            ->and($transformer(42))->toBe('42.00');
    });

    it('returns null for null values in export', function (): void {
        $transformer = (new CurrencyFieldType)->configure()->getExportTransformer();

        expect($transformer(null))->toBeNull();
    });
});
