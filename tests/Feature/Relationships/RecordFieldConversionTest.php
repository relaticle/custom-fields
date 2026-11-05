<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

function conversionMigration(): Migration
{
    return require dirname(__DIR__, 3).'/database/migrations/convert_record_fields_to_relationships.php';
}

function lookupTypeFieldsTable(): string
{
    return (string) config('custom-fields.database.table_names.custom_fields');
}

/**
 * A 3.x record field: its target in lookup_type, multiplicity in settings, no definition.
 */
function threeRecordField(string $code = 'legacy_related', bool $allowMultiple = true, ?string $target = null): CustomField
{
    registerPostLookupEntity();

    if (! Schema::hasColumn(lookupTypeFieldsTable(), 'lookup_type')) {
        Schema::table(lookupTypeFieldsTable(), function (Blueprint $table): void {
            $table->string('lookup_type')->nullable();
        });
    }

    $attributes = [
        'code' => $code,
        'name' => 'Legacy Related',
        'type' => 'record',
        'entity_type' => (new Post)->getMorphClass(),
        'custom_field_section_id' => sectionForEntity((new Post)->getMorphClass())->getKey(),
    ];

    if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
        $attributes[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
    }

    $field = CustomField::factory()->create($attributes);

    DB::table(lookupTypeFieldsTable())->where('id', $field->getKey())->update([
        'lookup_type' => $target ?? (new Post)->getMorphClass(),
        'settings' => json_encode(['allow_multiple' => $allowMultiple]),
    ]);

    return $field;
}

/**
 * @param  array<int, int|string>  $ids
 */
function threeRecordValue(CustomField $field, Post $holder, array $ids): void
{
    $row = [
        'entity_type' => $holder->getMorphClass(),
        'entity_id' => $holder->getKey(),
        'custom_field_id' => $field->getKey(),
        'json_value' => json_encode($ids),
    ];

    if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
        $tenantKey = (string) config('custom-fields.database.column_names.tenant_foreign_key');
        $row[$tenantKey] = $field->{$tenantKey};
    }

    DB::table((string) config('custom-fields.database.table_names.custom_field_values'))->insert($row);
}

function mysqlCommitsDdl(): bool
{
    return DB::connection()->getDriverName() === 'mysql';
}

