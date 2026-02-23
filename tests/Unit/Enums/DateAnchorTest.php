<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;

it('DateAnchor has correct cases and values', function (): void {
    expect(DateAnchor::Today->value)->toBe('today')
        ->and(DateAnchor::FixedDate->value)->toBe('fixed_date')
        ->and(DateAnchor::CustomField->value)->toBe('custom_field')
        ->and(DateAnchor::RecordCreated->value)->toBe('record_created');
});

it('DateAnchor implements HasLabel', function (): void {
    expect(DateAnchor::Today->getLabel())->toBe('Today')
        ->and(DateAnchor::FixedDate->getLabel())->toBe('Fixed Date')
        ->and(DateAnchor::CustomField->getLabel())->toBe('Another Field')
        ->and(DateAnchor::RecordCreated->getLabel())->toBe('Record Creation Date');
});

it('DateOffsetDirection has correct cases and values', function (): void {
    expect(DateOffsetDirection::Before->value)->toBe('before')
        ->and(DateOffsetDirection::After->value)->toBe('after');
});

it('DateOffsetDirection implements HasLabel', function (): void {
    expect(DateOffsetDirection::Before->getLabel())->toBe('Before')
        ->and(DateOffsetDirection::After->getLabel())->toBe('After');
});
