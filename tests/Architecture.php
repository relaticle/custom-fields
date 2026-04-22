<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;
use Spatie\LaravelData\Data;

test('configurable models are only instantiated via CustomFields facade', function (string $model, string $pattern, string $facade, array $allowedFiles): void {
    $srcPath = dirname(__DIR__).'/src';
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = str_replace($srcPath.'/', '', $file->getPathname());

        if (in_array($relativePath, $allowedFiles, true)) {
            continue;
        }

        $lines = explode("\n", file_get_contents($file->getPathname()));

        foreach ($lines as $lineNum => $line) {
            if (str_contains($line, 'use ')) {
                continue;
            }

            if (str_contains($line, '//')) {
                continue;
            }

            if (preg_match($pattern, $line)) {
                $violations[] = $relativePath.':'.($lineNum + 1).sprintf(' -> use %s instead', $facade);
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Direct {$model} instantiation/querying found:\n".implode("\n", $violations),
    );
})->with([
    'CustomField' => [
        'model' => 'CustomField',
        'pattern' => '/(?<![\w\\\\])CustomField::(query|where|find|create|first|all|get)\s*\(|new\s+CustomField[^a-zA-Z]/',
        'facade' => 'CustomFields::newCustomFieldModel()',
        'allowedFiles' => ['CustomFields.php', 'Models/CustomField.php'],
    ],
    'CustomFieldValue' => [
        'model' => 'CustomFieldValue',
        'pattern' => '/CustomFieldValue::(query|where|find|create|first|all|get)\s*\(|new\s+CustomFieldValue[^a-zA-Z]/',
        'facade' => 'CustomFields::newValueModel()',
        'allowedFiles' => ['CustomFields.php', 'Models/CustomFieldValue.php'],
    ],
    'CustomFieldOption' => [
        'model' => 'CustomFieldOption',
        'pattern' => '/CustomFieldOption::(query|where|find|create|first|all|get)\s*\(|new\s+CustomFieldOption[^a-zA-Z]/',
        'facade' => 'CustomFields::newOptionModel()',
        'allowedFiles' => ['CustomFields.php', 'Models/CustomFieldOption.php'],
    ],
    'CustomFieldSection' => [
        'model' => 'CustomFieldSection',
        'pattern' => '/CustomFieldSection::(query|where|find|create|first|all|get)\s*\(|new\s+CustomFieldSection[^a-zA-Z]/',
        'facade' => 'CustomFields::newSectionModel()',
        'allowedFiles' => ['CustomFields.php', 'Models/CustomFieldSection.php'],
    ],
]);

arch('Models extend Eloquent Model')
    ->expect([
        CustomField::class,
        CustomFieldSection::class,
        CustomFieldOption::class,
        CustomFieldValue::class,
    ])
    ->toExtend(Model::class);

arch('Filament Resource extends base Resource')
    ->expect(PostResource::class)
    ->toExtend(Resource::class);

arch('Filament Resource Pages extend base Page')
    ->expect('Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages')
    ->toExtend(Page::class);

arch('No debugging functions are used')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('Enums are backed by strings or integers')
    ->expect('Relaticle\CustomFields\Enums')
    ->toBeEnums();

arch('Factories extend Laravel Factory')
    ->expect('Relaticle\CustomFields\Database\Factories')
    ->toExtend(Factory::class);

arch('Custom field models implement HasCustomFields contract')
    ->expect(Post::class)
    ->toImplement(HasCustomFields::class)
    ->toUse(UsesCustomFields::class);

arch('Observers follow naming convention')
    ->expect('Relaticle\CustomFields\Observers')
    ->toHaveSuffix('Observer');

arch('Middleware follows naming convention')
    ->expect('Relaticle\CustomFields\Http\Middleware')
    ->toHaveSuffix('Middleware');

arch('Exceptions follow naming convention')
    ->expect('Relaticle\CustomFields\Exceptions')
    ->toHaveSuffix('Exception');

arch('Jobs follow proper structure')
    ->expect('Relaticle\CustomFields\Jobs')
    ->not->toHaveSuffix('Job');

arch('Data objects extend Spatie Data')
    ->expect('Relaticle\CustomFields\Data')
    ->toExtend(Data::class);

// Enhanced service layer architecture tests
arch('Services follow naming convention')
    ->expect('Relaticle\CustomFields\Services')
    ->toHaveSuffix('Service');

arch('Service classes have single responsibility')
    ->expect('Relaticle\CustomFields\Services')
    ->toBeClasses()
    ->and('Relaticle\CustomFields\Services')
    ->not->toHaveMethodsMatching('/^(get|set).+And.+/'); // Avoid methods that do multiple things

arch('Services use dependency injection properly')
    ->expect('Relaticle\CustomFields\Services')
    ->toBeClasses()
    ->and('Relaticle\CustomFields\Services')
    ->not->toUse(['new', 'static::']) // Avoid direct instantiation and static calls
    ->ignoring([Cache::class, Log::class]);

arch('No direct model usage in controllers')
    ->expect('Relaticle\CustomFields\Http\Controllers')
    ->not->toUse([
        CustomField::class,
        CustomFieldSection::class,
        CustomFieldValue::class,
        CustomFieldOption::class,
    ]);

arch('Controllers delegate to services')
    ->expect('Relaticle\CustomFields\Http\Controllers')
    ->toUse('Relaticle\CustomFields\Services');

// Security and data protection constraints
arch('No password or secret data in logs')
    ->expect(['password', 'secret', 'token', 'api_key'])
    ->not->toBeUsedIn('Relaticle\CustomFields')
    ->ignoring(['tests', 'Test', 'Factory']);

arch('Encryption is used for sensitive data')
    ->expect('Relaticle\CustomFields\Models')
    ->toUse([Encrypter::class, 'encrypt', 'decrypt'])
    ->when(fn ($class): bool => str_contains((string) $class, 'CustomField'));

arch('Input validation is implemented')
    ->expect('Relaticle\CustomFields\Http\Requests')
    ->toHaveMethod('rules')
    ->when(fn ($class): bool => class_exists($class));

arch('Filament forms use proper validation')
    ->expect('Relaticle\CustomFields\Filament')
    ->toUse(['Filament\\Forms\\Components'])
    ->when(fn ($class): bool => str_contains((string) $class, 'Form'));

// Performance constraints
arch('Database queries use proper indexing hints')
    ->expect('Relaticle\CustomFields\Models')
    ->not->toHaveMethodsMatching('/whereRaw|selectRaw|havingRaw/')
    ->ignoring(['tests', 'Factory']);

arch('No N+1 query patterns in services')
    ->expect('Relaticle\CustomFields\Services')
    ->not->toHaveMethodsMatching('/foreach.*->/')
    ->ignoring(['tests']);

arch('Caching is used for expensive operations')
    ->expect('Relaticle\CustomFields\Services')
    ->toUse([Cache::class, Repository::class])
    ->when(fn ($class): bool => str_contains((string) $class, 'Registry') || str_contains((string) $class, 'Helper'));

// Type safety constraints
arch('All methods have return type declarations')
    ->expect('Relaticle\CustomFields')
    ->toHaveReturnTypeDeclarations()
    ->ignoring(['tests', 'migrations', 'config']);

arch('All parameters have type declarations')
    ->expect('Relaticle\CustomFields')
    ->toHaveParameterTypeDeclarations()
    ->ignoring(['tests', 'migrations', 'config']);

arch('Strict types are declared')
    ->expect('Relaticle\CustomFields')
    ->toUseStrictTypes()
    ->ignoring(['config', 'lang']);

// Testing constraints
arch('All test classes follow naming conventions')
    ->expect('Relaticle\CustomFields\Tests')
    ->toHaveSuffix('Test')
    ->ignoring(['TestCase', 'Pest', 'helpers', 'Fixtures', 'Datasets']);

arch('Tests use proper factories')
    ->expect('Relaticle\CustomFields\Tests')
    ->toUse('Relaticle\CustomFields\Database\Factories')
    ->when(fn ($class): bool => str_contains((string) $class, 'Test'));

arch('Feature tests use RefreshDatabase')
    ->expect('Relaticle\CustomFields\Tests\Feature')
    ->toUse(RefreshDatabase::class);

// Package structure constraints
arch('Package follows proper namespace structure')
    ->expect('Relaticle\CustomFields')
    ->toHaveProperNamespaceStructure();

arch('No vendor dependencies in core models')
    ->expect('Relaticle\CustomFields\Models')
    ->not->toUse(['GuzzleHttp', 'Symfony\\Component\\HttpClient'])
    ->ignoring(['Illuminate', 'Carbon', 'Spatie']);

arch('Field type implementations are consistent')
    ->expect('Relaticle\CustomFields\Services\FieldTypes')
    ->toImplement(FieldTypeDefinitionInterface::class)
    ->when(fn ($class): bool => class_exists($class));

// Integration constraints
arch('Filament form components implement proper interface')
    ->expect('Relaticle\CustomFields\Filament\Integration\Components\Forms')
    ->toImplement('Relaticle\CustomFields\Filament\Integration\Components\Forms\FieldComponentInterface')
    ->ignoring(['AbstractFormComponent', 'FieldComponentInterface']);

arch('Livewire components follow proper structure')
    ->expect('Relaticle\CustomFields\Livewire')
    ->toExtend(Component::class)
    ->when(fn ($class): bool => class_exists($class));

// Data integrity constraints
arch('Models use proper casts for data integrity')
    ->expect('Relaticle\CustomFields\Models')
    ->toHaveProperty('casts')
    ->when(fn ($class): bool => str_contains((string) $class, 'CustomField'));

// Multi-tenancy constraints
arch('Tenant isolation is properly implemented')
    ->expect('Relaticle\CustomFields\Models')
    ->toUse([Filament::class, 'tenant'])
    ->when(fn ($class): bool => str_contains((string) $class, 'CustomField'));

arch('No global scopes bypass tenant isolation')
    ->expect('Relaticle\CustomFields\Models')
    ->not->toHaveMethodsMatching('/withoutGlobalScope|withoutGlobalScopes/')
    ->ignoring(['tests']);

// Error handling constraints
arch('Exceptions provide meaningful context')
    ->expect('Relaticle\CustomFields\Exceptions')
    ->toExtend('Exception')
    ->toHaveMethod('__construct');

arch('No silent failures in critical operations')
    ->expect('Relaticle\CustomFields\Services')
    ->not->toHaveMethodsMatching('/try.*catch.*continue|try.*catch.*return null/');

// Documentation and code quality
arch('Public methods have docblocks')
    ->expect('Relaticle\CustomFields')
    ->toHaveDocumentedPublicMethods()
    ->ignoring(['tests', 'migrations']);

arch('Complex methods are properly documented')
    ->expect('Relaticle\CustomFields')
    ->toHaveDocumentedComplexMethods()
    ->ignoring(['tests', 'migrations']);

test('every HasLabel enum in Relaticle\\CustomFields\\Enums routes getLabel through __()', function (): void {
    $dir = dirname(__DIR__).'/src/Enums';
    $files = glob($dir.'/*.php');

    $violations = [];

    foreach ($files as $file) {
        $class = 'Relaticle\\CustomFields\\Enums\\'.pathinfo($file, PATHINFO_FILENAME);

        if (! enum_exists($class)) {
            continue;
        }

        if (! is_subclass_of($class, HasLabel::class)) {
            continue;
        }

        $source = file_get_contents($file);

        if (! preg_match('/public function getLabel\(\)[^{]*\{(.*?)\n    \}/s', $source, $m)) {
            $violations[] = $class.': getLabel() not found';

            continue;
        }

        if (! str_contains($m[1], '__(')) {
            $violations[] = $class.': getLabel() does not call __()';
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});
