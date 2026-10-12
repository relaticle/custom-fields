<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Data\CustomFieldData;
use Relaticle\CustomFields\Data\CustomFieldSectionData;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Filament\Integration\Migrations\CustomFieldsMigrator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\Relationships\DeleteRelationshipDefinition;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

function authorship(): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'authorship',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToOne,
        fromField: new FieldSlotData(name: 'Author', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
        toField: new FieldSlotData(name: 'Posts', sectionId: sectionForEntity((new User)->getMorphClass())->getKey()),
    ));
}

it('creates a paired definition with two record fields in one transaction', function (): void {
    $definition = authorship();

    expect($definition->cardinality)->toBe(RelationshipCardinality::ManyToOne)
        ->and($definition->fromField)->not->toBeNull()
        ->and($definition->fromField->type)->toBe('record')
        ->and($definition->fromField->code)->toBe('author')
        ->and($definition->fromField->entity_type)->toBe((new Post)->getMorphClass())
        ->and($definition->toField->entity_type)->toBe((new User)->getMorphClass())
        ->and($definition->toField->code)->toBe('posts')
        ->and(CustomField::query()->count())->toBe(2);
});

it('creates a one-way definition with a single field', function (): void {
    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'referrer',
        fromEntityType: (new User)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToOne,
        fromField: new FieldSlotData(name: 'Referred by', sectionId: sectionForEntity((new User)->getMorphClass())->getKey()),
    ));

    expect($definition->to_field_id)->toBeNull()
        ->and($definition->isHeadless())->toBeFalse()
        ->and(CustomField::query()->count())->toBe(1);
});

it('creates a headless definition with no fields', function (): void {
    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'works_with',
        fromEntityType: (new User)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
    ));

    expect($definition->isHeadless())->toBeTrue()
        ->and(CustomField::query()->count())->toBe(0);
});

it('points both slots at one field for a symmetric definition', function (): void {
    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'spouse',
        fromEntityType: (new User)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::OneToOne,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Spouse', sectionId: sectionForEntity((new User)->getMorphClass())->getKey()),
    ));

    expect($definition->from_field_id)->toBe($definition->to_field_id)
        ->and($definition->directionFor($definition->fromField))->toBe(CustomFieldRelationship::DIRECTION_FROM)
        ->and(CustomField::query()->count())->toBe(1);
});

it('rejects a symmetric definition across two entity types', function (): void {
    app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'spouse',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::OneToOne,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Spouse'),
    ));
})->throws(InvalidArgumentException::class);

it('rejects a second slot on a symmetric definition', function (): void {
    app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'spouse',
        fromEntityType: (new User)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::OneToOne,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Spouse'),
        toField: new FieldSlotData(name: 'Spouse of'),
    ));
})->throws(InvalidArgumentException::class);

it('rejects a directional cardinality on a symmetric definition', function (): void {
    app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'sibling',
        fromEntityType: (new User)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::OneToMany,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Sibling'),
    ));
})->throws(InvalidArgumentException::class);

it('writes no field when the definition is rejected', function (): void {
    $create = fn (): CustomFieldRelationship => app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'spouse',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::OneToOne,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Spouse'),
    ));

    expect($create)->toThrow(InvalidArgumentException::class)
        ->and(CustomField::query()->withDeactivated()->count())->toBe(0)
        ->and(CustomFieldRelationship::query()->count())->toBe(0);
});

it('rejects an end that resolves to no model', function (): void {
    app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'authorship',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: 'acme_ghosts',
        cardinality: RelationshipCardinality::ManyToOne,
    ));
})->throws(InvalidArgumentException::class);

it('rejects a code already used by another definition', function (): void {
    authorship();

    app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'authorship',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
    ));
})->throws(InvalidArgumentException::class);

it('unpairs on delete, keeping each field on its own one-way definition', function (): void {
    $definition = authorship();
    CustomFieldLink::factory()->create(['relationship_id' => $definition->getKey()]);

    app(DeleteRelationshipDefinition::class)->execute($definition, deleteFields: false);

    expect(CustomFieldRelationship::query()->count())->toBe(2)
        ->and(CustomFieldRelationship::query()->whereNotNull('from_field_id')->count())->toBe(1)
        ->and(CustomFieldRelationship::query()->whereNotNull('to_field_id')->count())->toBe(1)
        ->and(CustomFieldLink::query()->count())->toBe(0)
        ->and(CustomField::query()->count())->toBe(2);
});

it('deletes the slot fields when asked', function (): void {
    $definition = authorship();
    CustomFieldLink::factory()->create(['relationship_id' => $definition->getKey()]);

    app(DeleteRelationshipDefinition::class)->execute($definition, deleteFields: true);

    expect(CustomFieldRelationship::query()->count())->toBe(0)
        ->and(CustomFieldLink::query()->count())->toBe(0)
        ->and(CustomField::query()->withDeactivated()->count())->toBe(0);
});

