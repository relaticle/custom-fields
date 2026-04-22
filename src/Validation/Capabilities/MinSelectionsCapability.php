<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Contracts\ValidationCapability;

final readonly class MinSelectionsCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'min_selections';
    }

    public function label(): string
    {
        return __('custom-fields::custom-fields.validation.min_selections');
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return [
            TextInput::make($statePath.'.min_selections')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->label(__('custom-fields::custom-fields.validation.min_selections')),
        ];
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $component->minItems((int) $value); // @phpstan-ignore method.notFound
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
