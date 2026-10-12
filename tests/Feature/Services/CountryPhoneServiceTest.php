<?php

declare(strict_types=1);

use Relaticle\CustomFields\Services\Phone\CountryPhoneService;

beforeEach(function (): void {
    $this->service = new CountryPhoneService;
});

it('provides country options with short codes', function (): void {
    $options = $this->service->getCountryOptions();

    expect($options)->toBeArray()
        ->and($options['US'])->toBe('US +1')
        ->and($options['GB'])->toBe('GB +44')
        ->and($options['DE'])->toBe('DE +49')
        ->and($options['FR'])->toBe('FR +33');
});

it('provides country options with full names', function (): void {
    $options = $this->service->getCountryOptionsWithNames();

    expect($options)->toBeArray()
        ->and($options['US'])->toContain('United States')
        ->and($options['US'])->toContain('(+1)')
        ->and($options['GB'])->toContain('United Kingdom')
        ->and($options['GB'])->toContain('(+44)');
});

it('returns sorted country options alphabetically', function (): void {
    $options = $this->service->getCountryOptions();
    $codes = array_keys($options);

    $sortedCodes = $codes;
    sort($sortedCodes);

    expect($codes)->toBe($sortedCodes);
});

it('detects country from locale', function (): void {
    app()->setLocale('en_US');
    expect($this->service->detectCountryFromLocale())->toBe('US');

    app()->setLocale('en_GB');
    expect($this->service->detectCountryFromLocale())->toBe('GB');

    app()->setLocale('de_DE');
    expect($this->service->detectCountryFromLocale())->toBe('DE');
});

it('falls back to US for invalid locale', function (): void {
    app()->setLocale('invalid');
    expect($this->service->detectCountryFromLocale())->toBe('US');

    app()->setLocale('en');
    expect($this->service->detectCountryFromLocale())->toBe('US');
});

it('parses E.164 to country and number', function (): void {
    $parsed = $this->service->parseE164('+14155551234');
    expect($parsed['country'])->toBe('US')
        ->and($parsed['number'])->toBe('4155551234');

    $parsed = $this->service->parseE164('+442071234567');
    expect($parsed['country'])->toBe('GB')
        ->and($parsed['number'])->toBe('2071234567');

    $parsed = $this->service->parseE164('+37491234567');
    expect($parsed['country'])->toBe('AM')
        ->and($parsed['number'])->toBe('91234567');
});

it('handles invalid E.164 with fallback', function (): void {
    $parsed = $this->service->parseE164('invalid-number', 'DE');
    expect($parsed['country'])->toBe('DE')
        ->and($parsed['number'])->toBe('invalid-number');
});

it('formats country and number to E.164', function (): void {
    $e164 = $this->service->formatToE164('US', '4155551234');
    expect($e164)->toBe('+14155551234');

    $e164 = $this->service->formatToE164('GB', '2071234567');
    expect($e164)->toBe('+442071234567');

    $e164 = $this->service->formatToE164('AM', '91234567');
    expect($e164)->toBe('+37491234567');
});

it('returns null for empty number when formatting', function (): void {
    expect($this->service->formatToE164('US', ''))->toBeNull()
        ->and($this->service->formatToE164('US', '   '))->toBeNull();
});

it('handles formatting with fallback for invalid numbers', function (): void {
    $e164 = $this->service->formatToE164('US', '123');
    expect($e164)->not->toBeNull();
});

it('normalizes an international number to E.164', function (string $input, string $expected): void {
    expect($this->service->normalize($input))->toBe($expected);
})->with([
    'spaces and dashes' => ['+1 415-555-0100', '+14155550100'],
    'parentheses' => ['+1 (415) 555-0100', '+14155550100'],
    'italian leading zero' => ['+39 06 1234 5678', '+390612345678'],
    'extension' => ['+1 (415) 555-0100 ext. 12', '+14155550100;ext=12'],
    'already canonical' => ['+14155550100;ext=12', '+14155550100;ext=12'],
    'short number' => ['+1-555-0101', '+15550101'],
]);

it('leaves a number with a text label unchanged', function (string $input, string $expected): void {
    expect($this->service->normalize($input))->toBe($expected);
})->with([
    'mobile label' => ['+1 415 555 0100 (mobile)', '+1 415 555 0100 (mobile)'],
    'office label' => ['+44 20 7946 0958 (office)', '+44 20 7946 0958 (office)'],
    'cell suffix' => ['+1 415 555 0100 cell', '+1 415 555 0100 cell'],
    'padded label' => ['  +1 415 555 0100 (mobile)  ', '+1 415 555 0100 (mobile)'],
]);

it('returns a national number without a country unchanged', function (): void {
    expect($this->service->normalize(' 555-123-4567 '))->toBe('555-123-4567');
});

it('normalizes to the same value when applied twice', function (string $input): void {
    $once = $this->service->normalize($input);

    expect($this->service->normalize($once))->toBe($once);
})->with([
    'plain' => ['+1 415-555-0100'],
    'extension' => ['+1 (415) 555-0100 ext. 12'],
    'canonical extension' => ['+14155550100;ext=12'],
    'italian leading zero' => ['+39 06 1234 5678'],
    'unparsable' => [' 555-123-4567 '],
    'garbage' => ['not a phone'],
    'label' => ['+1 415 555 0100 (mobile)'],
]);

it('keeps the extension through the panel input and display', function (): void {
    $stored = '+14155550100;ext=12';

    $parsed = $this->service->parseE164($stored);

    expect($parsed)->toBe(['country' => 'US', 'number' => '4155550100 ext. 12'])
        ->and($this->service->formatToE164($parsed['country'], $parsed['number']))->toBe($stored);
});

it('keeps the leading zero through the panel input', function (): void {
    $parsed = $this->service->parseE164('+390612345678');

    expect($parsed)->toBe(['country' => 'IT', 'number' => '0612345678'])
        ->and($this->service->formatToE164($parsed['country'], $parsed['number']))->toBe('+390612345678');
});

it('shows the international format with the extension and dials the number alone', function (string $stored, string $display, string $dial): void {
    expect($this->service->displayText($stored))->toBe($display)
        ->and($this->service->dialNumber($stored))->toBe($dial);
})->with([
    'extension' => ['+14155550100;ext=12', '+1 415-555-0100 ext. 12', '+14155550100'],
    'no extension' => ['+14155550100', '+14155550100', '+14155550100'],
    'legacy spaced value' => ['+1 415 555 0100', '+1 415 555 0100', '+14155550100'],
]);
