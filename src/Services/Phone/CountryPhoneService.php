<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Phone;

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

            if ($regionCode !== '' && $regionCode !== false && $this->isValidRegion($regionCode)) {
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
            $nationalNumber = (string) $parsed->getNationalNumber();

            return ['country' => $country, 'number' => $nationalNumber];
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
            $phone = new PhoneNumber($number, $country);

            return $phone->formatE164();
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

    private function getPhoneUtil(): PhoneNumberUtil
    {
        return $this->util ??= PhoneNumberUtil::getInstance();
    }
}
