<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Providers;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Relaticle\CustomFields\Filament\Integration\Actions\Imports\CustomFieldsImporter;
use Relaticle\CustomFields\Filament\Integration\Factories\ImportColumnFactory;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators\BasicColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators\MultiSelectColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators\SelectColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\Matchers\LookupMatcher;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\Matchers\LookupMatcherInterface;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ValueConverters\ValueConverter;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ValueConverters\ValueConverterInterface;

/**
 * Service provider for custom fields import functionality.
 */
class ImportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register implementations
        $this->app->singleton(LookupMatcherInterface::class, LookupMatcher::class);
        $this->app->singleton(ValueConverterInterface::class, ValueConverter::class);

        // Register column configurators
        $this->app->singleton(BasicColumnConfigurator::class);
        $this->app->singleton(SelectColumnConfigurator::class);
        $this->app->singleton(MultiSelectColumnConfigurator::class);

        // Register column factory
        $this->app->singleton(ImportColumnFactory::class);

        // Register the importer
        $this->app->singleton(CustomFieldsImporter::class, fn ($app): CustomFieldsImporter => new CustomFieldsImporter(
            $app->make(ImportColumnFactory::class),
            $app->make(ValueConverterInterface::class),
            $app->make(LoggerInterface::class)
        ));
    }
}
