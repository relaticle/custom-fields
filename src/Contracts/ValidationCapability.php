<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Contracts;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;

interface ValidationCapability
{
    public function key(): string;

    public function label(): string;

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array;

    public function applyToComponent(Field $component, mixed $value): void;

    /** @return array<int, string> */
    public function toRules(mixed $value): array;
}
