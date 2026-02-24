<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Contracts\ValidationCapability;

final readonly class MinLengthCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'min_length';
    }

    public function label(): string
    {
        return 'Minimum Length';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return [
            TextInput::make("{$statePath}.min_length")
                ->numeric()
                ->integer()
                ->minValue(0)
                ->label('Minimum Length'),
        ];
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $component->minLength((int) $value); // @phpstan-ignore method.notFound
    }

    /** @return array<int, string> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return ['min:'.(int) $value];
    }
}
