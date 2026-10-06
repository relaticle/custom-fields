<?php

declare(strict_types=1);

use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Contracts\FormComponentInterface;
use Relaticle\CustomFields\CustomFieldsPlugin;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\Filament\Integration\Builders\BaseBuilder;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\DateTimeColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\IconColumn;
use Relaticle\CustomFields\Filament\Integration\Factories\AbstractComponentFactory;
use Relaticle\CustomFields\Filament\Integration\Migrations\CustomFieldsMigration;
use Relaticle\CustomFields\Filament\Management\Pages\CustomFieldsManagementPage;
use Relaticle\CustomFields\Filament\Management\Schemas\FormInterface;
use Relaticle\CustomFields\Filament\Management\Schemas\SectionFormInterface;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Models\Scopes\ActivableScope;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use Relaticle\CustomFields\QueryBuilders\CustomFieldQueryBuilder;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;
use Relaticle\CustomFields\Tests\TestCase;
use Relaticle\CustomFields\Validation\Capabilities\AbstractDateCapability;
use Spatie\LaravelData\Data;

test('configurable models are only instantiated via CustomFields facade', function (string $model, string $pattern, string $facade, array $allowedFiles): void {
    $srcPath = __DIR__.'/../src';
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
    'CustomFieldRelationship' => [
        'model' => 'CustomFieldRelationship',
        'pattern' => '/CustomFieldRelationship::(query|where|find|create|first|all|get)\s*\(|new\s+CustomFieldRelationship[^a-zA-Z]/',
        'facade' => 'CustomFields::newRelationshipModel()',
        'allowedFiles' => ['CustomFields.php', 'Models/CustomFieldRelationship.php'],
    ],
    'CustomFieldLink' => [
        'model' => 'CustomFieldLink',
        'pattern' => '/CustomFieldLink::(query|where|find|create|first|all|get)\s*\(|new\s+CustomFieldLink[^a-zA-Z]/',
        'facade' => 'CustomFields::newLinkModel()',
        'allowedFiles' => ['CustomFields.php', 'Models/CustomFieldLink.php'],
    ],
]);

arch('Models extend Eloquent Model')
    ->expect([
        CustomField::class,
        CustomFieldSection::class,
        CustomFieldOption::class,
        CustomFieldValue::class,
        CustomFieldRelationship::class,
        CustomFieldLink::class,
    ])
    ->toExtend(Model::class);

test('custom field models are scoped by the tenant scope', function (string $model): void {
    $attributes = (new ReflectionClass($model))->getAttributes(ScopedBy::class);

    expect($attributes)->not->toBeEmpty($model.' carries no ScopedBy attribute.');

    $scopes = array_merge(...array_map(
        fn (ReflectionAttribute $attribute): array => (array) ($attribute->getArguments()[0] ?? []),
        $attributes,
    ));

    expect($scopes)->toContain(TenantScope::class);
})->with([
    CustomField::class,
    CustomFieldSection::class,
    CustomFieldOption::class,
    CustomFieldValue::class,
    CustomFieldRelationship::class,
    CustomFieldLink::class,
]);

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

arch('Data objects extend Spatie Data')
    ->expect('Relaticle\CustomFields\Data')
    ->toExtend(Data::class);

arch('Field type definitions implement the field type interface')
    ->expect('Relaticle\CustomFields\FieldTypeSystem\Definitions')
    ->toImplement(FieldTypeDefinitionInterface::class);

arch('Filament form components implement the shared form component interface')
    ->expect('Relaticle\CustomFields\Filament\Integration\Components\Forms')
    ->toImplement(FormComponentInterface::class)
    ->ignoring([
        'Relaticle\CustomFields\Filament\Integration\Components\Forms\PhoneInput',
        'Relaticle\CustomFields\Filament\Integration\Components\Forms\MultiValueInput',
        'Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectInput',
        'Relaticle\CustomFields\Filament\Integration\Components\Forms\RelationshipPicker',
    ]);

arch('Livewire components extend the base Component class')
    ->expect('Relaticle\CustomFields\Livewire')
    ->toExtend(Component::class)
    ->ignoring(['Relaticle\CustomFields\Livewire\Concerns']);

arch('No vendor dependencies in core models')
    ->expect('Relaticle\CustomFields\Models')
    ->not->toUse(['GuzzleHttp', 'Symfony\\Component\\HttpClient'])
    ->ignoring(['Illuminate', 'Carbon', 'Spatie']);

arch('Strict types are declared')
    ->expect('Relaticle\CustomFields')
    ->toUseStrictTypes();

