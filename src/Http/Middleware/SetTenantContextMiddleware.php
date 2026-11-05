<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Integration\Support\PanelTenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final class SetTenantContextMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            PanelTenant::rememberInContext();
        }

        return $next($request);
    }
}
