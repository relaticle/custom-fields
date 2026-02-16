<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Relaticle\CustomFields\Contracts\ValidationCapability;

final readonly class MaxFileSizeCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'max_size_kb';
    }

    public function label(): string
    {
        return 'Maximum File Size (KB)';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return [
            TextInput::make("{$statePath}.max_size_kb")
                ->numeric()
                ->integer()
                ->minValue(1)
                ->label('Maximum File Size (KB)'),
        ];
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $component->maxSize((int) $value);
    }

    /** @return array<int, string> */
    public function toRules(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return ['max:'.(int) $value];
    }
}
