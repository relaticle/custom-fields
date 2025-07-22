<?php

declare(strict_types=1);

namespace Relaticle\CustomFields;

use Filament\Facades\Filament;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Livewire\Livewire;
use Relaticle\CustomFields\Contracts\CustomsFieldsMigrators;
use Relaticle\CustomFields\Contracts\ValueResolvers;
use Relaticle\CustomFields\Filament\Integration\Migrations\CustomFieldsMigrator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Livewire\ManageCustomFieldWidth;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Providers\EntityServiceProvider;
use Relaticle\CustomFields\Providers\FieldTypeServiceProvider;
use Relaticle\CustomFields\Providers\ImportsServiceProvider;
use Relaticle\CustomFields\Providers\ValidationServiceProvider;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Services\ValueResolver\ValueResolver;
use Relaticle\CustomFields\Support\Utils;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CustomFieldsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'custom-fields';

    public static string $viewNamespace = 'custom-fields';

    public function bootingPackage(): void
    {
        $this->app->register(FieldTypeServiceProvider::class);
        $this->app->register(ImportsServiceProvider::class);
        $this->app->register(ValidationServiceProvider::class);
        $this->app->register(EntityServiceProvider::class);

        $this->app->singleton(CustomsFieldsMigrators::class, CustomFieldsMigrator::class);
        $this->app->singleton(ValueResolvers::class, ValueResolver::class);

        $this->app->singleton(TenantContextService::class);

        if (Utils::isTenantEnabled()) {
            foreach (Filament::getPanels() as $panel) {
                $tenantModel = $panel->getTenantModel();
                if ($tenantModel !== null) {
                    $tenantModelInstance = app($tenantModel);

                    CustomFieldSection::resolveRelationUsing('team', fn (CustomField $customField) => $customField->belongsTo($tenantModel, config('custom-fields.column_names.tenant_foreign_key')));

                    CustomField::resolveRelationUsing('team', fn (CustomField $customField) => $customField->belongsTo($tenantModel, config('custom-fields.column_names.tenant_foreign_key')));

                    $tenantModelInstance->resolveRelationUsing('customFields', fn (Model $tenantModel) => $tenantModel->hasMany(CustomField::class, config('custom-fields.column_names.tenant_foreign_key')));
                }
            }
        }

        Livewire::component('manage-custom-field-section', ManageCustomFieldSection::class);
        Livewire::component('manage-custom-field', ManageCustomField::class);
        Livewire::component('manage-custom-field-width', ManageCustomFieldWidth::class);
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->endWith(function (Command $command): void {
                        $command->newLine();
                        $command->warn('⚠️ Commercial/closed projects require a Commercial License');
                        $command->info('📄 Open source projects use AGPL-3.0');
                        $command->info('https://custom-fields.relaticle.com/legal-acknowledgments/license');
                        $command->newLine(2);
                    });
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath(sprintf('/../config/%s.php', $configFileName)))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__.'/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path('stubs/custom-fields/'.$file->getFilename()),
                ], 'custom-fields-stubs');
            }
        }
    }

    protected function getAssetPackageName(): ?string
    {
        return 'relaticle/custom-fields';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('custom-fields', __DIR__ . '/../resources/dist/components/custom-fields.js'),
            Css::make('custom-fields', __DIR__.'/../resources/dist/custom-fields.css')->loadedOnRequest(),
            // Js::make('custom-fields-scripts', __DIR__ . '/../resources/dist/custom-fields.js'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_custom_fields_table',
        ];
    }
}
