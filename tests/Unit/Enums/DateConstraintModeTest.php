<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\DateConstraintMode;

it('has Absolute and Relative cases', function (): void {
    expect(DateConstraintMode::cases())->toHaveCount(2)
        ->and(DateConstraintMode::Absolute->value)->toBe('absolute')
        ->and(DateConstraintMode::Relative->value)->toBe('relative');
});
