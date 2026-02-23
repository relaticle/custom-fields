<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Contracts\ValidationCapability;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Filament\Management\Forms\Components\DateConstraintField;
use Relaticle\CustomFields\Validation\Rules\DateConstraintRule;

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
        return DateConstraintField::make("{$statePath}.min_date", 'Minimum Date', 'min');
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $constraintValue = DateConstraintValue::from($value);

        $component->minDate(fn (Get $get, ?Model $record = null) => $constraintValue->resolve(
            getCallback: fn (string $path): mixed => $get($path),
            record: $record,
        ));
    }

    /** @return array<int, mixed> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return [new DateConstraintRule(DateConstraintValue::from($value), 'after_or_equal')];
    }
}
