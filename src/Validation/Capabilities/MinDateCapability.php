<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Contracts\ValidationCapability;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Enums\DateDirection;
use Relaticle\CustomFields\Enums\DateUnit;
use Relaticle\CustomFields\Filament\Management\Forms\Components\DateConstraintField;

final readonly class MinDateCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'min_date';
    }

    public function label(): string
    {
        return 'Minimum Date';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return DateConstraintField::make("{$statePath}.min_date", 'Minimum Date');
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $constraintValue = $this->hydrateValue($value);

        $component->minDate(fn () => $constraintValue->resolve());
    }

    /** @return array<int, string> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $constraintValue = $this->hydrateValue($value);

        return ["after_or_equal:{$constraintValue->resolve()->format('Y-m-d')}"];
    }

    /** @param array<string, mixed> $value */
    private function hydrateValue(mixed $value): DateConstraintValue
    {
        return new DateConstraintValue(
            relativeValue: (int) ($value['relative_value'] ?? 0),
            relativeUnit: DateUnit::from($value['relative_unit']),
            direction: DateDirection::from($value['direction'] ?? DateDirection::FromNow->value),
        );
    }
}