// The arch rule above only reaches classes, so it never sees the tests, the config file,
// the stubs, or a migration; those are exactly the files that keep losing the declaration.
test('every PHP file outside the source tree declares strict types', function (): void {
    $root = dirname(__DIR__);

    $files = [
        ...glob($root.'/config/*.php'),
        ...glob($root.'/stubs/*.stub'),
    ];

    foreach ([$root.'/tests', $root.'/database'] as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            // A Blade template ends in .php and can carry no declaration of its own.
            if ($file->getExtension() === 'php' && ! str_ends_with($file->getBasename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    $violations = [];

    foreach ($files as $file) {
        $tokens = array_values(array_filter(
            token_get_all(file_get_contents($file)),
            fn (array|string $token): bool => ! is_array($token)
                || ! in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        $first = $tokens[0] ?? null;

        if (is_array($first) && $first[0] === T_DECLARE && str_contains($tokens[2][1] ?? '', 'strict_types')) {
            continue;
        }

        $violations[] = str_replace($root.'/', '', $file);
    }

    expect($violations)->toBeEmpty(
        "Files without declare(strict_types=1) as their first statement:\n".implode("\n", $violations),
    );
});

// Two kinds of entry are ignored: things that cannot carry the keyword (interfaces, traits,
// abstract bases, enums) and the seams documented in
// docs/content/2.essentials/8.extending.md. Opening a class means adding it there too.
arch('Classes are final outside the documented extension points')
    ->expect('Relaticle\CustomFields')
    ->toBeFinal()
    ->ignoring([
        'Relaticle\CustomFields\Concerns',
        UpgradeStep::class,
        'Relaticle\CustomFields\Contracts',
        CustomFieldsPlugin::class,
        'Relaticle\CustomFields\Enums',
        BaseFieldType::class,
        'Relaticle\CustomFields\FieldTypeSystem\Concerns',
        'Relaticle\CustomFields\FieldTypeSystem\Definitions',
        'Relaticle\CustomFields\Filament\Integration\Base',
        BaseBuilder::class,
        'Relaticle\CustomFields\Filament\Integration\Components\Forms\MultiValueInput',
        'Relaticle\CustomFields\Filament\Integration\Components\Forms\PhoneInput',
        'Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectInput',
        DateTimeColumn::class,
        IconColumn::class,
        'Relaticle\CustomFields\Filament\Integration\Builders\Concerns',
        'Relaticle\CustomFields\Filament\Integration\Concerns',
        AbstractComponentFactory::class,
        'Relaticle\CustomFields\Filament\Integration\Factories\Concerns',
        CustomFieldsMigration::class,
        CustomFieldsManagementPage::class,
        FormInterface::class,
        SectionFormInterface::class,
        'Relaticle\CustomFields\Jobs\Concerns',
        'Relaticle\CustomFields\Livewire\Concerns',
        'Relaticle\CustomFields\Models\Concerns',
        'Relaticle\CustomFields\Models\Contracts',
        CustomField::class,
        CustomFieldRelationship::class,
        CustomFieldLink::class,
        ActivableScope::class,
        CustomFieldQueryBuilder::class,
        AbstractDateCapability::class,
    ]);

arch('All test classes follow naming conventions')
    ->expect('Relaticle\CustomFields\Tests')
    ->toHaveSuffix('Test')
    ->ignoring([
        TestCase::class,
        'Relaticle\CustomFields\Tests\Fixtures',
        'Relaticle\CustomFields\Tests\Datasets',
        'Relaticle\CustomFields\Tests\Database\Factories',
    ]);

arch('Exceptions extend the base exception')
    ->expect('Relaticle\CustomFields\Exceptions')
    ->toExtend('Exception');

test('every HasLabel enum in Relaticle\\CustomFields\\Enums routes getLabel through __()', function (): void {
    $dir = __DIR__.'/../src/Enums';
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

test('every Action::make() in src/Livewire has a translated ->label()', function (): void {
    $dir = __DIR__.'/../src/Livewire';
    $files = glob($dir.'/*.php');

    $violations = [];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        // Capture each `Action::make(...)` call plus its chained method calls up to the terminating `;`.
        if (! preg_match_all('/(Action|BulkAction|TestAction)::make\([^)]+\).*?(?=\s*;|\)\s*,)/s', $source, $matches)) {
            continue;
        }

        foreach ($matches[0] as $chain) {
            if (! preg_match('/->label\(\s*__\(/', $chain)) {
                $violations[] = basename($file).': Action::make() without ->label(__()): '.substr(preg_replace('/\s+/', ' ', $chain), 0, 120);
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

/**
 * Two classes in one file compile only while the parent of the first is already loaded:
 * autoloading it cold makes PHP resolve a return type declared before the class carrying
 * it, and the process dies with "Could not check compatibility".
 */
test('every source file declares exactly one type, named after the file', function (): void {
    $srcPath = __DIR__.'/../src';
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $declared = declaredTypeNames($file->getPathname());
        $relativePath = str_replace($srcPath.'/', '', $file->getPathname());

        if (count($declared) !== 1) {
            $violations[] = $relativePath.' declares '.($declared === [] ? 'no type' : implode(', ', $declared));

            continue;
        }

        if ($declared[0] !== $file->getBasename('.php')) {
            $violations[] = $relativePath.' declares '.$declared[0];
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

/**
 * @return array<int, string>
 */
function declaredTypeNames(string $path): array
{
    $tokens = token_get_all((string) file_get_contents($path));
    $names = [];

    foreach ($tokens as $index => $token) {
        if (! is_array($token)) {
            continue;
        }

        if (! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            continue;
        }

        $twoBack = $tokens[$index - 2] ?? null;
        $previous = $tokens[$index - 1] ?? null;

        // `new class` declares nothing importable, and `Foo::class` is not a declaration.
        if (is_array($twoBack) && $twoBack[0] === T_NEW) {
            continue;
        }

        if (is_array($previous) && $previous[0] === T_DOUBLE_COLON) {
            continue;
        }

        $next = $index + 1;

        while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $next++;
        }

        if (isset($tokens[$next]) && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
            $names[] = $tokens[$next][1];
        }
    }

    return $names;
}
