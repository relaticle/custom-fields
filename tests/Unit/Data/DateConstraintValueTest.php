<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-17 10:30:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('today anchor', function (): void {
    it('resolves to today with zero offset', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
        ]);

        expect($constraint->resolve()->format('Y-m-d H:i:s'))->toBe('2026-02-17 00:00:00');
    });

    it('resolves to start of day', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
            'offset' => 0,
        ]);

        expect($constraint->resolve()->hour)->toBe(0)
            ->and($constraint->resolve()->minute)->toBe(0)
            ->and($constraint->resolve()->second)->toBe(0);
    });

    it('resolves forward offset in days', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-24');
    });

    it('resolves backward offset in days', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
            'offset' => 30,
            'offset_unit' => 'days',
            'offset_direction' => 'before',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-01-18');
    });

    it('resolves forward offset in weeks', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
            'offset' => 2,
            'offset_unit' => 'weeks',
            'offset_direction' => 'after',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-03-03');
    });

    it('resolves backward offset in months', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
            'offset' => 3,
            'offset_unit' => 'months',
            'offset_direction' => 'before',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2025-11-17');
    });

    it('resolves forward offset in quarters', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
            'offset' => 1,
            'offset_unit' => 'quarters',
            'offset_direction' => 'after',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-05-17');
    });

    it('resolves backward offset in years', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
            'offset' => 18,
            'offset_unit' => 'years',
            'offset_direction' => 'before',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2008-02-17');
    });
});

describe('fixed date anchor', function (): void {
    it('resolves to the fixed date with zero offset', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'fixed_date',
            'fixed_date' => '2026-06-15',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-06-15');
    });

    it('resolves to the fixed date with offset', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'fixed_date',
            'fixed_date' => '2026-06-15',
            'offset' => 10,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-06-25');
    });
});

describe('custom field anchor', function (): void {
    it('resolves via get callback', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
        ]);

        $getCallback = fn (string $path): string => match ($path) {
            'custom_fields.start_date' => '2026-03-01',
            default => '',
        };

        expect($constraint->resolve($getCallback)->format('Y-m-d'))->toBe('2026-03-01');
    });

    it('resolves via get callback with offset', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
            'offset' => 5,
            'offset_unit' => 'days',
            'offset_direction' => 'before',
        ]);

        $getCallback = fn (string $path): string => match ($path) {
            'custom_fields.start_date' => '2026-03-01',
            default => '',
        };

        expect($constraint->resolve($getCallback)->format('Y-m-d'))->toBe('2026-02-24');
    });

    it('falls back to today when callback returns null', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
        ]);

        $getCallback = fn (string $path) => null;

        expect($constraint->resolve($getCallback)->format('Y-m-d'))->toBe('2026-02-17');
    });

    it('falls back to today when no callback provided', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-17');
    });
});

describe('record created anchor', function (): void {
    it('resolves from record created_at', function (): void {
        $record = Mockery::mock(Model::class);
        $record->shouldReceive('getAttribute')->with('created_at')->andReturn(Carbon::parse('2026-01-15'));

        $constraint = DateConstraintValue::from([
            'anchor' => 'record_created',
        ]);

        expect($constraint->resolve(record: $record)->format('Y-m-d'))->toBe('2026-01-15');
    });

    it('resolves from record created_at with offset', function (): void {
        $record = Mockery::mock(Model::class);
        $record->shouldReceive('getAttribute')->with('created_at')->andReturn(Carbon::parse('2026-01-15'));

        $constraint = DateConstraintValue::from([
            'anchor' => 'record_created',
            'offset' => 30,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]);

        expect($constraint->resolve(record: $record)->format('Y-m-d'))->toBe('2026-02-14');
    });

    it('falls back to today when record is null', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'record_created',
        ]);

        expect($constraint->resolve()->format('Y-m-d'))->toBe('2026-02-17');
    });
});

describe('serialization', function (): void {
    it('serializes to and from array', function (): void {
        $data = [
            'anchor' => 'today',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ];

        $constraint = DateConstraintValue::from($data);
        $serialized = $constraint->toArray();

        expect($serialized['anchor'])->toBe('today')
            ->and($serialized['offset'])->toBe(7)
            ->and($serialized['offset_unit'])->toBe('days')
            ->and($serialized['offset_direction'])->toBe('after');
    });

    it('serializes with fixed_date', function (): void {
        $data = [
            'anchor' => 'fixed_date',
            'fixed_date' => '2026-06-15',
        ];

        $constraint = DateConstraintValue::from($data);
        $serialized = $constraint->toArray();

        expect($serialized['anchor'])->toBe('fixed_date')
            ->and($serialized['fixed_date'])->toBe('2026-06-15');
    });

    it('serializes with field_reference', function (): void {
        $data = [
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
        ];

        $constraint = DateConstraintValue::from($data);
        $serialized = $constraint->toArray();

        expect($serialized['anchor'])->toBe('custom_field')
            ->and($serialized['field_reference'])->toBe('start_date');
    });
});

describe('default values', function (): void {
    it('defaults offset to 0, unit to days, direction to after', function (): void {
        $constraint = DateConstraintValue::from([
            'anchor' => 'today',
        ]);

        expect($constraint->offset)->toBe(0)
            ->and($constraint->offsetUnit)->toBe(DateUnit::Days)
            ->and($constraint->offsetDirection)->toBe(DateOffsetDirection::After);
    });
});
