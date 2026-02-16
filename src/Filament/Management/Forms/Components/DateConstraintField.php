<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Relaticle\CustomFields\Enums\DateConstraintMode;
use Relaticle\CustomFields\Enums\DateUnit;

final class DateConstraintField
{
    /** @return array<int, Component> */
    public static function make(string $statePath, string $label): array
    {
        return [
            Fieldset::make($label)
                ->schema([
                    ToggleButtons::make("{$statePath}.mode")
                        ->options(DateConstraintMode::class)
                        ->inline()
                        ->live()
                        ->default(null)
                        ->columnSpanFull(),

                    DatePicker::make("{$statePath}.absolute_value")
                        ->label('Date')
                        ->visible(fn (Get $get): bool => $get("{$statePath}.mode") === DateConstraintMode::Absolute->value),

                    TextInput::make("{$statePath}.relative_value")
                        ->numeric()
                        ->integer()
                        ->label('Value')
                        ->visible(fn (Get $get): bool => $get("{$statePath}.mode") === DateConstraintMode::Relative->value),

                    Select::make("{$statePath}.relative_unit")
                        ->options(DateUnit::class)
                        ->label('Unit')
                        ->visible(fn (Get $get): bool => $get("{$statePath}.mode") === DateConstraintMode::Relative->value),
                ])
                ->columns(2),
        ];
    }
}
