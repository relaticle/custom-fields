<?php

declare(strict_types=1);

use Relaticle\CustomFields\Services\RelationConditionResolver;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;

it('wires the Post belongsToMany Tag fixture', function () {
    $post = Post::factory()->create();
    $tag = Tag::factory()->create();
    $post->tagModels()->attach($tag);

    expect($post->fresh()->tagModels->pluck('id')->all())->toBe([$tag->id]);
});

it('resolves related keys across a two-hop path', function () {
    $resolver = app(RelationConditionResolver::class);

    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();
    $post = Post::factory()->create();
    $post->tagModels()->attach([$tagA->id, $tagB->id]);
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    expect($resolver->resolveRelatedKeys($comment, 'post.tagModels'))
        ->toEqualCanonicalizing([(string) $tagA->id, (string) $tagB->id]);
});

it('returns an empty set when an intermediate relation is null', function () {
    $resolver = app(RelationConditionResolver::class);
    $comment = Comment::factory()->create(['post_id' => 999999]); // non-existent post, no FK constraint

    expect($resolver->resolveRelatedKeys($comment, 'post.tagModels'))->toBe([]);
});

it('returns an empty set when the terminal relation is empty', function () {
    $resolver = app(RelationConditionResolver::class);
    $post = Post::factory()->create(); // no tags attached
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    expect($resolver->resolveRelatedKeys($comment, 'post.tagModels'))->toBe([]);
});

it('reflects the terminal related model from a path', function () {
    $resolver = app(RelationConditionResolver::class);

    expect($resolver->resolveTerminalRelatedModel(Comment::class, 'post.tagModels'))->toBeInstanceOf(Tag::class);
});

it('returns null when reflecting an invalid path', function () {
    $resolver = app(RelationConditionResolver::class);

    expect($resolver->resolveTerminalRelatedModel(Comment::class, 'post.nonExistentRelation'))->toBeNull();
});
