<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Contracts\ValidationCapability;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Filament\Management\Forms\Components\DateConstraintField;

final readonly class MaxDateCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'max_date';
    }

    public function label(): string
    {
        return 'Maximum Date';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return DateConstraintField::make("{$statePath}.max_date", 'Maximum Date');
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $constraintValue = $this->hydrateValue($value);

        $component->maxDate(fn () => $constraintValue->resolve());
    }

    /** @return array<int, string> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $constraintValue = $this->hydrateValue($value);

        return ["before_or_equal:{$constraintValue->resolve()->format('Y-m-d')}"];
    }

    /** @param array<string, mixed> $value */
    private function hydrateValue(mixed $value): DateConstraintValue
    {
        return DateConstraintValue::from($value);
    }
}
