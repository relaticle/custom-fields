<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\Relationships\LinkWriter;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;

function cardinalityPairing(RelationshipCardinality $cardinality, bool $symmetric = false): CustomFieldRelationship
{
    registerPostLookupEntity();

    $section = sectionForEntity((new Post)->getMorphClass());

    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'cardinality_ownership',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: $cardinality,
        isSymmetric: $symmetric,
        fromField: new FieldSlotData(name: 'Owned Post', sectionId: $section->getKey()),
        toField: $symmetric ? null : new FieldSlotData(name: 'Owning Post', sectionId: $section->getKey()),
    ));
}

function cardinalityAuthorship(): CustomFieldRelationship
{
    registerPostLookupEntity();

    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'cardinality_authorship',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToOne,
        fromField: new FieldSlotData(name: 'Author', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
        toField: new FieldSlotData(name: 'Posts', sectionId: sectionForEntity((new User)->getMorphClass())->getKey()),
    ));
}

/**
 * @return array<int, string>
 */
function cardinalityErrors(Closure $write): array
{
    try {
        $write();
    } catch (ValidationException $validationException) {
        return array_map(strval(...), Arr::flatten($validationException->errors()));
    }

    return [];
}

it('rejects two ids on a single from side', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::ManyToOne);
    [$first, $second] = Post::factory()->count(2)->create();

    $errors = cardinalityErrors(fn (): Post => Post::factory()->create([
        'custom_fields' => [$definition->fromField->code => [$first->getKey(), $second->getKey()]],
    ]));

    expect($errors)->toBe(['This relationship holds a single record.'])
        ->and(CustomFieldLink::query()->count())->toBe(0);
});

it('rejects two ids on a single to side', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::OneToMany);
    [$first, $second] = Post::factory()->count(2)->create();

    $errors = cardinalityErrors(fn (): Post => Post::factory()->create([
        'custom_fields' => [$definition->toField->code => [$first->getKey(), $second->getKey()]],
    ]));

    expect($errors)->toBe(['This relationship holds a single record.'])
        ->and(CustomFieldLink::query()->count())->toBe(0);
});

it('accepts two ids on a many side of the same definition', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::OneToMany);
    [$first, $second] = Post::factory()->count(2)->create();

    $post = Post::factory()->create([
        'custom_fields' => [$definition->fromField->code => [$first->getKey(), $second->getKey()]],
    ]);

    expect($post->getCustomFieldValue($definition->fromField))->toBe([$first->getKey(), $second->getKey()]);
});

it('accepts two ids on both sides of a many to many relationship', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::ManyToMany);
    [$first, $second] = Post::factory()->count(2)->create();

    $from = Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$first->getKey(), $second->getKey()]]]);
    $to = Post::factory()->create(['custom_fields' => [$definition->toField->code => [$first->getKey(), $second->getKey()]]]);

    expect($from->getCustomFieldValue($definition->fromField))->toBe([$first->getKey(), $second->getKey()])
        ->and($to->getCustomFieldValue($definition->toField))->toBe([$first->getKey(), $second->getKey()]);
});

it('names the record holding a taken single end when writing from the from side', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::OneToOne);
    $code = $definition->fromField->code;

    $target = Post::factory()->create(['title' => 'Taken Target']);
    Post::factory()->create(['title' => 'First Owner', 'custom_fields' => [$code => [$target->getKey()]]]);

    $errors = cardinalityErrors(fn (): Post => Post::factory()->create([
        'title' => 'Second Owner',
        'custom_fields' => [$code => [$target->getKey()]],
    ]));

    expect($errors)->toBe(['Taken Target is already linked to First Owner. Confirm the replacement to move it.'])
        ->and(CustomFieldLink::query()->active()->count())->toBe(1);
});

it('names the record holding a taken single end when writing from the to side', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::ManyToOne);

    $target = Post::factory()->create(['title' => 'Single Holder']);
    Post::factory()->create(['title' => 'First Owned', 'custom_fields' => [$definition->toField->code => [$target->getKey()]]]);

    $errors = cardinalityErrors(fn (): Post => Post::factory()->create([
        'title' => 'Second Owned',
        'custom_fields' => [$definition->toField->code => [$target->getKey()]],
    ]));

    expect($errors)->toBe(['Single Holder is already linked to First Owned. Confirm the replacement to move it.'])
        ->and(CustomFieldLink::query()->active()->count())->toBe(1);
});

it('lets a many end hold the same record twice over', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::ManyToMany);
    $code = $definition->fromField->code;

    $target = Post::factory()->create();
    Post::factory()->create(['custom_fields' => [$code => [$target->getKey()]]]);

    $second = Post::factory()->create(['custom_fields' => [$code => [$target->getKey()]]]);

    expect($second->getCustomFieldValue($definition->fromField))->toBe([$target->getKey()])
        ->and(CustomFieldLink::query()->active()->count())->toBe(2);
});

it('replaces the holder when the payload confirms it', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::OneToOne);
    $code = $definition->fromField->code;

    $target = Post::factory()->create(['title' => 'Taken Target']);
    $first = Post::factory()->create(['title' => 'First Owner', 'custom_fields' => [$code => [$target->getKey()]]]);

    $second = Post::factory()->create([
        'title' => 'Second Owner',
        'custom_fields' => [$code => ['ids' => [$target->getKey()], 'replace' => true]],
    ]);

    expect($second->getCustomFieldValue($definition->fromField))->toBe([$target->getKey()])
        ->and($first->getCustomFieldValue($definition->fromField))->toBe([])
        ->and(CustomFieldLink::query()->active()->count())->toBe(1)
        ->and(CustomFieldLink::query()->whereNotNull('active_until')->count())->toBe(1);
});

