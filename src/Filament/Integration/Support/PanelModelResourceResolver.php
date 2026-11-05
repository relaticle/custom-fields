<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support;

use Filament\Facades\Filament;
use Relaticle\CustomFields\Contracts\ModelResourceResolverInterface;

final readonly class PanelModelResourceResolver implements ModelResourceResolverInterface
{
    public function resourceFor(string $modelClass): ?string
    {
        return Filament::getModelResource($modelClass);
    }
}
