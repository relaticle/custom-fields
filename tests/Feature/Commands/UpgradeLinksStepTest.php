<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

function recordField(bool $allowMultiple = true, string $code = 'legacy_related'): CustomField
{
    registerPostLookupEntity();

    $attributes = [
        'code' => $code,
        'name' => 'Legacy Related',
        'type' => 'record',
        'entity_type' => (new Post)->getMorphClass(),
        'settings' => new CustomFieldSettingsData(allow_multiple: $allowMultiple),
        'custom_field_section_id' => sectionForEntity((new Post)->getMorphClass())->getKey(),
    ];

    if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
        $attributes[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
    }

    return CustomField::factory()->create($attributes);
}

/**
 * A 3.x record field, on the schema a host still has when it runs the upgrade command: the
 * migration that drops lookup_type refuses to run until this step has read it.
 */
function legacyRecordField(bool $allowMultiple = true, string $code = 'legacy_related'): CustomField
{
    restoreLookupTypeColumn();

    $field = recordField($allowMultiple, $code);

    DB::table((string) config('custom-fields.database.table_names.custom_fields'))
        ->where('id', $field->getKey())
        ->update(['lookup_type' => (new Post)->getMorphClass()]);

    return $field;
}

function commitsSchemaChanges(): bool
{
    return DB::connection()->getDriverName() === 'mysql';
}

function restoreLookupTypeColumn(): void
{
    $table = (string) config('custom-fields.database.table_names.custom_fields');

    if (Schema::hasColumn($table, 'lookup_type')) {
        return;
    }

    Schema::table($table, function (Blueprint $blueprint): void {
        $blueprint->string('lookup_type')->nullable();
    });
}

function definitionForField(CustomField $field, string $code, RelationshipCardinality $cardinality = RelationshipCardinality::ManyToMany): CustomFieldRelationship
{
    return CustomFieldRelationship::query()->create([
        'code' => $code,
        'from_entity_type' => (new Post)->getMorphClass(),
        'to_entity_type' => (new Post)->getMorphClass(),
        'cardinality' => $cardinality,
        'is_symmetric' => false,
        'from_field_id' => $field->getKey(),
        'to_field_id' => null,
    ]);
}

