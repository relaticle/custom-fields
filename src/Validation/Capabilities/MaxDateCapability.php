<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Relaticle\CustomFields\Data\DateConstraintValue;

final readonly class MaxDateCapability extends AbstractDateCapability
{
    public function key(): string
    {
        return 'max_date';
    }

    public function label(): string
    {
        return 'Maximum Date';
    }

    protected function context(): string
    {
        return 'max';
    }

    protected function comparison(): string
    {
        return 'before_or_equal';
    }

    protected function applyConstraint(Field $component, DateConstraintValue $constraint): void
    {
        $component->maxDate(self::resolveDate($constraint)); // @phpstan-ignore method.notFound
    }
}
