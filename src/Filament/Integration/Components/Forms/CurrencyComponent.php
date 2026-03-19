<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use NumberFormatter;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class CurrencyComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): TextInput
    {
        $decimalPlaces = $customField->getDecimalPlaces();
        $currencyCode = $customField->getCurrencyCode();
        $prefix = self::getCurrencySymbol($currencyCode, $customField->getCurrencyDisplayType());

        return TextInput::make($customField->getFieldName())
            ->prefix($prefix)
            ->numeric()
            ->inputMode('decimal')
            ->step($decimalPlaces > 0 ? 1 / (10 ** $decimalPlaces) : 1)
            ->formatStateUsing(function (mixed $state) use ($decimalPlaces): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return number_format((float) $state, $decimalPlaces);
            })
            ->dehydrateStateUsing(function (mixed $state): ?float {
                if ($state === null || $state === '') {
                    return null;
                }

                return Str::of($state)->replace(',', '')->toFloat();
            });
    }

    private static function getCurrencySymbol(string $currencyCode, string $displayType): string
    {
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);

        $formatted = $formatter->formatCurrency(0, $currencyCode);

        if ($formatted === false) {
            return $currencyCode;
        }

        $symbol = str_replace(['0', ' ', "\xC2\xA0"], '', $formatted);

        if ($symbol === '' || $displayType === 'code') {
            return $currencyCode;
        }

        return $symbol;
    }
}