it('migrates json_value arrays into definitions and links', function (): void {
    $field = legacyRecordField();
    [$first, $second] = Post::factory()->count(2)->create();
    $post = Post::factory()->create(['custom_fields' => [$field->code => [$first->getKey(), $second->getKey()]]]);

    expect(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1);

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('Migrating record field')
        ->assertSuccessful();

    $definition = CustomFieldRelationship::query()->sole();
    $migrated = $field->fresh();

    expect($definition->code)->toBe($field->code)
        ->and($definition->from_field_id)->toEqual($field->getKey())
        ->and($definition->to_field_id)->toBeNull()
        ->and($definition->cardinality)->toBe(RelationshipCardinality::ManyToMany)
        ->and($definition->from_entity_type)->toBe((new Post)->getMorphClass())
        ->and($definition->to_entity_type)->toBe((new Post)->getMorphClass())
        ->and(CustomFieldLink::query()->active()->pluck('source')->all())->toBe(['migration', 'migration'])
        ->and(CustomFieldLink::query()->active()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([0, 1])
        ->and(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1)
        ->and($post->fresh()->getCustomFieldValue($migrated))->toBe([$first->getKey(), $second->getKey()]);
});

it('gives a single-value record field a many to one definition', function (): void {
    $field = legacyRecordField(allowMultiple: false);
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    expect(CustomFieldRelationship::query()->sole()->cardinality)->toBe(RelationshipCardinality::ManyToOne);
});

it('writes no link for a record value that was cleared', function (): void {
    $field = legacyRecordField();
    $target = Post::factory()->create();
    $post = Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);
    $post->update(['custom_fields' => [$field->code => []]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    expect(CustomFieldLink::query()->count())->toBe(0)
        ->and(CustomFieldRelationship::query()->count())->toBe(1)
        ->and($post->fresh()->getCustomFieldValue($field->fresh()))->toBe([]);
});

it('reports the migration in dry-run mode and writes nothing', function (): void {
    $field = legacyRecordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--dry-run' => true])
        ->expectsOutputToContain('would be created')
        ->assertSuccessful();

    expect(CustomFieldLink::query()->count())->toBe(0)
        ->and(CustomFieldRelationship::query()->count())->toBe(0)
        ->and(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1);
});

it('creates nothing twice across reruns', function (): void {
    $field = legacyRecordField();
    [$first, $second] = Post::factory()->count(2)->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$first->getKey(), $second->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();
    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    expect(CustomFieldRelationship::query()->count())->toBe(1)
        ->and(CustomFieldLink::query()->count())->toBe(2);
});

it('migrates the values a field with a definition still holds', function (): void {
    $field = legacyRecordField();
    $first = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$first->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    $leftover = Post::factory()->create();
    $second = Post::factory()->create();

    CustomFieldValue::query()->create([
        'entity_type' => $leftover->getMorphClass(),
        'entity_id' => $leftover->getKey(),
        'custom_field_id' => $field->getKey(),
        'json_value' => [$second->getKey()],
    ]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    expect(CustomFieldRelationship::query()->count())->toBe(1)
        ->and(CustomFieldLink::query()->active()->count())->toBe(2)
        ->and($leftover->fresh()->getCustomFieldValue($field->fresh()))->toBe([$second->getKey()]);
});

it('refuses to migrate a field that reads the far end of its definition', function (): void {
    $field = recordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    CustomFieldRelationship::query()->create([
        'code' => 'reversed_related',
        'from_entity_type' => (new Post)->getMorphClass(),
        'to_entity_type' => (new Post)->getMorphClass(),
        'cardinality' => RelationshipCardinality::ManyToMany,
        'is_symmetric' => false,
        'from_field_id' => null,
        'to_field_id' => $field->getKey(),
    ]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('reads the to end of reversed_related, skipped')
        ->assertFailed();

    expect(CustomFieldLink::query()->count())->toBe(0);
});

it('keeps the migrated value rows until the purge is asked for', function (): void {
    $field = legacyRecordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('Skipping: purge-record-values')
        ->assertSuccessful();

    expect(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1);
});

it('purges the migrated value rows when asked, leaving the links alone', function (): void {
    $field = legacyRecordField();
    $target = Post::factory()->create();
    $post = Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();
    $this->artisan('custom-fields:upgrade', ['--force' => true, '--purge' => true])
        ->expectsOutputToContain('Purge Migrated Record Values')
        ->assertSuccessful();

    expect(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(0)
        ->and(CustomFieldLink::query()->active()->count())->toBe(1)
        ->and($post->fresh()->getCustomFieldValue($field->fresh()))->toBe([$target->getKey()]);
});

it('deletes nothing in a dry-run purge', function (): void {
    $field = legacyRecordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();
    $this->artisan('custom-fields:upgrade', ['--dry-run' => true, '--purge' => true])
        ->expectsOutputToContain('would be deleted')
        ->assertSuccessful();

    expect(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1);
});

it('stops at the gate before a purge that would delete unmigrated values', function (): void {
    $field = recordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', [
        '--force' => true,
        '--purge' => true,
        '--skip' => 'migrate-record-links',
    ])
        ->expectsOutputToContain('record links still in json_value')
        ->doesntExpectOutputToContain('Purging value rows')
        ->assertFailed();

    expect(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1)
        ->and(CustomFieldLink::query()->count())->toBe(0);
});

it('refuses to purge values the ledger does not hold even when the gate is skipped', function (): void {
    $field = recordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    definitionForField($field, 'hand_defined');

    $this->artisan('custom-fields:upgrade', [
        '--force' => true,
        '--purge' => true,
        '--skip' => 'migrate-record-links,validate-schema',
    ])
        ->expectsOutputToContain('run the Migrate Record Links step first')
        ->assertFailed();

    expect(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1);
});

it('leaves an unlinked record unlinked across a rerun', function (RelationshipCardinality $cardinality): void {
    $field = recordField();
    $target = Post::factory()->create();
    $post = Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    definitionForField($field, 'rerun_related', $cardinality);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    expect(CustomFieldLink::query()->active()->count())->toBe(1);

    $post->update(['custom_fields' => [$field->code => []]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    expect(CustomFieldLink::query()->active()->count())->toBe(0)
        ->and(CustomFieldLink::query()->count())->toBe(1);
})->with([
    'many to many' => RelationshipCardinality::ManyToMany,
    'many to one' => RelationshipCardinality::ManyToOne,
]);

it('stops before the purge when the migration fails', function (): void {
    $field = recordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    CustomFieldRelationship::query()->create([
        'code' => 'far_end_related',
        'from_entity_type' => (new Post)->getMorphClass(),
        'to_entity_type' => (new Post)->getMorphClass(),
        'cardinality' => RelationshipCardinality::ManyToMany,
        'is_symmetric' => false,
        'from_field_id' => null,
        'to_field_id' => $field->getKey(),
    ]);

    $this->artisan('custom-fields:upgrade', ['--force' => true, '--purge' => true])
        ->expectsOutputToContain('Stopping: migrate-record-links failed.')
        ->doesntExpectOutputToContain('Purging value rows')
        ->assertFailed();

    expect(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1);
});

it('warns about record links still in json_value while the run migrates them', function (): void {
    $field = legacyRecordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('links still in json_value, migrating below')
        ->assertSuccessful();
});

it('fails validation when the record-links step is skipped and links are still in json_value', function (): void {
    $field = recordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true, '--skip' => 'migrate-record-links'])
        ->expectsOutputToContain('record links still in json_value')
        ->assertFailed();

    expect(CustomFieldLink::query()->count())->toBe(0);
});

it('fails validation while the relationship tables are missing', function (): void {
    config()->set('custom-fields.database.table_names.custom_field_links', 'not_a_links_table');

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('Table not_a_links_table: MISSING')
        ->assertFailed();
});

it('validates the schema without the relationship tables while the feature is off', function (): void {
    config('custom-fields.features')->disable(CustomFieldsFeature::SYSTEM_RELATIONSHIPS);
    config()->set('custom-fields.database.table_names.custom_field_links', 'not_a_links_table');

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('Skipping: purge-record-values')
        ->assertSuccessful();
});

it('stamps the tenant of the field on the definition and its links', function (): void {
    useTenantSchema(9);

    $field = legacyRecordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    expect(CustomFieldRelationship::query()->sole()->tenant_id)->toBe(9)
        ->and(CustomFieldLink::query()->sole()->tenant_id)->toBe(9);
})->skip(
    fn (): bool => DB::connection()->getDriverName() === 'mysql',
    'MySQL commits DDL implicitly, so the added tenant columns would outlive the test transaction.',
);

it('leaves every record field with a definition, and no column to hold a target', function (): void {
    $field = legacyRecordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    $migration = require __DIR__.'/../../../database/migrations/drop_custom_fields_lookup_type.php';
    $migration->up();

    $definitionless = CustomField::query()
        ->forType('record')
        ->get()
        ->reject(fn (CustomField $record): bool => $record->relationshipDefinition() instanceof CustomFieldRelationship);

    expect(Schema::hasColumn((string) config('custom-fields.database.table_names.custom_fields'), 'lookup_type'))->toBeFalse()
        ->and($definitionless)->toBeEmpty()
        ->and(CustomField::query()->forType('record')->count())->toBe(1);
})->skip(commitsSchemaChanges(...), 'MySQL commits DDL implicitly, so dropping the column inside the test would end its transaction.');

it('refuses to drop the lookup column while a record field still has no definition', function (): void {
    $field = legacyRecordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $migration = require __DIR__.'/../../../database/migrations/drop_custom_fields_lookup_type.php';

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, $field->code)
        ->and(Schema::hasColumn((string) config('custom-fields.database.table_names.custom_fields'), 'lookup_type'))->toBeTrue();
});

it('skips an id whose record is gone and reports it', function (): void {
    $field = recordField();
    [$kept, $gone] = Post::factory()->count(2)->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$kept->getKey(), $gone->getKey()]]]);

    definitionForField($field, 'dangling_related');
    $gone->forceDelete();

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('1 id(s) point at missing rows, skipped')
        ->assertSuccessful();

    expect(CustomFieldLink::query()->active()->pluck('to_entity_id')->map(intval(...))->all())->toBe([$kept->getKey()]);
});

