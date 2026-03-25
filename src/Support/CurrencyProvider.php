<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use NumberFormatter;
use ResourceBundle;

final class CurrencyProvider
{
    /** @var array<string, string>|null */
    private static ?array $cachedOptions = null;

    private static ?string $cachedLocale = null;

    /** @var array<string, int>|null */
    private static ?array $cachedDigits = null;

    /**
     * Known-obsolete currency codes that ICU doesn't consistently mark as historical.
     * These were replaced by other currencies (mostly EUR) but lack date annotations in ICU data.
     *
     * @var array<int, string>
     */
    private const OBSOLETE_CODES = [
        // Replaced by EUR
        'ADP', 'ATS', 'BEF', 'CYP', 'DEM', 'EEK', 'ESP', 'FIM', 'FRF', 'GRD',
        'HRK', 'IEP', 'ITL', 'LTL', 'LUF', 'LVL', 'MCF', 'MTL', 'NLG', 'PTE',
        'SIT', 'SKK', 'SML', 'VAL',
        // Other obsolete
        'MGF', 'MRO', 'STD', 'VEB', 'VEF', 'TMM', 'ZWD', 'ZWL', 'ZWR',
    ];

    /**
     * X-prefixed codes that are real currencies (CFA franc zones, East Caribbean).
     *
     * @var array<int, string>
     */
    private const REAL_X_CODES = ['XOF', 'XAF', 'XPF', 'XCD'];

    /**
     * @return array<string, string> Currency code => "Name (CODE)" for select options
     */
    public static function getOptions(): array
    {
        $locale = app()->getLocale();

        if (self::$cachedOptions !== null && self::$cachedLocale === $locale) {
            return self::$cachedOptions;
        }

        self::$cachedLocale = $locale;

        $configCurrencies = config('custom-fields.currency.currencies');

        if (is_array($configCurrencies) && $configCurrencies !== []) {
            return self::$cachedOptions = self::buildOptionsFromConfig($configCurrencies);
        }

        return self::$cachedOptions = self::buildOptionsFromIcu($locale);
    }

    /**
     * Get the standard number of decimal places for a currency code.
     */
    public static function getDecimalDigits(string $code): int
    {
        if (self::$cachedDigits === null) {
            self::$cachedDigits = [];
        }

        if (isset(self::$cachedDigits[$code])) {
            return self::$cachedDigits[$code];
        }

        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $code);

        return self::$cachedDigits[$code] = $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    /**
     * @return array<string, string>
     */
    private static function buildOptionsFromIcu(string $locale): array
    {
        $bundle = new ResourceBundle($locale, 'ICUDATA-curr');
        $currencies = $bundle->get('Currencies');
        $options = [];

        foreach ($currencies as $code => $data) {
            if (strlen($code) !== 3) {
                continue;
            }

            $name = $data[1];

            if (preg_match('/\(\d{4}/', $name)) {
                continue;
            }

            if ($code[0] === 'X' && ! in_array($code, self::REAL_X_CODES, true)) {
                continue;
            }

            if (in_array($code, self::OBSOLETE_CODES, true)) {
                continue;
            }

            $options[$code] = sprintf('%s (%s)', $name, $code);
        }

        ksort($options);

        return $options;
    }

    /**
     * @param  array<string, string>  $currencies  Code => Name pairs from config
     * @return array<string, string>
     */
    private static function buildOptionsFromConfig(array $currencies): array
    {
        $options = [];

        foreach ($currencies as $code => $name) {
            $options[$code] = sprintf('%s (%s)', $name, $code);
        }

        return $options;
    }

    /**
     * Flush cached data (useful for testing).
     */
    public static function flush(): void
    {
        self::$cachedOptions = null;
        self::$cachedLocale = null;
        self::$cachedDigits = null;
    }
}
