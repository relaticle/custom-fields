<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Phone;

use Illuminate\Support\Str;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Locale;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Service for providing country and phone code data.
 */
final class CountryPhoneService
{
    private ?PhoneNumberUtil $util = null;

    /**
     * Get country options with short codes for selected display.
     *
     * @return array<string, string> ['AM' => 'AM +374', 'US' => 'US +1', ...]
     */
    public function getCountryOptions(): array
    {
        $util = $this->getPhoneUtil();
        /** @var array<int, string> $regions */
        $regions = $util->getSupportedRegions();
        $options = [];

        foreach ($regions as $regionCode) {
            $callingCode = $util->getCountryCodeForRegion($regionCode);
            $options[$regionCode] = sprintf('%s +%d', $regionCode, $callingCode);
        }

        asort($options);

        return $options;
    }

    /**
     * Get country options with full names for dropdown list.
     *
     * @return array<string, string> ['AM' => 'Armenia (+374)', 'US' => 'United States (+1)', ...]
     */
    public function getCountryOptionsWithNames(): array
    {
        $util = $this->getPhoneUtil();
        /** @var array<int, string> $regions */
        $regions = $util->getSupportedRegions();
        $options = [];
        $locale = app()->getLocale();

        foreach ($regions as $regionCode) {
            $callingCode = $util->getCountryCodeForRegion($regionCode);
            $countryName = Locale::getDisplayRegion('-'.$regionCode, $locale) ?: $regionCode;
            $options[$regionCode] = sprintf('%s (+%d)', $countryName, $callingCode);
        }

        asort($options);

        return $options;
    }

    /**
     * Get the calling code for a region.
     */
    public function getCallingCode(string $regionCode): int
    {
        return $this->getPhoneUtil()->getCountryCodeForRegion($regionCode);
    }

    /**
     * Detect country from application locale.
     */
    public function detectCountryFromLocale(): string
    {
        $locale = app()->getLocale();

        // Try to extract region from locale (e.g., 'en_US' -> 'US')
        if (str_contains($locale, '_')) {
            $parts = explode('_', $locale);
            $regionCode = strtoupper($parts[1] ?? '');

            if ($regionCode !== '' && $this->isValidRegion($regionCode)) {
                return $regionCode;
            }
        }

        // Try locale_get_region if available
        if (function_exists('locale_get_region')) {
            $regionCode = locale_get_region($locale);

            if ($regionCode !== '' && $this->isValidRegion($regionCode)) {
                return $regionCode;
            }
        }

        return 'US'; // Default fallback
    }

    /**
     * Check if a region code is valid.
     */
    public function isValidRegion(string $regionCode): bool
    {
        /** @var array<int, string> $regions */
        $regions = $this->getPhoneUtil()->getSupportedRegions();

        return in_array(strtoupper($regionCode), $regions, true);
    }

    /**
     * Parse E.164 formatted phone number into country and national number.
     *
     * @return array{country: string, number: string}
     */
    public function parseE164(string $e164, string $defaultCountry = 'US'): array
    {
        if (blank($e164)) {
            return ['country' => $defaultCountry, 'number' => ''];
        }

        try {
            $phone = new PhoneNumber($e164);
            $country = $phone->getCountry();

            if ($country === null) {
                return ['country' => $defaultCountry, 'number' => ltrim($e164, '+')];
            }

            $parsed = $this->getPhoneUtil()->parse($e164);
            $nationalNumber = $this->getPhoneUtil()->getNationalSignificantNumber($parsed);
            $extension = $parsed->getExtension();

            return ['country' => $country, 'number' => filled($extension) ? "{$nationalNumber} ext. {$extension}" : $nationalNumber];
        } catch (Throwable) {
            return ['country' => $defaultCountry, 'number' => ltrim($e164, '+')];
        }
    }

    /**
     * Format country and national number to E.164.
     */
    public function formatToE164(string $country, string $number): ?string
    {
        if (blank($number)) {
            return null;
        }

        try {
            return $this->canonical($this->getPhoneUtil()->parse($number, strtoupper($country)));
        } catch (Throwable) {
            // Fallback: manually prepend country code
            $callingCode = $this->getCallingCode($country);
            $cleanNumber = preg_replace('/\D/', '', $number);

            return sprintf('+%d%s', $callingCode, $cleanNumber);
        }
    }

    /**
     * Format phone number for display in international format.
     */
    public function formatForDisplay(string $e164): string
    {
        if (blank($e164)) {
            return '';
        }

        try {
            $phone = new PhoneNumber($e164);

            return $phone->formatInternational();
        } catch (Throwable) {
            return $e164;
        }
    }

    public function normalize(string $value): string
    {
        $trimmed = trim($value);

        try {
            $parsed = $this->getPhoneUtil()->parse($trimmed);
        } catch (Throwable) {
            return $trimmed;
        }

        return $this->getPhoneUtil()->isPossibleNumber($parsed) ? $this->canonical($parsed) : $trimmed;
    }

    public function displayText(string $stored): string
    {
        return str_contains($stored, ';') ? $this->formatForDisplay($stored) : $stored;
    }

    public function dialNumber(string $stored): string
    {
        return (string) preg_replace('/[^0-9+]/', '', Str::before($stored, ';'));
    }

    private function canonical(LibPhoneNumber $parsed): string
    {
        $e164 = $this->getPhoneUtil()->format($parsed, PhoneNumberFormat::E164);
        $extension = $parsed->getExtension();

        return filled($extension) ? "{$e164};ext={$extension}" : $e164;
    }

    private function getPhoneUtil(): PhoneNumberUtil
    {
        return $this->util ??= PhoneNumberUtil::getInstance();
    }
}
