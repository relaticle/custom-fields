<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\LinkSide;
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
        ->and($definition->nearEnd($from)->side)->toBe(LinkSide::From)
        ->and($definition->nearEnd($to)->side)->toBe(LinkSide::To)
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

it('rejects nearEnd on an unrelated field', function (): void {
    $definition = CustomFieldRelationship::factory()->create();
    $stranger = CustomField::factory()->create(['type' => 'record']);

    $definition->nearEnd($stranger);
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

it('rejects nearEnd on a field that has never been saved', function (): void {
    $definition = CustomFieldRelationship::factory()->create(['from_field_id' => null, 'to_field_id' => null]);

    $definition->nearEnd(CustomField::factory()->make(['type' => 'record']));
})->throws(InvalidArgumentException::class);

it('resolves the definition model through the swap registry', function (): void {
    expect(CustomFields::relationshipModel())->toBe(CustomFieldRelationship::class)
        ->and(CustomFields::newRelationshipModel())->toBeInstanceOf(CustomFieldRelationship::class)
        ->and(CustomFields::newRelationshipModel()->getTable())
        ->toBe(config('custom-fields.database.table_names.custom_field_relationships'));
});

it('answers relationshipDefinition for an eager-loaded list of link fields in a constant number of queries', function (): void {
    $section = CustomFieldSection::factory()->create();
    CustomField::factory()->create(['type' => 'text', 'entity_type' => 'post', 'custom_field_section_id' => $section->id]);

    $definitionsFor = function (int $count) use ($section): array {
        $definitions = [];

        foreach (range(1, $count) as $index) {
            $field = CustomField::factory()->create(['type' => 'record', 'entity_type' => 'post', 'custom_field_section_id' => $section->id]);
            $slot = $index % 2 === 0 ? 'to_field_id' : 'from_field_id';
            $definitions[$field->id] = CustomFieldRelationship::factory()->create(['from_field_id' => null, 'to_field_id' => null, $slot => $field->id])->id;
        }

        return $definitions;
    };

    $readAll = function (): array {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $read = CustomField::query()
            ->linkFields()
            ->withRelationshipDefinition()
            ->get()
            ->mapWithKeys(fn (CustomField $field): array => [$field->id => $field->relationshipDefinition()?->id])
            ->all();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$read, $queries];
    };

    $expected = $definitionsFor(2);
    [$read, $queriesForTwo] = $readAll();

    expect($read)->toEqual($expected);

    $expected += $definitionsFor(6);
    [$read, $queriesForEight] = $readAll();

    expect($read)->toEqual($expected)
        ->and($queriesForEight)->toBe($queriesForTwo)
        ->and($queriesForTwo)->toBe(3);
});

it('reads no definitions for a list of fields that cannot point at records', function (): void {
    $section = CustomFieldSection::factory()->create();
    CustomField::factory()->create(['type' => 'text', 'entity_type' => 'post', 'custom_field_section_id' => $section->id]);
    CustomField::factory()->create(['type' => 'select', 'entity_type' => 'post', 'custom_field_section_id' => $section->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $definitions = CustomField::query()
        ->withRelationshipDefinition()
        ->get()
        ->map(fn (CustomField $field): ?CustomFieldRelationship => $field->relationshipDefinition())
        ->filter()
        ->all();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($definitions)->toBe([])
        ->and($queries)->toBe(1);
});
