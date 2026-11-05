<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Jobs\Concerns;

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Services\TenantContextService;

/**
 * @internal
 */
trait TenantAware
{
    /**
     * The tenant ID for this job.
     */
    public null|int|string $tenantId = null;

    /**
     * Set the tenant context when dispatching the job.
     */
    public function withTenant(null|int|string $tenantId = null): static
    {
        $this->tenantId = $tenantId ?? TenantContextService::getCurrentTenantId();

        return $this;
    }

    /**
     * Handle the job with tenant context.
     */
    public function handleWithTenantContext(): void
    {
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY) && $this->tenantId !== null) {
            TenantContextService::withTenant($this->tenantId, function (): void {
                $this->handle();
            });
        } else {
            $this->handle();
        }
    }

    /**
     * Automatically set tenant context when job is being dispatched.
     */
    public function __construct()
    {
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $this->tenantId = TenantContextService::getCurrentTenantId();
        }
    }
}
