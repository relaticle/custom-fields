<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Context;

final class TenantContextService
{
    private const string TENANT_ID_KEY = 'custom_fields_tenant_id';

    /**
     * Custom tenant resolver callback.
     *
     * @var Closure(): (int|string|null)|null
     */
    private static ?Closure $tenantResolver = null;

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
     * Register a custom tenant resolver.
     *
     * @param  Closure(): (int|string|null)  $callback
     */
    public static function setTenantResolver(Closure $callback): void
    {
        self::$tenantResolver = $callback;
    }

    /**
     * Clear the custom tenant resolver.
     */
    public static function clearTenantResolver(): void
    {
        self::$tenantResolver = null;
    }

    /**
     * Get the current tenant ID from custom resolver, context, or Filament.
     * This works in both web requests and queue jobs.
     *
     * Resolution order:
     * 1. Custom resolver (if registered)
     * 2. Laravel Context (works in queues)
     * 3. Filament tenant (works in web requests)
     * 4. null (no tenant)
     */
    public static function getCurrentTenantId(): null|int|string
    {
        // First priority: custom resolver
        if (self::$tenantResolver instanceof Closure) {
            return (self::$tenantResolver)();
        }

        // Second priority: Laravel Context (works in queues)
        $contextTenantId = Context::getHidden(self::TENANT_ID_KEY);
        if ($contextTenantId !== null) {
            return $contextTenantId;
        }

        // Third priority: Filament tenant (works in web requests)
        $filamentTenant = Filament::getTenant();

        return $filamentTenant?->getKey();
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
