<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\VisibilityOperator;

it('matches is_in when the related set intersects the selected list', function (): void {
    expect(VisibilityOperator::IS_IN->evaluate([5, 9], [9, 12]))->toBeTrue()
        ->and(VisibilityOperator::IS_IN->evaluate([5, 9], [12, 13]))->toBeFalse()
        ->and(VisibilityOperator::IS_IN->evaluate([], [9]))->toBeFalse()
        ->and(VisibilityOperator::IS_IN->evaluate([9], []))->toBeFalse();
});

it('matches is_not_in as the negation of is_in', function (): void {
    expect(VisibilityOperator::IS_NOT_IN->evaluate([5, 9], [12]))->toBeTrue()
        ->and(VisibilityOperator::IS_NOT_IN->evaluate([5, 9], [9]))->toBeFalse()
        ->and(VisibilityOperator::IS_NOT_IN->evaluate([], [9]))->toBeTrue();
});

it('normalizes int vs string keys before intersecting', function (): void {
    expect(VisibilityOperator::IS_IN->evaluate([5, 9], ['9']))->toBeTrue()
        ->and(VisibilityOperator::IS_IN->evaluate(['5'], [5]))->toBeTrue();
});
