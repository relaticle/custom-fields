<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Context;

class TenantContextService
{
    private const string TENANT_ID_KEY = 'custom_fields_tenant_id';

    /**
     * Set the tenant ID in the context.
     * This will persist across queue jobs and other async operations.
     */
    public static function setTenantId(null|int|string $tenantId): void
    {
        if ($tenantId !== null) {
            Context::addHidden(self::TENANT_ID_KEY, $tenantId);
        } else {
            Context::forgetHidden(self::TENANT_ID_KEY);
        }
    }

    /**
     * Get the current tenant ID from context or Filament.
     * This works in both web requests and queue jobs.
     */
    public static function getCurrentTenantId(): null|int|string
    {
        // First try to get tenant from Laravel Context (works in queues)
        $contextTenantId = Context::getHidden(self::TENANT_ID_KEY);
        if ($contextTenantId !== null) {
            return $contextTenantId;
        }

        // Fallback to Filament tenant (works in web requests)
        $filamentTenant = Filament::getTenant();
        if ($filamentTenant !== null) {
            return $filamentTenant->getKey();
        }

        return null;
    }

    /**
     * Set tenant context from Filament tenant.
     * This is useful for ensuring context is set from web requests.
     */
    public static function setFromFilamentTenant(): void
    {
        $tenant = Filament::getTenant();
        if ($tenant !== null) {
            self::setTenantId($tenant->getKey());
        }
    }

    /**
     * Execute a callback with a specific tenant context.
     */
    public static function withTenant(null|int|string $tenantId, callable $callback): mixed
    {
        $originalTenantId = self::getCurrentTenantId();

        try {
            self::setTenantId($tenantId);

            return $callback();
        } finally {
            self::setTenantId($originalTenantId);
        }
    }

    /**
     * Check if tenant context is available.
     */
    public static function hasTenantContext(): bool
    {
        return self::getCurrentTenantId() !== null;
    }
}
