<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Closure;
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
     * Lower-priority resolver an integration layer supplies, consulted after the Context.
     *
     * @var Closure(): (int|string|null)|null
     */
    private static ?Closure $fallbackTenantResolver = null;

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
     * Register the resolver consulted when neither the custom resolver nor the Context names a tenant.
     *
     * @param  Closure(): (int|string|null)  $callback
     */
    public static function setFallbackTenantResolver(Closure $callback): void
    {
        self::$fallbackTenantResolver = $callback;
    }

    /**
     * Clear the custom tenant resolver.
     */
    public static function clearTenantResolver(): void
    {
        self::$tenantResolver = null;
    }

    /**
     * Get the current tenant ID from the custom resolver, the Context, or the fallback resolver.
     * This works in both web requests and queue jobs.
     *
     * Resolution order:
     * 1. Custom resolver (if registered)
     * 2. Laravel Context (works in queues)
     * 3. Fallback resolver (the Filament panel tenant, registered by the service provider)
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

        // Third priority: fallback resolver (Filament tenant in web requests)
        return self::$fallbackTenantResolver instanceof Closure
            ? (self::$fallbackTenantResolver)()
            : null;
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
