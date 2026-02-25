<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Contracts\ValidationCapability;

final readonly class DecimalPlacesCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'decimal_places';
    }

    public function label(): string
    {
        return 'Decimal Places';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return [
            TextInput::make($statePath.'.decimal_places')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(4)
                ->placeholder('Enter a value between 0 and 4')
                ->label('Decimal Places'),
        ];
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $decimalPlaces = max(0, min(4, (int) $value));
        $component->step($this->resolveStep($decimalPlaces)); // @phpstan-ignore method.notFound
    }

    /** @return array<int, string> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $decimalPlaces = max(0, min(4, (int) $value));

        if ($decimalPlaces === 0) {
            return ['integer'];
        }

        return ['decimal:0,'.$decimalPlaces];
    }

    private function resolveStep(int $decimalPlaces): int|float
    {
        if ($decimalPlaces === 0) {
            return 1;
        }

        return 1 / (10 ** $decimalPlaces);
    }
}
