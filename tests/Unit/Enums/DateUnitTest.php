<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\DateUnit;

it('has Days, Weeks, Months, Years cases', function (): void {
    $caseNames = array_map(fn (DateUnit $unit): string => $unit->name, DateUnit::cases());

    expect($caseNames)->toBe(['Days', 'Weeks', 'Months', 'Years']);
});

it('provides labels for form selects', function (DateUnit $unit, string $expected): void {
    expect($unit->getLabel())->toBe($expected);
})->with([
    [DateUnit::Days, 'Days'],
    [DateUnit::Weeks, 'Weeks'],
    [DateUnit::Months, 'Months'],
    [DateUnit::Years, 'Years'],
]);