it('keeps the definition and its edges when one slot field is deleted', function (): void {
    $definition = authorship();
    CustomFieldLink::factory()->create(['relationship_id' => $definition->getKey()]);

    $definition->toField->delete();

    expect($definition->refresh()->to_field_id)->toBeNull()
        ->and($definition->from_field_id)->not->toBeNull()
        ->and(CustomFieldLink::query()->count())->toBe(1);
});

it('still unpairs a deleted slot field while the relationships feature is off', function (): void {
    $definition = authorship();
    CustomFieldLink::factory()->create(['relationship_id' => $definition->getKey()]);

    config('custom-fields.features')->disable(CustomFieldsFeature::SYSTEM_RELATIONSHIPS);

    $definition->toField->delete();

    expect($definition->refresh()->to_field_id)->toBeNull()
        ->and($definition->from_field_id)->not->toBeNull()
        ->and(CustomFieldLink::query()->count())->toBe(1);
});

it('removes a one-way definition and its edges when its only field is deleted', function (): void {
    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'referrer',
        fromEntityType: (new User)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToOne,
        fromField: new FieldSlotData(name: 'Referred by', sectionId: sectionForEntity((new User)->getMorphClass())->getKey()),
    ));
    CustomFieldLink::factory()->create(['relationship_id' => $definition->getKey()]);

    $definition->fromField->delete();

    expect(CustomFieldRelationship::query()->count())->toBe(0)
        ->and(CustomFieldLink::query()->count())->toBe(0);
});

it('removes a symmetric definition when its single field is deleted', function (): void {
    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'spouse',
        fromEntityType: (new User)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::OneToOne,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Spouse', sectionId: sectionForEntity((new User)->getMorphClass())->getKey()),
    ));
    CustomFieldLink::factory()->create(['relationship_id' => $definition->getKey()]);

    $definition->fromField->delete();

    expect(CustomFieldRelationship::query()->count())->toBe(0)
        ->and(CustomFieldLink::query()->count())->toBe(0);
});

it('unpairs a slot from a foreign tenant context', function (): void {
    useTenantSchema(7);

    $definition = authorship();
    CustomFieldLink::factory()->create([
        'relationship_id' => $definition->getKey(),
        'tenant_id' => 7,
    ]);
    $toField = $definition->toField;

    TenantContextService::setTenantId(8);

    $toField->delete();

    TenantContextService::setTenantId(7);

    expect($definition->refresh()->to_field_id)->toBeNull()
        ->and($definition->from_field_id)->not->toBeNull()
        ->and(CustomFieldLink::query()->count())->toBe(1);
})->skip(
    fn (): bool => DB::connection()->getDriverName() === 'mysql',
    'MySQL commits DDL implicitly, so the added tenant columns would outlive the test transaction.',
);

it('refuses to move the ends of an existing definition', function (): void {
    $definition = authorship();

    $definition->update(['to_entity_type' => (new Post)->getMorphClass()]);
})->throws(RuntimeException::class);

it('allows a cardinality change on an existing definition', function (): void {
    $definition = authorship();

    $definition->update(['cardinality' => RelationshipCardinality::ManyToMany]);

    expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToMany);
});

it('stamps the tenant on the definition and both slot fields', function (): void {
    useTenantSchema(7);

    $definition = authorship();

    expect($definition->tenant_id)->toBe(7)
        ->and($definition->fromField->tenant_id)->toBe(7)
        ->and($definition->toField->tenant_id)->toBe(7);

    DB::table(config('custom-fields.database.table_names.custom_field_relationships'))
        ->where('id', $definition->getKey())
        ->update(['tenant_id' => 8]);

    expect(CustomFieldRelationship::query()->count())->toBe(0);
})->skip(
    fn (): bool => DB::connection()->getDriverName() === 'mysql',
    'MySQL commits DDL implicitly, so the added tenant columns would outlive the test transaction.',
);

