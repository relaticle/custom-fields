<?php

declare(strict_types=1);

use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldSection;

it('persists a definition and reads direction per field', function (): void {
    $section = CustomFieldSection::factory()->create();

    $from = CustomField::factory()->create([
        'type' => 'record',
        'entity_type' => 'post',
        'custom_field_section_id' => $section->id,
    ]);

    $to = CustomField::factory()->create([
        'type' => 'record',
        'entity_type' => 'user',
        'custom_field_section_id' => $section->id,
    ]);

    $definition = CustomFieldRelationship::factory()->create([
        'code' => 'author_of',
        'from_entity_type' => 'post',
        'to_entity_type' => 'user',
        'cardinality' => RelationshipCardinality::ManyToOne,
        'from_field_id' => $from->id,
        'to_field_id' => $to->id,
    ]);

    expect($definition->cardinality)->toBe(RelationshipCardinality::ManyToOne)
        ->and($definition->directionFor($from))->toBe('from')
        ->and($definition->directionFor($to))->toBe('to')
        ->and($definition->isHeadless())->toBeFalse()
        ->and($definition->fromField)->toBeSameModel($from)
        ->and($definition->toField)->toBeSameModel($to)
        ->and($from->relationshipDefinition()?->id)->toBe($definition->id);
});

it('supports headless definitions with no fields', function (): void {
    $definition = CustomFieldRelationship::factory()->create([
        'from_field_id' => null,
        'to_field_id' => null,
    ]);

    expect($definition->isHeadless())->toBeTrue()
        ->and($definition->is_symmetric)->toBeFalse();
});

it('rejects directionFor on an unrelated field', function (): void {
    $definition = CustomFieldRelationship::factory()->create();
    $stranger = CustomField::factory()->create(['type' => 'record']);

    $definition->directionFor($stranger);
})->throws(InvalidArgumentException::class);

it('matches no definition for a field that has never been saved', function (): void {
    $section = CustomFieldSection::factory()->create();

    CustomFieldRelationship::factory()->create(['from_field_id' => null, 'to_field_id' => null]);

    $field = CustomField::factory()->make(['type' => 'record', 'custom_field_section_id' => $section->id]);

    expect($field->relationshipDefinition())->toBeNull();

    $field->save();

    $definition = CustomFieldRelationship::factory()->create(['to_field_id' => $field->id]);

    expect($field->relationshipDefinition()?->id)->toBe($definition->id);
});

it('rejects directionFor on a field that has never been saved', function (): void {
    $definition = CustomFieldRelationship::factory()->create(['from_field_id' => null, 'to_field_id' => null]);

    $definition->directionFor(CustomField::factory()->make(['type' => 'record']));
})->throws(InvalidArgumentException::class);

it('resolves the definition model through the swap registry', function (): void {
    expect(CustomFields::relationshipModel())->toBe(CustomFieldRelationship::class)
        ->and(CustomFields::newRelationshipModel())->toBeInstanceOf(CustomFieldRelationship::class)
        ->and(CustomFields::newRelationshipModel()->getTable())
        ->toBe(config('custom-fields.database.table_names.custom_field_relationships'));
});
