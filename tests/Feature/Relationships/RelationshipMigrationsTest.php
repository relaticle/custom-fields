<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
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

it('creates neither table when relationships are disabled', function () use ($definitionsMigration, $linksMigration): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->disable(CustomFieldsFeature::SYSTEM_RELATIONSHIPS));
    config()->set('custom-fields.database.table_names.custom_field_relationships', 'probe_relationships');
    config()->set('custom-fields.database.table_names.custom_field_links', 'probe_links');

    $definitionsMigration()->up();
    $linksMigration()->up();

    expect(Schema::hasTable('probe_relationships'))->toBeFalse()
        ->and(Schema::hasTable('probe_links'))->toBeFalse();
});

it('scopes the definition code to the tenant when multi-tenancy is enabled', function () use ($definitionsMigration): void {
    config()->set('custom-fields.features', FeatureConfigurator::configure()->enable(
        CustomFieldsFeature::SYSTEM_RELATIONSHIPS,
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
        CustomFieldsFeature::SYSTEM_RELATIONSHIPS,
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