describe('adopting a field the caller already wrote', function (): void {
    it('wraps an existing record field as the slot instead of creating one', function (): void {
        $field = CustomField::factory()->create([
            'code' => 'mentor',
            'name' => 'Mentor',
            'type' => 'record',
            'entity_type' => (new User)->getMorphClass(),
            'custom_field_section_id' => sectionForEntity((new User)->getMorphClass())->getKey(),
        ]);

        $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'mentorship',
            fromEntityType: (new User)->getMorphClass(),
            toEntityType: (new User)->getMorphClass(),
            cardinality: RelationshipCardinality::ManyToOne,
            fromField: new FieldSlotData(name: 'Mentor', fieldId: $field->getKey()),
        ));

        expect($definition->from_field_id)->toBe($field->getKey())
            ->and(CustomField::query()->count())->toBe(1);
    });

    it('refuses a field that is not a record field', function (): void {
        $field = CustomField::factory()->create([
            'code' => 'stage',
            'type' => 'select',
            'entity_type' => (new User)->getMorphClass(),
            'custom_field_section_id' => sectionForEntity((new User)->getMorphClass())->getKey(),
        ]);

        app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'mentorship',
            fromEntityType: (new User)->getMorphClass(),
            toEntityType: (new User)->getMorphClass(),
            cardinality: RelationshipCardinality::ManyToOne,
            fromField: new FieldSlotData(name: 'Stage', fieldId: $field->getKey()),
        ));
    })->throws(InvalidArgumentException::class);

    it('refuses a field that sits on the other entity', function (): void {
        $field = CustomField::factory()->create([
            'code' => 'mentor',
            'type' => 'record',
            'entity_type' => (new Post)->getMorphClass(),
            'custom_field_section_id' => sectionForEntity((new Post)->getMorphClass())->getKey(),
        ]);

        app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'mentorship',
            fromEntityType: (new User)->getMorphClass(),
            toEntityType: (new User)->getMorphClass(),
            cardinality: RelationshipCardinality::ManyToOne,
            fromField: new FieldSlotData(name: 'Mentor', fieldId: $field->getKey()),
        ));
    })->throws(InvalidArgumentException::class);

    it('refuses a field that already renders a relationship', function (): void {
        $definition = authorship();

        app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'second_authorship',
            fromEntityType: (new Post)->getMorphClass(),
            toEntityType: (new User)->getMorphClass(),
            cardinality: RelationshipCardinality::ManyToOne,
            fromField: new FieldSlotData(name: 'Author', fieldId: $definition->from_field_id),
        ));
    })->throws(InvalidArgumentException::class);
});

describe('preset migrations', function (): void {
    it('gives a record field a one-way definition instead of a lookup column', function (): void {
        app(CustomFieldsMigrator::class)->new(
            model: Post::class,
            fieldData: new CustomFieldData(
                name: 'Sales Representative',
                code: 'sales_rep',
                type: 'record',
                section: new CustomFieldSectionData(name: 'Sales', code: 'sales'),
            ),
        )->lookupType(User::class)->create();

        $definition = CustomFieldRelationship::query()->sole();

        expect($definition->from_entity_type)->toBe((new Post)->getMorphClass())
            ->and($definition->to_entity_type)->toBe((new User)->getMorphClass())
            ->and($definition->cardinality)->toBe(RelationshipCardinality::ManyToOne)
            ->and($definition->fromField->code)->toBe('sales_rep')
            ->and($definition->to_field_id)->toBeNull();
    });

    it('reads allow_multiple once, to pick the cardinality', function (): void {
        app(CustomFieldsMigrator::class)->new(
            model: Post::class,
            fieldData: new CustomFieldData(
                name: 'Reviewers',
                code: 'reviewers',
                type: 'record',
                section: new CustomFieldSectionData(name: 'Sales', code: 'sales'),
                settings: new CustomFieldSettingsData(allow_multiple: true),
            ),
        )->lookupType(User::class)->create();

        expect(CustomFieldRelationship::query()->sole()->cardinality)
            ->toBe(RelationshipCardinality::ManyToMany);
    });

    it('takes an explicit cardinality over the settings flag', function (): void {
        app(CustomFieldsMigrator::class)->new(
            model: Post::class,
            fieldData: new CustomFieldData(
                name: 'Owner',
                code: 'owner',
                type: 'record',
                section: new CustomFieldSectionData(name: 'Sales', code: 'sales'),
                settings: new CustomFieldSettingsData(allow_multiple: true),
            ),
        )->lookupType(User::class, RelationshipCardinality::OneToOne)->create();

        expect(CustomFieldRelationship::query()->sole()->cardinality)
            ->toBe(RelationshipCardinality::OneToOne);
    });

    it('refuses to move the ends of a field it already created', function (): void {
        app(CustomFieldsMigrator::class)->new(
            model: Post::class,
            fieldData: new CustomFieldData(
                name: 'Sales Representative',
                code: 'sales_rep',
                type: 'record',
                section: new CustomFieldSectionData(name: 'Sales', code: 'sales'),
            ),
        )->lookupType(User::class)->create();

        app(CustomFieldsMigrator::class)
            ->find(Post::class, 'sales_rep')
            ->update(['lookup_type' => (new Post)->getMorphClass()]);
    })->throws(InvalidArgumentException::class);
});
