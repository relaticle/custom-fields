<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Relaticle\CustomFields\Exceptions\UnsupportedThroughRelationException;
use Relaticle\CustomFields\Support\ThroughRelationResolver;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

it('classifies a to-one relation to a model with custom fields as supported', function (string $modelClass, string $relation, string $expected): void {
    $resolved = app(ThroughRelationResolver::class)->resolve(new $modelClass, $relation);

    expect($resolved)->toBeInstanceOf($expected);
})->with([
    'belongs to' => [Comment::class, 'post', BelongsTo::class],
    'has one' => [User::class, 'post', HasOne::class],
    'morph one' => [Post::class, 'featuredComment', MorphOne::class],
]);

it('rejects a relation the row model does not have', function (): void {
    expect(fn () => app(ThroughRelationResolver::class)->resolve(new Comment, 'publisher'))
        ->toThrow(UnsupportedThroughRelationException::class, 'has no relation named `publisher`');
});

it('rejects a method that is not a relation', function (): void {
    expect(fn () => app(ThroughRelationResolver::class)->resolve(new Comment, 'getTable'))
        ->toThrow(UnsupportedThroughRelationException::class, 'has no relation named `getTable`');
});

it('rejects a to-many relation', function (string $modelClass, string $relation, string $type): void {
    expect(fn () => app(ThroughRelationResolver::class)->resolve(new $modelClass, $relation))
        ->toThrow(UnsupportedThroughRelationException::class, sprintf('is a %s', $type));
})->with([
    'has many' => [User::class, 'posts', 'HasMany'],
    'belongs to many' => [Post::class, 'tagModels', 'BelongsToMany'],
]);

it('rejects a polymorphic to-one relation', function (): void {
    expect(fn () => app(ThroughRelationResolver::class)->resolve(new Comment, 'commentable'))
        ->toThrow(UnsupportedThroughRelationException::class, 'is a MorphTo');
});

it('rejects a relation whose target has no custom fields', function (): void {
    expect(fn () => app(ThroughRelationResolver::class)->resolve(new Post, 'author'))
        ->toThrow(UnsupportedThroughRelationException::class, 'does not implement HasCustomFields');
});

it('names the model and the relation in every rejection', function (): void {
    expect(fn () => app(ThroughRelationResolver::class)->resolve(new Post, 'author'))
        ->toThrow(UnsupportedThroughRelationException::class, '`author` on `'.Post::class.'`');
});
