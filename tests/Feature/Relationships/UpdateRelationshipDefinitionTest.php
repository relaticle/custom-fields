<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\Relationships\UpdateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

function reviewers(RelationshipCardinality $cardinality = RelationshipCardinality::ManyToMany): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'reviewers',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new User)->getMorphClass(),
        cardinality: $cardinality,
        fromField: new FieldSlotData(name: 'Reviewers', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
    ));
}

it('leaves the edges alone when the cardinality does not change', function (): void {
    $definition = reviewers();
    $reviewers = User::factory()->count(2)->create();

    Post::factory()->create(['custom_fields' => [$definition->fromField->code => $reviewers->modelKeys()]]);

    app(UpdateRelationshipDefinition::class)->execute($definition, RelationshipCardinality::ManyToMany);

    expect(CustomFieldLink::query()->active()->count())->toBe(2);
});

it('closes nothing when the cardinality widens', function (): void {
    $definition = reviewers(RelationshipCardinality::ManyToOne);
    $reviewer = User::factory()->create();

    Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$reviewer->getKey()]]]);

    app(UpdateRelationshipDefinition::class)->execute($definition, RelationshipCardinality::ManyToMany);

    expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToMany)
        ->and(CustomFieldLink::query()->active()->count())->toBe(1);
});

it('refuses to narrow an end without the keep-first confirmation', function (): void {
    $definition = reviewers();
    $reviewers = User::factory()->count(2)->create();

    Post::factory()->create(['custom_fields' => [$definition->fromField->code => $reviewers->modelKeys()]]);

    try {
        app(UpdateRelationshipDefinition::class)->execute($definition, RelationshipCardinality::ManyToOne);
    } catch (ValidationException) {
        expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToMany)
            ->and(CustomFieldLink::query()->active()->count())->toBe(2);

        return;
    }

    $this->fail('Narrowing was accepted without the keep-first confirmation.');
});

it('keeps the first edge of each holder on the narrowed end', function (): void {
    $definition = reviewers();
    $reviewers = User::factory()->count(3)->create();

    $first = Post::factory()->create(['custom_fields' => [$definition->fromField->code => $reviewers->modelKeys()]]);
    $second = Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$reviewers[2]->getKey(), $reviewers[1]->getKey()]]]);

    app(UpdateRelationshipDefinition::class)->execute($definition, RelationshipCardinality::ManyToOne, keepFirst: true);

    expect($definition->refresh()->cardinality)->toBe(RelationshipCardinality::ManyToOne)
        ->and($first->fresh()->getCustomFieldValue($definition->fromField))->toBe([$reviewers[0]->getKey()])
        ->and($second->fresh()->getCustomFieldValue($definition->fromField))->toBe([$reviewers[2]->getKey()])
        ->and(CustomFieldLink::query()->whereNotNull('active_until')->count())->toBe(3);
});

it('keeps one edge per record on either end of a symmetric definition', function (): void {
    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'related_posts',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Related Posts', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
    ));

    $posts = Post::factory()->count(3)->create();
    $posts[0]->update(['custom_fields' => [$definition->fromField->code => [$posts[1]->getKey(), $posts[2]->getKey()]]]);

    app(UpdateRelationshipDefinition::class)->execute($definition, RelationshipCardinality::OneToOne, keepFirst: true);

    expect(CustomFieldLink::query()->active()->count())->toBe(1)
        ->and($posts[0]->fresh()->getCustomFieldValue($definition->fromField))->toBe([$posts[1]->getKey()]);
});

it('keeps a record on one end from closing its own edge on the other', function (): void {
    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'reports_to',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
        fromField: new FieldSlotData(name: 'Reports To', sectionId: sectionForEntity((new Post)->getMorphClass())->getKey()),
    ));

    $code = $definition->fromField->code;
    [$a, $b, $c] = Post::factory()->count(3)->create();

    $a->update(['custom_fields' => [$code => [$b->getKey()]]]);
    $c->update(['custom_fields' => [$code => [$a->getKey()]]]);

    app(UpdateRelationshipDefinition::class)->execute($definition, RelationshipCardinality::OneToOne, keepFirst: true);

    expect(CustomFieldLink::query()->active()->count())->toBe(2)
        ->and($a->fresh()->getCustomFieldValue($definition->fromField))->toBe([$b->getKey()])
        ->and($c->fresh()->getCustomFieldValue($definition->fromField))->toBe([$a->getKey()]);
});
