<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;

$definitionsMigration = fn (): Migration => require dirname(__DIR__, 3).'/database/migrations/create_relationship_definitions_table.php';
$linksMigration = fn (): Migration => require dirname(__DIR__, 3).'/database/migrations/create_relationship_links_table.php';

it('points the partial index at the prefixed table', function () use ($linksMigration): void {
    $connection = Schema::getConnection();
    $original = $connection->getTablePrefix();
    $connection->setTablePrefix('pfx_');

    try {
        $statement = collect(DB::pretend(function () use ($linksMigration): void {
            $linksMigration()->up();
        }))
            ->pluck('query')
            ->first(fn (string $query): bool => str_contains($query, 'cf_links_active_edge_unique'));
    } finally {
        $connection->setTablePrefix($original);
    }

    expect($statement)->toContain('pfx_custom_field_links');
})->skip(
    fn (): bool => ! in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true),
    'The MySQL family has no partial index, so there is nothing to prefix.',
);

it('scopes the definition code to the tenant when multi-tenancy is enabled', function () use ($definitionsMigration): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_MULTI_TENANCY,
    ));
    config()->set('custom-fields.database.table_names.custom_field_relationships', 'probe_relationships');

    $definitionsMigration()->up();

    $codeIndex = collect(Schema::getIndexes('probe_relationships'))
        ->first(fn (array $index): bool => $index['unique'] && in_array('code', $index['columns'], true));

    expect(Schema::hasColumn('probe_relationships', 'tenant_id'))->toBeTrue()
        ->and($codeIndex['columns'])->toEqualCanonicalizing(['code', 'tenant_id']);
});

it('stamps the links table with a tenant key when multi-tenancy is enabled', function () use ($linksMigration): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_MULTI_TENANCY,
    ));
    config()->set('custom-fields.database.table_names.custom_field_links', 'probe_links');

    $create = collect(DB::pretend(function () use ($linksMigration): void {
        $linksMigration()->up();
    }))
        ->pluck('query')
        ->first(fn (string $query): bool => str_contains($query, 'create table'));

    expect($create)->toContain('probe_links')
        ->toContain('tenant_id');
});

it('keys the slot columns off the custom field model, not the package key type', function () use ($definitionsMigration): void {
    config()->set('custom-fields.database.key_type', 'ulid');
    config()->set('custom-fields.database.table_names.custom_field_relationships', 'probe_relationships');

    $definitionsMigration()->up();

    $definitions = collect(Schema::getColumns('probe_relationships'))->keyBy('name');
    $customFieldKey = collect(Schema::getColumns('custom_fields'))->keyBy('name')->get('id');

    expect($definitions->get('from_field_id')['type_name'])->toBe($customFieldKey['type_name'])
        ->and($definitions->get('to_field_id')['type_name'])->toBe($customFieldKey['type_name'])
        ->and($definitions->get('id')['type_name'])->not->toBe($customFieldKey['type_name']);
});

it('keys the four tables that predate 4.0 off the package key type on a fresh install', function (): void {
    config()->set('custom-fields.database.key_type', 'ulid');
    config()->set('custom-fields.features', FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_SECTIONS,
        CustomFieldsFeature::SYSTEM_MULTI_TENANCY,
    ));

    $tables = config('custom-fields.database.table_names');

    foreach (['custom_field_links', 'custom_field_relationships', 'custom_field_values', 'custom_field_options', 'custom_fields', 'custom_field_sections'] as $key) {
        Schema::drop($tables[$key]);
    }

    (require dirname(__DIR__, 3).'/database/migrations/create_custom_fields_table.php')->up();

    $keyColumns = [
        'custom_field_sections' => ['id', 'tenant_id'],
        'custom_fields' => ['id', 'tenant_id', 'custom_field_section_id'],
        'custom_field_options' => ['id', 'tenant_id', 'custom_field_id'],
        'custom_field_values' => ['id', 'tenant_id', 'custom_field_id', 'entity_id'],
    ];

    foreach ($keyColumns as $key => $names) {
        $columns = collect(Schema::getColumns($tables[$key]))->keyBy('name');

        foreach ($names as $name) {
            expect($columns->get($name)['type_name'])->toBeIn(['varchar', 'char', 'bpchar'], sprintf('%s.%s is not a ULID column', $key, $name));
        }
    }
});

it('adds the provenance columns to a 3.x values table and leaves them alone once present', function (): void {
    $values = config('custom-fields.database.table_names.custom_field_values');
    $migration = require dirname(__DIR__, 3).'/database/migrations/track_custom_field_values_provenance.php';

    Schema::table($values, function (Blueprint $table): void {
        $table->dropColumn(['source', 'updated_at']);
    });

    $migration->up();
    $migration->up();

    expect(Schema::hasColumns($values, ['source', 'updated_at']))->toBeTrue();
});
