<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Relaticle\CustomFields\Contracts\ValidationCapability;

final readonly class MaxValueCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'max_value';
    }

    public function label(): string
    {
        return 'Maximum Value';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return [
            TextInput::make("{$statePath}.max_value")
                ->numeric()
                ->label('Maximum Value'),
        ];
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $component->maxValue((float) $value);
    }

    /** @return array<int, string> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return ['max:'.(float) $value];
    }
}
