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
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;

describe('CurrencyColumn', function (): void {
    it('creates a money-formatted column with symbol display type by default', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $column = (new CurrencyColumn)->make($field);

        expect($column)
            ->toBeInstanceOf(BaseTextColumn::class)
            ->isMoney()->toBeTrue();
    });

    it('uses numeric formatting with code prefix for code display type', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['currency_code' => 'EUR', 'display_type' => 'code']],
        ]);

        $column = (new CurrencyColumn)->make($field);

        expect($column)
            ->toBeInstanceOf(BaseTextColumn::class)
            ->isNumeric()->toBeTrue()
            ->getPrefix()->toBe('EUR ');
    });

    it('uses configured currency code from settings', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['currency_code' => 'EUR', 'decimal_places' => 2]],
        ]);

        $column = (new CurrencyColumn)->make($field);

        expect($column)
            ->toBeInstanceOf(BaseTextColumn::class)
            ->isMoney()->toBeTrue();
    });

    it('creates a column for fields with zero decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['decimal_places' => 0]],
        ]);

        $column = (new CurrencyColumn)->make($field);

        expect($column)
            ->toBeInstanceOf(BaseTextColumn::class)
            ->isMoney()->toBeTrue();
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
    it('creates a money-formatted entry with symbol display type by default', function (): void {
        $field = CustomField::factory()->ofType('currency')->create();

        $entry = (new CurrencyEntry)->make($field);

        expect($entry)
            ->toBeInstanceOf(BaseTextEntry::class)
            ->isMoney()->toBeTrue();
    });

    it('uses numeric formatting with code prefix for code display type', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['currency_code' => 'GBP', 'display_type' => 'code']],
        ]);

        $entry = (new CurrencyEntry)->make($field);

        expect($entry)
            ->toBeInstanceOf(BaseTextEntry::class)
            ->isNumeric()->toBeTrue()
            ->getPrefix()->toBe('GBP ');
    });

    it('creates an entry for fields with zero decimal places', function (): void {
        $field = CustomField::factory()->ofType('currency')->create([
            'settings' => ['additional' => ['decimal_places' => 0]],
        ]);

        $entry = (new CurrencyEntry)->make($field);

        expect($entry)
            ->toBeInstanceOf(BaseTextEntry::class)
            ->isMoney()->toBeTrue();
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

    it('renders a step of :dataset on the record form', function (array $attributes, string $step): void {
        $this->actingAs(User::factory()->create());

        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['active' => true]);

        CustomField::factory()->ofType('currency')->create([
            'custom_field_section_id' => $section->getKey(),
            'entity_type' => Post::class,
            'code' => 'amount',
            ...$attributes,
        ]);

        livewire(CreatePost::class)
            ->assertSeeHtml("step=\"{$step}\" type=\"number\" wire:model=\"data.custom_fields.amount\"");
    })->with([
        'the default 2 decimal places' => [[], '0.01'],
        'configured decimal places from settings' => [['settings' => ['additional' => ['decimal_places' => 4]]], '0.0001'],
        'zero decimal places' => [['settings' => ['additional' => ['decimal_places' => 0]]], '1'],
        'legacy decimal places in validation_rules' => [['validation_rules' => ['decimal_places' => 4]], '0.0001'],
    ]);
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
