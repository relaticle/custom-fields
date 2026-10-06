<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;
use Postare\BladeMdi\BladeMdiServiceProvider;
use Propaganistas\LaravelPhone\PhoneServiceProvider;
use Relaticle\CustomFields\CustomFieldsServiceProvider;
use Relaticle\CustomFields\EntitySystem\EntityConfigurator;
use Relaticle\CustomFields\EntitySystem\EntityManager;
use Relaticle\CustomFields\EntitySystem\EntityModel;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Enums\UiFlavor;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Tests\Database\Factories\TagFactory;
use Relaticle\CustomFields\Tests\database\factories\UserFactory;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Providers\AdminPanelProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

class TestCase extends BaseTestCase
{
    use WithWorkbench;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Clear booted models to ensure event listeners are properly registered
        // This fixes an issue where models booted during test environment setup
        // don't have their Eloquent events properly wired to the event dispatcher
        Post::clearBootedModels();
        User::clearBootedModels();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => match ($modelName) {
                Tag::class => TagFactory::class,
                User::class => UserFactory::class,
                default => 'Relaticle\\CustomFields\\Database\\Factories\\'.class_basename($modelName).'Factory'
            }
        );

        $this->actingAs(User::factory()->create());
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeMdiServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            PhoneServiceProvider::class,
            PowerJoinsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,

            // Custom service provider for the custom fields package
            LaravelDataServiceProvider::class,

            // Custom service provider for the admin panel
            AdminPanelProvider::class,

            // Custom fields service provider
            CustomFieldsServiceProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('view.paths', [
            ...$app['config']->get('view.paths'),
            __DIR__.'/../resources/views',
        ]);

        $this->configureDatabaseConnection();

        // Authentication configuration for testing
        config()->set('auth.providers.users.model', User::class);

        // The flavor is a run dimension, not a per-test one: CI runs the whole suite once per
        // flavor, so the forked surfaces are exercised in both presentations.
        config()->set('custom-fields.ui.flavor', env('CUSTOM_FIELDS_UI_FLAVOR', UiFlavor::Polished->value));

        // Custom fields configuration
        config()->set('custom-fields.database.table_names.custom_field_sections', 'custom_field_sections');
        config()->set('custom-fields.database.table_names.custom_fields', 'custom_fields');
        config()->set('custom-fields.database.table_names.custom_field_values', 'custom_field_values');
        config()->set('custom-fields.database.table_names.custom_field_options', 'custom_field_options');

        // Every flag is pinned, enabled or disabled, so the suite never rides the package
        // defaults an unlisted flag falls back to.
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(
                CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
                CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS,
                CustomFieldsFeature::UI_TABLE_COLUMNS,
                CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS,
                CustomFieldsFeature::UI_TABLE_FILTERS,
                CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
                CustomFieldsFeature::SYSTEM_SECTIONS,
                CustomFieldsFeature::SYSTEM_RELATIONSHIPS,
            )
            ->disable(
                CustomFieldsFeature::FIELD_ENCRYPTION,
                CustomFieldsFeature::FIELD_OPTION_COLORS,
                CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE,
                CustomFieldsFeature::FIELD_MULTI_VALUE,
                CustomFieldsFeature::FIELD_UNIQUE_VALUE,
                CustomFieldsFeature::FIELD_VALIDATION_RULES,
                CustomFieldsFeature::FIELD_DESCRIPTION,
                CustomFieldsFeature::FIELD_DESCRIPTION_POSITION,
                CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY,
                CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS_HIDDEN_DEFAULT,
                CustomFieldsFeature::UI_FIELD_WIDTH_CONTROL,
                CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL,
                CustomFieldsFeature::SYSTEM_MULTI_TENANCY,
            )
        );

        // Entity configuration for tests using the new builder
        $this->registerTestEntities();

        // Filament configuration
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Fix Spatie Laravel Data configuration for testing
        config()->set('data.throw_when_max_depth_reached', false);
        config()->set('data.max_transformation_depth');
        config()->set('data.validation_strategy', 'only_requests');
    }

    /**
     * Configure the `testing` connection from DB_CONNECTION (default sqlite in-memory).
     * pgsql/mysql read DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD, set via real
     * env vars or a phpunit.xml <env> block.
     */
    private function configureDatabaseConnection(): void
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        config()->set('database.default', 'testing');

        config()->set('database.connections.testing', match ($driver) {
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'custom_fields_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'custom_fields_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        // Load package migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load test migrations (like users table)
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function createTestModelTable(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('test_models', function ($table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Register the default test entities. The Comment entity declares a cross-record
     * relation path so RelationAttribute condition tests can resolve it via the registry.
     *
     * @param  array<class-string, array<string, string>>  $conditionRelationOverrides  modelClass => (path => label)
     */
    protected function registerTestEntities(array $conditionRelationOverrides = []): void
    {
        $commentRelations = $conditionRelationOverrides[Comment::class]
            ?? ['post.tagModels' => 'Post → Tags'];

        $postRelations = $conditionRelationOverrides[Post::class] ?? [];

        config()->set('custom-fields.entity_configuration',
            EntityConfigurator::configure()
                ->autoDiscover(false)
                ->cache(false)
                ->models([
                    EntityModel::configure(
                        modelClass: Post::class,
                        labelSingular: 'Post',
                        searchAttributes: ['title', 'content'],
                        features: [EntityFeature::CUSTOM_FIELDS, EntityFeature::LOOKUP_SOURCE],
                        conditionRelations: $postRelations,
                    ),
                    EntityModel::configure(
                        modelClass: Comment::class,
                        labelSingular: 'Comment',
                        features: [EntityFeature::CUSTOM_FIELDS, EntityFeature::LOOKUP_SOURCE],
                        conditionRelations: $commentRelations,
                    ),
                ])
        );
    }

    /**
     * Re-register test entities with custom cross-record relation paths and rebuild the registry.
     * Use inside a test to override the defaults set by defineEnvironment().
     *
     * @param  array<class-string, array<string, string>>  $conditionRelations  modelClass => (path => label)
     */
    protected function setEntityConditionRelations(array $conditionRelations): void
    {
        $this->registerTestEntities($conditionRelations);
        $this->refreshEntityManager();
    }

    /**
     * Forget the EntityManager singletons so the next resolution rebuilds from current config.
     */
    protected function refreshEntityManager(): void
    {
        $this->app->forgetInstance(EntityManager::class);
    }
}
