<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Contracts\ValidationCapability;

final readonly class AcceptedFileTypesCapability implements ValidationCapability
{
    public function key(): string
    {
        return 'accepted_types';
    }

    public function label(): string
    {
        return 'Accepted File Types';
    }

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return [
            TagsInput::make($statePath.'.accepted_types')
                ->label('Accepted File Types')
                ->placeholder('e.g. image/png, application/pdf'),
        ];
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        $types = (array) $value;

        if ($types === []) {
            return;
        }

        $component->acceptedFileTypes($types); // @phpstan-ignore method.notFound
    }

    /** @return array<int, string> */
    public function toRules(mixed $value): array
    {
        return [];
    }
}
