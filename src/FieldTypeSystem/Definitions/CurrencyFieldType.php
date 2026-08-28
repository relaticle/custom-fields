<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use NumberFormatter;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\Settings\CurrencyFieldSettingsData;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CurrencyComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\CurrencyEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\CurrencyColumn;
use Relaticle\CustomFields\Imports\UnresolvedValue;
use Relaticle\CustomFields\Support\CurrencyProvider;
use Relaticle\CustomFields\Validation\Capabilities\MaxValueCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinValueCapability;

class CurrencyFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::float()
            ->key('currency')
            ->label(__('custom-fields::custom-fields.field_types.currency'))
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
            ->importTransformer(function (mixed $state): float|UnresolvedValue|null {
                if (blank($state)) {
                    return null;
                }

                $format = CustomFields::importNumberFormat();
                $parsed = $format->parse((string) $state, stripCurrencySymbol: true);

                if ($parsed === null) {
                    return UnresolvedValue::make($state, sprintf(
                        "'%s' is not a valid amount. Expected format: %s.",
                        $state,
                        $format->getExample(),
                    ));
                }

                return $parsed;
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
            Fieldset::make(__('custom-fields::custom-fields.currency.fieldset'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('settings.additional.currency_code')
                        ->label(__('custom-fields::custom-fields.currency.currency'))
                        ->searchable()
                        ->options(fn (): array => CurrencyProvider::getOptions())
                        ->afterStateHydrated(function (Select $component, mixed $state) use ($defaultCode): void {
                            if (blank($state)) {
                                $component->state($defaultCode);
                            }
                        })
                        ->required()
                        ->live(),

                    Select::make('settings.additional.display_type')
                        ->label(__('custom-fields::custom-fields.currency.display'))
                        ->options([
                            'symbol' => __('custom-fields::custom-fields.currency.display_options.symbol'),
                            'code' => __('custom-fields::custom-fields.currency.display_options.code'),
                        ])
                        ->afterStateHydrated(function (Select $component, mixed $state): void {
                            if (blank($state)) {
                                $component->state('symbol');
                            }
                        })
                        ->required(),

                    Select::make('settings.additional.decimal_places')
                        ->label(__('custom-fields::custom-fields.currency.decimal_places'))
                        ->helperText(__('custom-fields::custom-fields.currency.decimal_places_helper'))
                        ->options(function (Get $get): array {
                            $code = $get('settings.additional.currency_code') ?? 'USD';

                            $options = [];

                            foreach ([0, 2, 3, 4] as $digits) {
                                $sample = number_format(1200.50, $digits);

                                if (class_exists(NumberFormatter::class)) {
                                    $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
                                    $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $digits);

                                    $formatted = $formatter->formatCurrency(1200.50, $code);

                                    if ($formatted !== false) {
                                        $sample = $formatted;
                                    }
                                }

                                $label = $digits === 0 ? 'No decimals' : $digits.' decimals';
                                $options[(string) $digits] = sprintf('%s (%s)', $label, $sample);
                            }

                            return $options;
                        })
                        ->afterStateHydrated(function (Select $component, mixed $state, Get $get): void {
                            if ($state !== null) {
                                $component->state((string) $state);

                                return;
                            }

                            $code = $get('settings.additional.currency_code') ?? 'USD';
                            $component->state((string) CurrencyProvider::getDecimalDigits($code));
                        })
                        ->required(),

                ]),
        ];
    }
}
