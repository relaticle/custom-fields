<?php

declare(strict_types=1);

// ABOUTME: Minimal service provider for custom fields import functionality
// ABOUTME: Registers only essential services with no unnecessary abstractions

namespace Relaticle\CustomFields\Providers;

use Illuminate\Support\ServiceProvider;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;

/**
 * Simplified service provider for custom fields import functionality.
 *
 * This provider has been dramatically simplified from the previous version:
 * - Removed unnecessary interfaces and abstractions
 * - No longer registers factories (created on-demand)
 * - Configurator is created when needed
 * - WeakMap storage is static and self-initializing
 */
class ImportsServiceProvider extends ServiceProvider
{
    /**
     * Register import services.
     *
     * We only register what absolutely needs to be in the container.
     * Everything else is created on-demand for better performance.
     */
    public function register(): void
    {
        // Register the unified configurator as a singleton
        // This ensures consistent behavior across all imports
        $this->app->singleton(ImportColumnConfigurator::class);

        // That's it! Everything else is handled internally or created on-demand:
        // - ImportDataStorage uses static WeakMap (self-initializing)
        // - ImporterBuilder creates its own configurator instance
        // - No need for factories, matchers, or converters
    }

    /**
     * Bootstrap import services.
     *
     * Currently no bootstrapping needed, but method kept for future extensions.
     */
    public function boot(): void
    {
        // No bootstrapping needed currently
    }
}
