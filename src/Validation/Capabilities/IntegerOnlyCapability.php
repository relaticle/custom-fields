<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Toggle;
use Relaticle\CustomFields\Contracts\ValidationCapability;

final readonly class IntegerOnlyCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'integer_only';
    }

    public function label(): string
    {
        return 'Integer Only';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return [
            Toggle::make("{$statePath}.integer_only")
                ->label('Integer Only'),
        ];
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ((bool) $value !== true) {
            return;
        }

        $component->integer();
    }

    /** @return array<int, string> */
    public function toRules(mixed $value): array
    {
        if ((bool) $value !== true) {
            return [];
        }

        return ['integer'];
    }
}
