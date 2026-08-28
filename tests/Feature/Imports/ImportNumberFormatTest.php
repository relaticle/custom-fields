<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\ImportNumberFormat;

it('parses point-decimal numbers', function (string $cell, ?float $expected): void {
    expect(ImportNumberFormat::POINT->parse($cell))->toBe($expected);
})->with([
    ['1234.56', 1234.56],
    ['1,234.56', 1234.56],
    ['1 234.56', 1234.56],
    ['0', 0.0],
    ['-5.5', -5.5],
    ['12abc', null],
    ['Q/A', null],
    ['1.2.3', null],
    ['$1,234.56', null],
    ['', null],
]);

it('parses comma-decimal numbers', function (string $cell, ?float $expected): void {
    expect(ImportNumberFormat::COMMA->parse($cell))->toBe($expected);
})->with([
    ['1234,56', 1234.56],
    ['1.234,56', 1234.56],
    ['Q/A', null],
]);

it('strips a currency symbol or code at either edge', function (string $cell, ?float $expected): void {
    expect(ImportNumberFormat::POINT->parse($cell, stripCurrencySymbol: true))->toBe($expected);
})->with([
    ['$1,234.56', 1234.56],
    ['-$5.50', -5.5],
    ['1234.56 EUR', 1234.56],
    ['USD 99.99', 99.99],
    ['12$34', null],
    ['Q/A', null],
]);

/**
 * `12abc` and `1234.56 EUR` are the same shape, so a currency column cannot reject one
 * and accept the other without a currency allowlist. This matches what the currency
 * field did before, and a plain number column still rejects it.
 */
it('accepts trailing text on a currency column, the price of accepting a currency code', function (): void {
    expect(ImportNumberFormat::POINT->parse('12abc', stripCurrencySymbol: true))->toBe(12.0)
        ->and(ImportNumberFormat::POINT->parse('12abc'))->toBeNull();
});
