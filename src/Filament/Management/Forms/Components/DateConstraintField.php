<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;

final class DateConstraintField
{
    /** @return array<int, Component> */
    public static function make(string $statePath, string $label, string $context = 'min'): array
    {
        return [
            Fieldset::make($label)
                ->schema([
                    TextInput::make("{$statePath}.relative_value")
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->label('Value'),

                    Select::make("{$statePath}.relative_unit")
                        ->options(DateUnit::class)
                        ->default(DateUnit::Days->value)
                        ->afterStateHydrated(function ($component, $state): void {
                            if ($state === null) {
                                $component->state(DateUnit::Days->value);
                            }
                        })
                        ->label('Unit'),

                    Select::make("{$statePath}.direction")
                        ->options(DateOffsetDirection::class)
                        ->default(DateOffsetDirection::After->value)
                        ->afterStateHydrated(function ($component, $state): void {
                            if ($state === null) {
                                $component->state(DateOffsetDirection::After->value);
                            }
                        })
                        ->label('Direction'),
                ])
                ->columns(3),
        ];
    }
}
