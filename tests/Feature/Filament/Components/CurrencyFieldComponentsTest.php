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
    it('creates a text column with money formatting', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $column = (new CurrencyColumn)->make($field);

        expect($column)->toBeInstanceOf(BaseTextColumn::class);
    });

    it('uses configured currency code from settings', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['currency_code' => 'EUR', 'decimal_places' => 2]],
        ]);

        $column = (new CurrencyColumn)->make($field);

        expect($column)->toBeInstanceOf(BaseTextColumn::class);
    });

    it('creates a column for fields with zero decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['decimal_places' => 0]],
        ]);

        $column = (new CurrencyColumn)->make($field);

        expect($column)->toBeInstanceOf(BaseTextColumn::class);
    });

    it('falls back to validation_rules for legacy decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'validation_rules' => ['decimal_places' => 4],
        ]);

        $column = (new CurrencyColumn)->make($field);

        expect($column)->toBeInstanceOf(BaseTextColumn::class);
    });
});

describe('CurrencyEntry', function (): void {
    it('creates a text entry with money formatting', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $entry = (new CurrencyEntry)->make($field);

        expect($entry)->toBeInstanceOf(BaseTextEntry::class);
    });

    it('creates an entry for fields with zero decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['decimal_places' => 0]],
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

    it('uses EUR symbol for EUR currency', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['currency_code' => 'EUR']],
        ]);

        $component = app(CurrencyComponent::class)->create($field);

        expect($component->getPrefixLabel())->toContain('€');
    });

    it('uses currency code as prefix for code display type', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['currency_code' => 'USD', 'display_type' => 'code']],
        ]);

        $component = app(CurrencyComponent::class)->create($field);

        expect($component->getPrefixLabel())->toBe('USD');
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

    it('uses step matching configured decimal places from settings', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['decimal_places' => 4]],
        ]);

        $component = app(CurrencyComponent::class)->create($field);

        expect((float) $component->getStep())->toBe(0.0001);
    });

    it('uses step of 1 for zero decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['decimal_places' => 0]],
        ]);

        $component = app(CurrencyComponent::class)->create($field);

        expect((int) $component->getStep())->toBe(1);
    });

    it('falls back to validation_rules for legacy decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'validation_rules' => ['decimal_places' => 4],
        ]);

        $component = app(CurrencyComponent::class)->create($field);

        expect((float) $component->getStep())->toBe(0.0001);
    });
});

describe('CurrencyFieldType Export Transformer', function (): void {
    it('exports values preserving meaningful precision', function (): void {
        $transformer = (new CurrencyFieldType)->configure()->getExportTransformer();

        expect($transformer(1234.5))->toBe('1234.5')
            ->and($transformer(0))->toBe('0')
            ->and($transformer(99.999))->toBe('99.999')
            ->and($transformer(42))->toBe('42')
            ->and($transformer(1234.50))->toBe('1234.5');
    });

    it('returns null for null values in export', function (): void {
        $transformer = (new CurrencyFieldType)->configure()->getExportTransformer();

        expect($transformer(null))->toBeNull();
    });
});

describe('CustomField currency helpers', function (): void {
    it('returns decimal places from settings', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['decimal_places' => 0]],
        ]);

        expect($field->getDecimalPlaces())->toBe(0);
    });

    it('falls back to validation_rules for decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'validation_rules' => ['decimal_places' => 3],
        ]);

        expect($field->getDecimalPlaces())->toBe(3);
    });

    it('returns default decimal places when nothing configured', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        expect($field->getDecimalPlaces())->toBe(2);
    });

    it('returns currency code from settings', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['currency_code' => 'EUR']],
        ]);

        expect($field->getCurrencyCode())->toBe('EUR');
    });

    it('returns default currency code when nothing configured', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        expect($field->getCurrencyCode())->toBe('USD');
    });

    it('returns display type from settings', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['display_type' => 'code']],
        ]);

        expect($field->getCurrencyDisplayType())->toBe('code');
    });

    it('returns default display type when nothing configured', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        expect($field->getCurrencyDisplayType())->toBe('symbol');
    });
});
