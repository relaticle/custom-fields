<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Decimal separator convention for CSV import parsing.
 *
 * Only the decimal separator is configurable; thousands separators are stripped.
 * Declaring it means `1.234,56` has one meaning instead of two.
 */
enum ImportNumberFormat: string implements HasLabel
{
    case POINT = 'point';
    case COMMA = 'comma';

    public function getLabel(): string
    {
        return match ($this) {
            self::POINT => 'Point',
            self::COMMA => 'Comma',
        };
    }

    public function getExample(): string
    {
        return match ($this) {
            self::POINT => '1,234.56',
            self::COMMA => '1.234,56',
        };
    }

    /**
     * `$stripCurrencySymbol` keeps currency columns accepting `$1,234.56` and
     * `1234.56 EUR`, which they accept today. It is not applied to plain number
     * columns, which reject anything with non-numeric characters.
     */
    public function parse(string $value, bool $stripCurrencySymbol = false): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($stripCurrencySymbol) {
            // Keep the sign, drop a symbol or code on either side: -$5.50, $1,234.56, 1234.56 EUR.
            $value = preg_replace('/^([+-]?)[^\d]*/u', '$1', $value) ?? '';
            $value = preg_replace('/[^\d]*$/u', '', $value) ?? '';
        }

        $decimalSeparator = match ($this) {
            self::POINT => '.',
            self::COMMA => ',',
        };

        $otherSeparator = match ($this) {
            self::POINT => ',',
            self::COMMA => '.',
        };

        $value = str_replace([' ', "\u{00A0}", $otherSeparator], '', $value);

        if ($decimalSeparator === ',') {
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
