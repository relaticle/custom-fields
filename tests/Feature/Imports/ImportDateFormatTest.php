<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\ImportDateFormat;

it('keeps parsing everything the Carbon fallback accepted', function (string $cell): void {
    expect(ImportDateFormat::EUROPEAN->parse($cell)?->format('Y-m-d'))->toBe('2024-01-15');
})->with([
    '2024-01-15',
    '15/01/2024',
    '15-01-2024',
    '15.01.2024',
    'January 15, 2024',
    '15 January 2024',
    '2024-01-15 10:30:00',
]);

it('rejects calendar-invalid and out-of-range dates', function (string $cell): void {
    expect(ImportDateFormat::EUROPEAN->parse($cell))->toBeNull();
})->with([
    '31/02/2024',
    '13/45/2024',
    '2024-02-31',
    '2024-13-01',
    '99/99/9999',
]);

it('rejects free text, two-digit years and blanks', function (string $cell): void {
    expect(ImportDateFormat::EUROPEAN->parse($cell))->toBeNull();
})->with([
    'invalid-date',
    'Q/A',
    'next tuesday',
    '1/1/24',
    '',
    '   ',
]);

it('reads an ambiguous date according to the declared convention', function (): void {
    expect(ImportDateFormat::EUROPEAN->parse('3/4/2024')?->format('Y-m-d'))->toBe('2024-04-03')
        ->and(ImportDateFormat::AMERICAN->parse('3/4/2024')?->format('Y-m-d'))->toBe('2024-03-04')
        ->and(ImportDateFormat::ISO->parse('3/4/2024'))->toBeNull();
});

it('accepts ISO under every convention so a Y-m-d cell is never re-read', function (ImportDateFormat $format): void {
    expect($format->parse('2024-01-15')?->format('Y-m-d'))->toBe('2024-01-15');
})->with(ImportDateFormat::cases());

it('parses datetimes when asked', function (): void {
    expect(ImportDateFormat::ISO->parse('2024-01-15 10:30:00', withTime: true)?->format('Y-m-d H:i:s'))
        ->toBe('2024-01-15 10:30:00')
        ->and(ImportDateFormat::EUROPEAN->parse('15/01/2024 10:30:00', withTime: true)?->format('Y-m-d H:i:s'))
        ->toBe('2024-01-15 10:30:00');
});

it('rejects an overflowing datetime', function (): void {
    expect(ImportDateFormat::EUROPEAN->parse('31/02/2024 10:30:00', withTime: true))->toBeNull();
});
