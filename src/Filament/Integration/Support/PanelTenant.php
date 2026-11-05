<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support;

use Filament\Facades\Filament;
use Relaticle\CustomFields\Services\TenantContextService;

final class PanelTenant
{
    public static function id(): int|string|null
    {
        return Filament::getTenant()?->getKey();
    }

    public static function rememberInContext(): void
    {
        $tenantId = self::id();

        if ($tenantId !== null) {
            TenantContextService::setTenantId($tenantId);
        }
    }
}
