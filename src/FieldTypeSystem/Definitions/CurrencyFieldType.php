<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use NumberFormatter;
use Relaticle\CustomFields\Data\Settings\CurrencyFieldSettingsData;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CurrencyComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\CurrencyEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\CurrencyColumn;
use Relaticle\CustomFields\Validation\Capabilities\MaxValueCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinValueCapability;

class CurrencyFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::float()
            ->key('currency')
            ->label('Currency')
            ->icon('mdi-currency-usd')
            ->formComponent(CurrencyComponent::class)
            ->tableColumn(CurrencyColumn::class)
            ->infolistEntry(CurrencyEntry::class)
            ->priority(25)
            ->withValidationCapabilities(
                MinValueCapability::class,
                MaxValueCapability::class,
            )
            ->withSettings(
                CurrencyFieldSettingsData::class,
                fn (): array => $this->settingsSchema(),
            )
            ->importExample('99.99')
            ->importTransformer(function (mixed $state): ?float {
                if (blank($state)) {
                    return null;
                }

                if (is_string($state)) {
                    $state = preg_replace('/[^0-9.-]/', '', $state);
                }

                return (float) $state;
            })
            ->exportTransformer(function (mixed $value): ?string {
                if ($value === null) {
                    return null;
                }

                return rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.');
            });
    }

    /**
     * @return array<int, Component>
     */
    private function settingsSchema(): array
    {
        $defaultCode = config('custom-fields.currency.default_code', 'USD');

        return [
            Fieldset::make('Currency Settings')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('settings.additional.currency_code')
                        ->label('Currency')
                        ->searchable()
                        ->options(fn (): array => $this->getCurrencyOptions())
                        ->afterStateHydrated(function (Select $component, mixed $state) use ($defaultCode): void {
                            if (blank($state)) {
                                $component->state($defaultCode);
                            }
                        })
                        ->required()
                        ->live(),

                    Select::make('settings.additional.display_type')
                        ->label('Display')
                        ->options([
                            'symbol' => 'Symbol ($1,200.50)',
                            'code' => 'Code (USD 1,200.50)',
                        ])
                        ->afterStateHydrated(function (Select $component, mixed $state): void {
                            if (blank($state)) {
                                $component->state('symbol');
                            }
                        })
                        ->required(),

                    Select::make('settings.additional.decimal_places')
                        ->label('Decimal Places')
                        ->options(function (Get $get): array {
                            $code = $get('settings.additional.currency_code') ?? 'USD';

                            $options = [];

                            foreach ([0, 2, 3] as $digits) {
                                $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
                                $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $digits);
                                $sample = $formatter->formatCurrency(1200.50, $code) ?: number_format(1200.50, $digits);

                                $label = $digits === 0 ? 'No decimals' : $digits . ' decimals';
                                $options[(string) $digits] = sprintf('%s (%s)', $label, $sample);
                            }

                            return $options;
                        })
                        ->afterStateHydrated(function (Select $component, mixed $state): void {
                            $component->state($state === null ? '2' : (string) $state);
                        })
                        ->required(),

                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getCurrencyOptions(): array
    {
        static $options = null;

        if ($options !== null) {
            return $options;
        }

        $currencies = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'JPY' => 'Japanese Yen',
            'CHF' => 'Swiss Franc',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'NZD' => 'New Zealand Dollar',
            'CNY' => 'Chinese Yuan',
            'HKD' => 'Hong Kong Dollar',
            'SGD' => 'Singapore Dollar',
            'SEK' => 'Swedish Krona',
            'NOK' => 'Norwegian Krone',
            'DKK' => 'Danish Krone',
            'KRW' => 'South Korean Won',
            'INR' => 'Indian Rupee',
            'BRL' => 'Brazilian Real',
            'MXN' => 'Mexican Peso',
            'ZAR' => 'South African Rand',
            'TRY' => 'Turkish Lira',
            'RUB' => 'Russian Ruble',
            'PLN' => 'Polish Zloty',
            'THB' => 'Thai Baht',
            'IDR' => 'Indonesian Rupiah',
            'MYR' => 'Malaysian Ringgit',
            'PHP' => 'Philippine Peso',
            'CZK' => 'Czech Koruna',
            'HUF' => 'Hungarian Forint',
            'ILS' => 'Israeli Shekel',
            'CLP' => 'Chilean Peso',
            'ARS' => 'Argentine Peso',
            'COP' => 'Colombian Peso',
            'PEN' => 'Peruvian Sol',
            'AED' => 'UAE Dirham',
            'SAR' => 'Saudi Riyal',
            'QAR' => 'Qatari Riyal',
            'KWD' => 'Kuwaiti Dinar',
            'BHD' => 'Bahraini Dinar',
            'OMR' => 'Omani Rial',
            'EGP' => 'Egyptian Pound',
            'NGN' => 'Nigerian Naira',
            'KES' => 'Kenyan Shilling',
            'GHS' => 'Ghanaian Cedi',
            'TWD' => 'Taiwan Dollar',
            'VND' => 'Vietnamese Dong',
            'BGN' => 'Bulgarian Lev',
            'RON' => 'Romanian Leu',
            'ISK' => 'Icelandic Krona',
            'UAH' => 'Ukrainian Hryvnia',
            'PKR' => 'Pakistani Rupee',
            'BDT' => 'Bangladeshi Taka',
            'LKR' => 'Sri Lankan Rupee',
        ];

        $options = [];

        foreach ($currencies as $code => $name) {
            $options[$code] = sprintf('%s (%s)', $name, $code);
        }

        return $options;
    }
}
