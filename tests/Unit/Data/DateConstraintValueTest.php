<?php

declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Data\DateConstraintValue;

it('resolves future dates in days', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'relative_value' => 7,
        'relative_unit' => 'days',
        'direction' => 'from_now',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-24');
});

it('resolves past dates in days', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'relative_value' => 30,
        'relative_unit' => 'days',
        'direction' => 'ago',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-01-18');
});

it('resolves future dates in weeks', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'relative_value' => 2,
        'relative_unit' => 'weeks',
        'direction' => 'from_now',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-03-03');
});

it('resolves past dates in months', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'relative_value' => 3,
        'relative_unit' => 'months',
        'direction' => 'ago',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2025-11-17');
});

it('resolves future dates in quarters', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'relative_value' => 1,
        'relative_unit' => 'quarters',
        'direction' => 'from_now',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-05-17');
});

it('resolves past dates in years', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'relative_value' => 18,
        'relative_unit' => 'years',
        'direction' => 'ago',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2008-02-17');
});

it('resolves zero value as today regardless of direction', function (): void {
    Carbon::setTestNow('2026-02-17');

    $ago = DateConstraintValue::from([
        'relative_value' => 0,
        'relative_unit' => 'days',
        'direction' => 'ago',
    ]);

    $fromNow = DateConstraintValue::from([
        'relative_value' => 0,
        'relative_unit' => 'days',
        'direction' => 'from_now',
    ]);

    expect($ago->resolve()->format('Y-m-d'))->toBe('2026-02-17')
        ->and($fromNow->resolve()->format('Y-m-d'))->toBe('2026-02-17');
});

it('defaults direction to from_now', function (): void {
    Carbon::setTestNow('2026-02-17');

    $constraint = DateConstraintValue::from([
        'relative_value' => 7,
        'relative_unit' => 'days',
    ]);

    expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-24');
});

it('serializes to and from array', function (): void {
    $data = [
        'relative_value' => 7,
        'relative_unit' => 'days',
        'direction' => 'ago',
    ];

    $constraint = DateConstraintValue::from($data);
    $serialized = $constraint->toArray();

    expect($serialized['relative_value'])->toBe(7)
        ->and($serialized['relative_unit'])->toBe('days')
        ->and($serialized['direction'])->toBe('ago');
});
