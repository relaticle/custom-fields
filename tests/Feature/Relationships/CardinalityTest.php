<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\RelationshipCardinality;

it('exposes single-or-multi per side', function (): void {
    expect(RelationshipCardinality::OneToOne->fromSideIsSingle())->toBeTrue()
        ->and(RelationshipCardinality::OneToOne->toSideIsSingle())->toBeTrue()
        ->and(RelationshipCardinality::OneToMany->fromSideIsSingle())->toBeFalse()
        ->and(RelationshipCardinality::OneToMany->toSideIsSingle())->toBeTrue()
        ->and(RelationshipCardinality::ManyToOne->fromSideIsSingle())->toBeTrue()
        ->and(RelationshipCardinality::ManyToOne->toSideIsSingle())->toBeFalse()
        ->and(RelationshipCardinality::ManyToMany->fromSideIsSingle())->toBeFalse()
        ->and(RelationshipCardinality::ManyToMany->toSideIsSingle())->toBeFalse();
});

it('registers the new table names in config', function (): void {
    expect(config('custom-fields.database.table_names.custom_field_relationships'))
        ->toBe('custom_field_relationships')
        ->and(config('custom-fields.database.table_names.custom_field_links'))
        ->toBe('custom_field_links')
        ->and(config('custom-fields.database.key_type'))
        ->toBe('bigint');
});