it('migrates an end that is only soft deleted', function (): void {
    $field = recordField();
    $trashed = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$trashed->getKey()]]]);

    definitionForField($field, 'trashed_related');
    $trashed->delete();

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->doesntExpectOutputToContain('point at missing rows')
        ->assertSuccessful();

    expect(CustomFieldLink::query()->active()->count())->toBe(1);
});

it('skips a record field that has neither a target nor values', function (): void {
    $field = recordField();

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('no lookup type and no values, skipped')
        ->assertSuccessful();

    expect(CustomFieldRelationship::query()->count())->toBe(0)
        ->and($field->fresh())->not->toBeNull();
});

it('fails on a record field holding values with no target', function (): void {
    $field = recordField();
    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$field->code => [$target->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('values with no lookup type')
        ->assertFailed();

    expect(CustomFieldLink::query()->count())->toBe(0);
});

it('keeps one definition code per tenant when two tenants share a field code', function (): void {
    useTenantSchema(1);

    $first = legacyRecordField(code: 'owner');
    $firstTarget = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$first->code => [$firstTarget->getKey()]]]);

    TenantContextService::setTenantId(2);

    $second = legacyRecordField(code: 'owner');
    $secondTarget = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$second->code => [$secondTarget->getKey()]]]);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    $definitions = CustomFieldRelationship::query()
        ->withoutGlobalScopes()
        ->orderBy('tenant_id')
        ->get();

    expect($definitions->pluck('code')->all())->toBe(['owner', 'owner'])
        ->and($definitions->pluck('tenant_id')->all())->toBe([1, 2]);
})->skip(commitsSchemaChanges(...), 'MySQL commits DDL implicitly, so the added tenant columns would outlive the test transaction.');

