<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Contracts\ValidationCapability;

final readonly class MaxValueCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'max_value';
    }

    public function label(): string
    {
        return __('custom-fields::custom-fields.validation.max_value');
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return [
            TextInput::make($statePath.'.max_value')
                ->numeric()
                ->label(__('custom-fields::custom-fields.validation.max_value')),
        ];
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $component->maxValue((float) $value); // @phpstan-ignore method.notFound
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
