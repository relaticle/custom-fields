<?php

declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Data\DateConstraintValue;

it('resolves absolute dates', function (): void {
    $constraint = DateConstraintValue::from([
        'mode' => 'absolute',
        'absolute_value' => '2026-06-15',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-06-15');
});

it('resolves relative dates in days', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 7,
        'relative_unit' => 'days',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-24');
});

it('resolves relative dates in weeks', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 2,
        'relative_unit' => 'weeks',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-03-03');
});

it('resolves relative dates in months', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 3,
        'relative_unit' => 'months',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-05-17');
});

it('resolves relative dates in years', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 1,
        'relative_unit' => 'years',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2027-02-17');
});

it('handles negative relative values for past dates', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => -30,
        'relative_unit' => 'days',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-01-18');
});

it('resolves zero relative value as today', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'mode' => 'relative',
        'relative_value' => 0,
        'relative_unit' => 'days',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-17');
});

it('serializes to and from array', function (): void {
    $data = [
        'mode' => 'relative',
        'relative_value' => 7,
        'relative_unit' => 'days',
    ];

    $constraint = DateConstraintValue::from($data);
    $serialized = $constraint->toArray();

    expect($serialized['mode'])->toBe('relative')
        ->and($serialized['relative_value'])->toBe(7)
        ->and($serialized['relative_unit'])->toBe('days');
});
