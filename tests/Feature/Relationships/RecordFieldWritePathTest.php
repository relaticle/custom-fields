<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

function writePathAuthorship(RelationshipCardinality $cardinality = RelationshipCardinality::ManyToMany): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'write_path_authorship',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: $cardinality,
        fromField: new FieldSlotData(name: 'Author', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
    ));
}

/**
 * @return array<int, int|string>
 */
function activeTargetIds(): array
{
    return CustomFieldLink::query()
        ->active()
        ->orderBy('sort_order')
        ->pluck('to_entity_id')
        ->map(intval(...))
        ->all();
}

it('writes links instead of a value row when a create payload names a record field', function (): void {
    $definition = writePathAuthorship();
    $user = User::factory()->create();

    Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$user->getKey()]]]);

    expect(activeTargetIds())->toBe([$user->getKey()])
        ->and(CustomFieldValue::query()->where('custom_field_id', $definition->from_field_id)->count())->toBe(0);
});

it('replaces the edge set when an update payload changes it', function (): void {
    $definition = writePathAuthorship();
    $code = $definition->fromField->code;
    [$a, $b] = User::factory()->count(2)->create();

    $post = Post::factory()->create(['custom_fields' => [$code => [$a->getKey()]]]);

    $post->update(['custom_fields' => [$code => [$b->getKey()]]]);

    expect(activeTargetIds())->toBe([$b->getKey()])
        ->and(CustomFieldLink::query()->whereNotNull('active_until')->count())->toBe(1);
});

it('closes every edge when the payload holds an empty value', function (): void {
    $definition = writePathAuthorship();
    $code = $definition->fromField->code;
    $user = User::factory()->create();

    $post = Post::factory()->create(['custom_fields' => [$code => [$user->getKey()]]]);

    $post->update(['custom_fields' => [$code => []]]);

    expect(activeTargetIds())->toBe([])
        ->and(CustomFieldLink::query()->count())->toBe(1);
});

it('closes every edge when the payload holds null', function (): void {
    $definition = writePathAuthorship();
    $code = $definition->fromField->code;
    $user = User::factory()->create();

    $post = Post::factory()->create(['custom_fields' => [$code => [$user->getKey()]]]);

    $post->update(['custom_fields' => [$code => null]]);

    expect(activeTargetIds())->toBe([]);
});

it('leaves the edges alone when the payload omits the record field', function (): void {
    $definition = writePathAuthorship();
    $code = $definition->fromField->code;
    $user = User::factory()->create();

    $post = Post::factory()->create(['custom_fields' => [$code => [$user->getKey()]]]);

    $post->update(['custom_fields' => []]);

    expect(activeTargetIds())->toBe([$user->getKey()]);
});

it('writes links for a record field with a definition even while the relationships feature is off', function (): void {
    $definition = writePathAuthorship();
    $code = $definition->fromField->code;
    $user = User::factory()->create();

    config('custom-fields.features')->disable(CustomFieldsFeature::SYSTEM_RELATIONSHIPS);

    $post = Post::factory()->create(['custom_fields' => [$code => [$user->getKey()]]]);

    expect(activeTargetIds())->toBe([$user->getKey()])
        ->and(CustomFieldValue::query()->where('custom_field_id', $definition->from_field_id)->count())->toBe(0)
        ->and($post->refresh()->getCustomFieldValue($definition->fromField))->toBe([$user->getKey()]);
});

it('keeps an undefined record field on the value row while the relationships feature is off', function (): void {
    $field = CustomField::factory()->create([
        'code' => 'write_path_unbound',
        'type' => 'record',
        'entity_type' => (new Post)->getMorphClass(),
        'custom_field_section_id' => sectionForEntity((new Post)->getMorphClass())->getKey(),
    ]);
    $user = User::factory()->create();

    config('custom-fields.features')->disable(CustomFieldsFeature::SYSTEM_RELATIONSHIPS);

    Post::factory()->create(['custom_fields' => [$field->code => [$user->getKey()]]]);

    expect(CustomFieldLink::query()->count())->toBe(0)
        ->and(CustomFieldValue::query()->where('custom_field_id', $field->getKey())->count())->toBe(1);
});

it('stamps the definition tenant on links written through the trait', function (): void {
    useTenantSchema(7);

    $definition = writePathAuthorship();
    $user = User::factory()->create();

    Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$user->getKey()]]]);

    expect(CustomFieldLink::query()->sole()->tenant_id)->toBe(7);
})->skip(
    fn (): bool => DB::connection()->getDriverName() === 'mysql',
    'MySQL commits DDL implicitly, so the added tenant columns would outlive the test transaction.',
);

it('rolls the new record back when a link target is rejected', function (): void {
    $definition = writePathAuthorship();

    $before = Post::query()->count();

    expect(fn (): Post => Post::factory()->create([
        'custom_fields' => [$definition->fromField->code => [999999]],
    ]))->toThrow(ValidationException::class);

    expect(Post::query()->count())->toBe($before)
        ->and(CustomFieldLink::query()->count())->toBe(0);
});

it('rolls an update back when a link target is rejected', function (): void {
    $definition = writePathAuthorship();
    $code = $definition->fromField->code;
    $user = User::factory()->create();

    $post = Post::factory()->create(['custom_fields' => [$code => [$user->getKey()]], 'title' => 'Kept']);

    expect(fn (): bool => $post->update([
        'title' => 'Rolled back',
        'custom_fields' => [$code => [999999]],
    ]))->toThrow(ValidationException::class);

    expect($post->fresh()->title)->toBe('Kept')
        ->and(activeTargetIds())->toBe([$user->getKey()]);
});

it('still clears a non-record field when the payload omits its key', function (string $type, mixed $value): void {
    $field = CustomField::factory()->create([
        'code' => 'omitted_'.$type,
        'type' => $type,
        'entity_type' => (new Post)->getMorphClass(),
        'custom_field_section_id' => sectionForEntity((new Post)->getMorphClass())->getKey(),
    ]);

    $post = Post::factory()->create(['custom_fields' => ['omitted_'.$type => $value]]);

    expect(Post::query()->findOrFail($post->getKey())->getCustomFieldValue($field))->toBe($value);

    $post->update(['custom_fields' => []]);

    expect(Post::query()->findOrFail($post->getKey())->getCustomFieldValue($field))->toBeNull();
})->with([
    'text' => ['text', 'kept'],
    'select' => ['select', 7],
]);