it('lets a record replace its own single link without confirmation', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::ManyToOne);
    $code = $definition->fromField->code;

    [$first, $second] = Post::factory()->count(2)->create();
    $post = Post::factory()->create(['custom_fields' => [$code => [$first->getKey()]]]);

    $post->update(['custom_fields' => [$code => [$second->getKey()]]]);

    expect($post->getCustomFieldValue($definition->fromField))->toBe([$second->getKey()]);
});

it('rejects two ids on a symmetric single relationship', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::OneToOne, symmetric: true);
    [$first, $second] = Post::factory()->count(2)->create();

    $errors = cardinalityErrors(fn (): Post => Post::factory()->create([
        'custom_fields' => [$definition->fromField->code => [$first->getKey(), $second->getKey()]],
    ]));

    expect($errors)->toBe(['This relationship holds a single record.']);
});

it('names the holder of a taken symmetric end', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::OneToOne, symmetric: true);
    $code = $definition->fromField->code;

    $target = Post::factory()->create(['title' => 'Taken Partner']);
    Post::factory()->create(['title' => 'First Partner', 'custom_fields' => [$code => [$target->getKey()]]]);

    $errors = cardinalityErrors(fn (): Post => Post::factory()->create([
        'title' => 'Second Partner',
        'custom_fields' => [$code => [$target->getKey()]],
    ]));

    expect($errors)->toBe(['Taken Partner is already linked to First Partner. Confirm the replacement to move it.']);
});

it('drops the rejected payload instead of retrying it on the next save', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::ManyToOne);
    $code = $definition->fromField->code;

    [$first, $second] = Post::factory()->count(2)->create();
    $post = Post::factory()->create(['title' => 'Rejected']);

    $errors = cardinalityErrors(fn (): bool => $post->update([
        'custom_fields' => [$code => [$first->getKey(), $second->getKey()]],
    ]));

    $post->update(['title' => 'Retried']);

    expect($errors)->toBe(['This relationship holds a single record.'])
        ->and($post->fresh()->title)->toBe('Retried')
        ->and(CustomFieldLink::query()->count())->toBe(0);
});

it('names the holder by end, not by an id two entity types share', function (): void {
    $definition = cardinalityAuthorship();

    [$holder, $writer] = User::factory()->count(2)->create();

    $posts = Post::factory()->count((int) $holder->getKey() + 1)->create();
    $taken = $posts->first();
    $decoy = $posts->firstWhere('id', $holder->getKey());

    $taken->update(['title' => 'Taken Post']);
    $decoy->update(['title' => 'Decoy Post']);

    app(LinkWriter::class)->apply($taken, $definition->fromField, [$holder->getKey()]);

    $errors = cardinalityErrors(fn (): mixed => app(LinkWriter::class)
        ->apply($writer, $definition->toField, [$decoy->getKey(), $taken->getKey()]));

    expect($errors)->toBe([sprintf('Taken Post is already linked to %s. Confirm the replacement to move it.', $holder->getKey())]);
});

it('reports the single-record message through the panel form', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::ManyToOne);
    $code = $definition->fromField->code;

    [$first, $second] = Post::factory()->count(2)->create();
    $post = Post::factory()->create();

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm([
            'title' => $post->title,
            'author_id' => $post->author_id,
            'rating' => $post->rating,
            'custom_fields' => [$code => [$first->getKey(), $second->getKey()]],
        ])
        ->call('save')
        ->assertHasFormErrors(['custom_fields.'.$code]);

    expect(CustomFieldLink::query()->count())->toBe(0);
});

it('reports a single-side overflow once through the panel form', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::ManyToOne);
    $code = $definition->fromField->code;

    [$first, $second] = Post::factory()->count(2)->create();
    $post = Post::factory()->create();

    $form = livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm([
            'title' => $post->title,
            'author_id' => $post->author_id,
            'rating' => $post->rating,
            'custom_fields' => [$code => [$first->getKey(), $second->getKey()]],
        ])
        ->call('save');

    expect($form->instance()->getErrorBag()->get('data.custom_fields.'.$code))
        ->toBe(['This relationship holds a single record.']);
});

it('reports the holder message through the panel form', function (): void {
    $definition = cardinalityPairing(RelationshipCardinality::OneToOne);
    $code = $definition->fromField->code;

    $target = Post::factory()->create(['title' => 'Taken Target']);
    Post::factory()->create(['title' => 'First Owner', 'custom_fields' => [$code => [$target->getKey()]]]);
    $post = Post::factory()->create(['title' => 'Second Owner']);

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm([
            'title' => $post->title,
            'author_id' => $post->author_id,
            'rating' => $post->rating,
            'custom_fields' => [$code => [$target->getKey()]],
        ])
        ->call('save')
        ->assertHasFormErrors([
            'custom_fields.'.$code => 'Taken Target is already linked to First Owner. Confirm the replacement to move it.',
        ]);

    expect(CustomFieldLink::query()->active()->count())->toBe(1);
});
