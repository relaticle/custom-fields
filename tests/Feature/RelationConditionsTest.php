<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Services\RelationConditionResolver;
use Relaticle\CustomFields\Support\RelationConditionConfig;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;

it('shows when the related set matches, hides when it does not (the 3 AC cases)', function (): void {
    $matching = Tag::factory()->create();
    $other = Tag::factory()->create();

    $postWithMatch = Post::factory()->create();
    $postWithMatch->tagModels()->attach($matching);
    $commentMatch = Comment::factory()->create(['post_id' => $postWithMatch->id]);

    $postNoMatch = Post::factory()->create();
    $postNoMatch->tagModels()->attach($other);
    $commentNoMatch = Comment::factory()->create(['post_id' => $postNoMatch->id]);

    $postNoTags = Post::factory()->create();
    $commentNoTags = Comment::factory()->create(['post_id' => $postNoTags->id]);

    $visibility = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'post.tagModels',
            'operator' => VisibilityOperator::IS_IN,
            'value' => [$matching->id],
            'source' => ConditionSource::RelationAttribute,
        ]],
    ]);

    expect($visibility->evaluate([], $commentMatch))->toBeTrue()
        ->and($visibility->evaluate([], $commentNoMatch))->toBeFalse()
        ->and($visibility->evaluate([], $commentNoTags))->toBeFalse()
        ->and($visibility->evaluate([]))->toBeTrue(); // create form: fail-open
});

it('is_not_in shows for a member with no related records', function (): void {
    $tag = Tag::factory()->create();
    $postNoTags = Post::factory()->create();
    $comment = Comment::factory()->create(['post_id' => $postNoTags->id]);

    $visibility = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'post.tagModels',
            'operator' => VisibilityOperator::IS_NOT_IN,
            'value' => [$tag->id],
            'source' => ConditionSource::RelationAttribute,
        ]],
    ]);

    expect($visibility->evaluate([], $comment))->toBeTrue();
});

it('has a helper to detect relation-attribute conditions', function (): void {
    $visibility = VisibilityData::from([
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'post.tagModels',
            'operator' => VisibilityOperator::IS_IN,
            'value' => [1],
            'source' => ConditionSource::RelationAttribute,
        ]],
    ]);

    expect($visibility->hasRelationAttributeConditions())->toBeTrue();
});

it('wires the Post belongsToMany Tag fixture', function (): void {
    $post = Post::factory()->create();
    $tag = Tag::factory()->create();
    $post->tagModels()->attach($tag);

    expect($post->fresh()->tagModels->pluck('id')->all())->toBe([$tag->id]);
});

it('resolves related keys across a two-hop path', function (): void {
    $resolver = app(RelationConditionResolver::class);

    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();
    $post = Post::factory()->create();
    $post->tagModels()->attach([$tagA->id, $tagB->id]);
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    expect($resolver->resolveRelatedKeys($comment, 'post.tagModels'))
        ->toEqualCanonicalizing([(string) $tagA->id, (string) $tagB->id]);
});

it('returns an empty set when an intermediate relation is null', function (): void {
    $resolver = app(RelationConditionResolver::class);
    $comment = Comment::factory()->create(['post_id' => 999999]); // non-existent post, no FK constraint

    expect($resolver->resolveRelatedKeys($comment, 'post.tagModels'))->toBe([]);
});

it('returns an empty set when the terminal relation is empty', function (): void {
    $resolver = app(RelationConditionResolver::class);
    $post = Post::factory()->create(); // no tags attached
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    expect($resolver->resolveRelatedKeys($comment, 'post.tagModels'))->toBe([]);
});

it('reflects the terminal related model from a path', function (): void {
    $resolver = app(RelationConditionResolver::class);

    expect($resolver->resolveTerminalRelatedModel(Comment::class, 'post.tagModels'))->toBeInstanceOf(Tag::class);
});

it('returns null when reflecting an invalid path', function (): void {
    $resolver = app(RelationConditionResolver::class);

    expect($resolver->resolveTerminalRelatedModel(Comment::class, 'post.nonExistentRelation'))->toBeNull();
});

it('reads per-entity relation and scoping config', function (): void {
    config()->set('custom-fields.visibility', [
        'restrict_to_configured' => true,
        'sources' => [
            Comment::class => [
                'relations' => ['post.tagModels' => 'Post → Tags'],
            ],
        ],
    ]);

    $config = app(RelationConditionConfig::class);

    expect($config->restrictToConfigured())->toBeTrue()
        ->and($config->relationsFor(Comment::class))->toBe(['post.tagModels' => 'Post → Tags'])
        ->and($config->isRelationSourceAvailable(Comment::class))->toBeTrue()
        ->and($config->isRelationSourceAvailable(Post::class))->toBeFalse()
        ->and($config->isModelAttributeSourceAvailable(Post::class))->toBeFalse(); // restricted + no attributes
});

it('defaults to legacy behavior (model-attribute available everywhere) when not restricted', function (): void {
    config()->set('custom-fields.visibility', ['restrict_to_configured' => false, 'sources' => []]);

    expect(app(RelationConditionConfig::class)->isModelAttributeSourceAvailable(Post::class))->toBeTrue();
});

it('does not expose the relation source unless a path is configured, even when unrestricted', function (): void {
    config()->set('custom-fields.visibility', ['restrict_to_configured' => false, 'sources' => []]);

    $config = app(RelationConditionConfig::class);

    expect($config->isRelationSourceAvailable(Post::class))->toBeFalse()
        ->and($config->isModelAttributeSourceAvailable(Post::class))->toBeTrue();
});
