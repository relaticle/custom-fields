<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Relaticle\CustomFields\Data\DateConstraintValue;

final readonly class MinDateCapability extends AbstractDateCapability
{
    public function key(): string
    {
        return 'min_date';
    }

    public function label(): string
    {
        return 'Minimum Date';
    }

    protected function context(): string
    {
        return 'min';
    }

    protected function comparison(): string
    {
        return 'after_or_equal';
    }

    protected function applyConstraint(Field $component, DateConstraintValue $constraint): void
    {
        $component->minDate(self::resolveDate($constraint)); // @phpstan-ignore method.notFound
    }
}