it('gives a legacy record field with no values a definition of its own', function (): void {
    $field = legacyRecordField();

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    $definition = CustomFieldRelationship::query()->sole();

    expect($definition->from_field_id)->toEqual($field->getKey())
        ->and($definition->to_entity_type)->toBe((new Post)->getMorphClass())
        ->and(CustomFieldLink::query()->count())->toBe(0);
});

it('refuses to drop the lookup column while a valueless record field still has no definition', function (): void {
    $field = legacyRecordField();

    $migration = require __DIR__.'/../../../database/migrations/drop_custom_fields_lookup_type.php';

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, $field->code);

    $this->artisan('custom-fields:upgrade', ['--force' => true])->assertSuccessful();

    $migration->up();

    expect(Schema::hasColumn((string) config('custom-fields.database.table_names.custom_fields'), 'lookup_type'))->toBeFalse()
        ->and($field->fresh()->targetEntityType())->toBe((new Post)->getMorphClass());
})->skip(commitsSchemaChanges(...), 'MySQL commits DDL implicitly, so dropping the column inside the test would end its transaction.');

it('drops the lookup column when a record field without a definition has no target either', function (): void {
    restoreLookupTypeColumn();
    recordField();

    $migration = require __DIR__.'/../../../database/migrations/drop_custom_fields_lookup_type.php';
    $migration->up();

    expect(Schema::hasColumn((string) config('custom-fields.database.table_names.custom_fields'), 'lookup_type'))->toBeFalse();
})->skip(commitsSchemaChanges(...), 'MySQL commits DDL implicitly, so dropping the column inside the test would end its transaction.');