it('turns a record field into a definition and its ids into links, then drops the column', function (): void {
    $field = threeRecordField();
    [$first, $second] = Post::factory()->count(2)->create();
    $holder = Post::factory()->create();
    threeRecordValue($field, $holder, [$second->getKey(), $first->getKey()]);

    conversionMigration()->up();

    $definition = CustomFieldRelationship::query()->sole();

    expect(Schema::hasColumn(lookupTypeFieldsTable(), 'lookup_type'))->toBeFalse()
        ->and($definition->code)->toBe('legacy_related')
        ->and($definition->from_field_id)->toEqual($field->getKey())
        ->and($definition->to_field_id)->toBeNull()
        ->and($definition->cardinality)->toBe(RelationshipCardinality::ManyToMany)
        ->and(CustomFieldLink::query()->pluck('source')->unique()->all())->toBe(['migration'])
        ->and(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(0)
        ->and($holder->fresh()->getCustomFieldValue($field->fresh()))->toBe([$second->getKey(), $first->getKey()]);
})->skip(mysqlCommitsDdl(...), 'MySQL commits DDL implicitly, so the column change would outlive the test transaction.');

it('gives a single-value record field a many to one definition', function (): void {
    threeRecordField(allowMultiple: false);

    conversionMigration()->up();

    expect(CustomFieldRelationship::query()->sole()->cardinality)->toBe(RelationshipCardinality::ManyToOne);
})->skip(mysqlCommitsDdl(...), 'MySQL commits DDL implicitly, so the column change would outlive the test transaction.');

it('skips an id whose row is gone and keeps one that is only soft deleted', function (): void {
    $field = threeRecordField();
    [$kept, $trashed, $gone] = Post::factory()->count(3)->create();
    $holder = Post::factory()->create();
    threeRecordValue($field, $holder, [$kept->getKey(), $trashed->getKey(), $gone->getKey()]);
    $trashed->delete();
    $gone->forceDelete();

    conversionMigration()->up();

    expect(CustomFieldLink::query()->orderBy('sort_order')->pluck('to_entity_id')->map(intval(...))->all())
        ->toBe([$kept->getKey(), $trashed->getKey()]);
})->skip(mysqlCommitsDdl(...), 'MySQL commits DDL implicitly, so the column change would outlive the test transaction.');

it('adds only the missing links to a field that already has a definition', function (): void {
    $field = threeRecordField();
    [$linked, $unlinked] = Post::factory()->count(2)->create();
    $holder = Post::factory()->create();

    $definition = CustomFieldRelationship::query()->create([
        'code' => 'hand_defined',
        'from_entity_type' => (new Post)->getMorphClass(),
        'to_entity_type' => (new Post)->getMorphClass(),
        'cardinality' => RelationshipCardinality::ManyToMany,
        'is_symmetric' => false,
        'from_field_id' => $field->getKey(),
    ]);
    CustomFieldLink::factory()->create([
        'relationship_id' => $definition->getKey(),
        'from_entity_type' => $holder->getMorphClass(),
        'from_entity_id' => $holder->getKey(),
        'to_entity_type' => $linked->getMorphClass(),
        'to_entity_id' => $linked->getKey(),
    ]);
    threeRecordValue($field, $holder, [$linked->getKey(), $unlinked->getKey()]);

    conversionMigration()->up();

    expect(CustomFieldRelationship::query()->count())->toBe(1)
        ->and(CustomFieldLink::query()->pluck('to_entity_id')->map(intval(...))->sort()->values()->all())
        ->toBe([$linked->getKey(), $unlinked->getKey()]);
})->skip(mysqlCommitsDdl(...), 'MySQL commits DDL implicitly, so the column change would outlive the test transaction.');

it('switches off a record field with no target and keeps its stored ids', function (): void {
    $field = threeRecordField(target: '');
    $holder = Post::factory()->create();
    threeRecordValue($field, $holder, [$holder->getKey()]);

    conversionMigration()->up();

    expect(CustomFieldRelationship::query()->count())->toBe(0)
        ->and(DB::table(lookupTypeFieldsTable())->where('id', $field->getKey())->value('active'))->toBeFalsy()
        ->and(CustomFieldValue::query()->withoutGlobalScopes()->where('custom_field_id', $field->getKey())->count())->toBe(1);
})->skip(mysqlCommitsDdl(...), 'MySQL commits DDL implicitly, so the column change would outlive the test transaction.');

it('stamps each tenant on its own definition and links, codes scoped per tenant', function (): void {
    useTenantSchema(1);
    $first = threeRecordField(code: 'owner');
    $firstTarget = Post::factory()->create();
    threeRecordValue($first, Post::factory()->create(), [$firstTarget->getKey()]);

    TenantContextService::setTenantId(2);
    $second = threeRecordField(code: 'owner');
    $secondTarget = Post::factory()->create();
    threeRecordValue($second, Post::factory()->create(), [$secondTarget->getKey()]);

    conversionMigration()->up();

    $definitions = CustomFieldRelationship::query()->withoutGlobalScopes()->orderBy('tenant_id')->get();
    $links = CustomFieldLink::query()->withoutGlobalScopes()->orderBy('tenant_id')->get();

    expect($definitions->pluck('code')->all())->toBe(['owner', 'owner'])
        ->and($definitions->pluck('tenant_id')->all())->toBe([1, 2])
        ->and($links->pluck('tenant_id')->all())->toBe([1, 2])
        ->and($links->pluck('to_entity_id')->map(intval(...))->all())->toBe([$firstTarget->getKey(), $secondTarget->getKey()]);
})->skip(mysqlCommitsDdl(...), 'MySQL commits DDL implicitly, so the tenant columns would outlive the test transaction.');
