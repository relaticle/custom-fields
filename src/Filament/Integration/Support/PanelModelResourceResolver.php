<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support;

use Filament\Facades\Filament;
use Relaticle\CustomFields\Contracts\ModelResourceResolverInterface;

/**
 * @internal
 */
final readonly class PanelModelResourceResolver implements ModelResourceResolverInterface
{
    public function resourceFor(string $modelClass): ?string
    {
        $resource = Filament::getModelResource($modelClass);

        return $resource !== null && class_exists($resource) ? $resource : null;
    }
}
