<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Schemas;

use Closure;
use Filament\Schemas\Components\Component;

interface SectionFormInterface
{
    public static function entityType(string $entityType): self;

    /**
     * @param  Closure(array<int, Component>, string): array<int, Component>  $callback
     */
    public static function extendSchemaUsing(Closure $callback): void;
}
