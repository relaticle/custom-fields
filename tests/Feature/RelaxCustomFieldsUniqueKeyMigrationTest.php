<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

/**
 * loadMigrationsFrom() (see tests/TestCase.php) already ran this migration's up() once
 * during suite bootstrap, so every test here starts from the wide (post-up) index state.
 * RefreshDatabase wraps each test in a DB transaction, and SQLite's DDL is transactional,
 * so schema changes made in one test — including index drops/adds — never leak into the
 * next; each test is free to call up()/down() as many times as it needs.
 */
beforeEach(function (): void {
    $this->migration = require __DIR__.'/../../database/migrations/relax_custom_fields_unique_key.php';
    $this->table = config('custom-fields.database.table_names.custom_fields');
});

function customFieldsIndexNames(string $table): Collection
{
    return collect(Schema::getIndexes($table))->pluck('name');
}

it('down() restores the narrow key and drops the wide one', function (): void {
    expect(customFieldsIndexNames($this->table))
        ->toContain('cf_code_entity_section_unique')
        ->not->toContain('custom_fields_code_entity_type_unique');

    $this->migration->down();

    $indexes = customFieldsIndexNames($this->table);

    expect($indexes)
        ->toContain('custom_fields_code_entity_type_unique')
        ->not->toContain('cf_code_entity_section_unique');
});

it('up() restores the wide key after a down() round trip', function (): void {
    $this->migration->down();
    $this->migration->up();

    $indexes = customFieldsIndexNames($this->table);

    expect($indexes)
        ->toContain('cf_code_entity_section_unique')
        ->not->toContain('custom_fields_code_entity_type_unique');
});

it('up() is idempotent when the wide index already exists', function (): void {
    expect(fn () => $this->migration->up())->not->toThrow(Throwable::class);

    expect(customFieldsIndexNames($this->table)->filter(
        fn (string $name): bool => $name === 'cf_code_entity_section_unique'
    ))->toHaveCount(1);
});

it('up() is idempotent when the narrow index is already gone, the state a consumer who hand-applied an equivalent change is in', function (): void {
    Schema::table($this->table, fn (Blueprint $table) => $table->dropUnique('cf_code_entity_section_unique'));

    expect(customFieldsIndexNames($this->table))
        ->not->toContain('cf_code_entity_section_unique')
        ->not->toContain('custom_fields_code_entity_type_unique');

    expect(fn () => $this->migration->up())->not->toThrow(Throwable::class);

    expect(customFieldsIndexNames($this->table))->toContain('cf_code_entity_section_unique');
});

it('down() aborts before dropping anything when rows share a code across sections', function (): void {
    $sectionA = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'code' => 'section_a']);
    $sectionB = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'code' => 'section_b']);

    CustomField::factory()->create([
        'custom_field_section_id' => $sectionA->id,
        'entity_type' => Post::class,
        'code' => 'duplicate_code',
        'type' => 'text',
    ]);

    CustomField::factory()->create([
        'custom_field_section_id' => $sectionB->id,
        'entity_type' => Post::class,
        'code' => 'duplicate_code',
        'type' => 'text',
    ]);

    expect(fn () => $this->migration->down())
        ->toThrow(RuntimeException::class, 'duplicate_code');

    $indexes = customFieldsIndexNames($this->table);

    expect($indexes)
        ->toContain('cf_code_entity_section_unique')
        ->not->toContain('custom_fields_code_entity_type_unique');
});

it('down() succeeds once the duplicate rows are resolved', function (): void {
    $sectionA = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'code' => 'section_a']);
    $sectionB = CustomFieldSection::factory()->create(['entity_type' => Post::class, 'code' => 'section_b']);

    CustomField::factory()->create([
        'custom_field_section_id' => $sectionA->id,
        'entity_type' => Post::class,
        'code' => 'duplicate_code',
        'type' => 'text',
    ]);

    $duplicate = CustomField::factory()->create([
        'custom_field_section_id' => $sectionB->id,
        'entity_type' => Post::class,
        'code' => 'duplicate_code',
        'type' => 'text',
    ]);

    $duplicate->update(['code' => 'no_longer_duplicate']);

    expect(fn () => $this->migration->down())->not->toThrow(Throwable::class);

    expect(customFieldsIndexNames($this->table))
        ->toContain('custom_fields_code_entity_type_unique')
        ->not->toContain('cf_code_entity_section_unique');
});

it('honors prefix_indexes and the connection table prefix when computing the drop-target index name', function (): void {
    config()->set('database.connections.prefixed_for_test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'wp_',
        'prefix_indexes' => true,
    ]);

    $originalDefault = config('database.default');
    config()->set('database.default', 'prefixed_for_test');

    try {
        $connection = DB::connection('prefixed_for_test');
        $connection->useDefaultSchemaGrammar();
        $columns = ['code', 'entity_type', 'custom_field_section_id'];

        $defaultUniqueIndexName = new ReflectionMethod($this->migration, 'defaultUniqueIndexName');
        $defaultUniqueIndexName->setAccessible(true);
        $actual = $defaultUniqueIndexName->invoke($this->migration, 'custom_fields', $columns);

        $blueprint = new Blueprint($connection, 'custom_fields');
        $createIndexName = new ReflectionMethod($blueprint, 'createIndexName');
        $createIndexName->setAccessible(true);
        $expected = $createIndexName->invoke($blueprint, 'unique', $columns);

        expect($actual)->toBe($expected)
            ->and($actual)->toStartWith('wp_custom_fields_');
    } finally {
        config()->set('database.default', $originalDefault);
        DB::purge('prefixed_for_test');
    }
});

it('computes the same index name with no prefix configured, matching current behavior', function (): void {
    $columns = ['code', 'entity_type'];

    $defaultUniqueIndexName = new ReflectionMethod($this->migration, 'defaultUniqueIndexName');
    $defaultUniqueIndexName->setAccessible(true);
    $actual = $defaultUniqueIndexName->invoke($this->migration, 'custom_fields', $columns);

    expect($actual)->toBe('custom_fields_code_entity_type_unique');
});
