<?php

declare(strict_types=1);

use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;

it('wires the Post belongsToMany Tag fixture', function () {
    $post = Post::factory()->create();
    $tag = Tag::factory()->create();
    $post->tagModels()->attach($tag);

    expect($post->fresh()->tagModels->pluck('id')->all())->toBe([$tag->id]);
});
