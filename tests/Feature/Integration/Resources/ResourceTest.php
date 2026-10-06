<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

it('can retrieve Eloquent query for model', function (): void {
    expect(PostResource::getEloquentQuery())
        ->toBeInstanceOf(Builder::class)
        ->getModel()->toBeInstanceOf(Post::class);
});

it('can generate a slug based on the model name', function (): void {
    expect(PostResource::getSlug())
        ->toBe('posts');
});

it('can generate a label based on the model name', function (): void {
    expect(PostResource::getModelLabel())
        ->toBe('post');
});

it('can generate a plural label based on the model name and locale', function (): void {
    $originalLocale = app()->getLocale();

    app()->setLocale('en');
    expect(PostResource::getPluralModelLabel())
        ->toBe('posts');

    app()->setLocale('id');
    expect(PostResource::getPluralModelLabel())
        ->toBe('post');

    app()->setLocale($originalLocale);
});

it("can retrieve a record's title", function (): void {
    $post = Post::factory()->create();

    expect(PostResource::getRecordTitle($post))
        ->toBe($post->title);
});

it('can resolve record route binding', function (): void {
    $post = Post::factory()->create();

    expect(PostResource::resolveRecordRouteBinding($post->getKey()))
        ->toBeSameModel($post);
});

it("can retrieve a page's URL", function (): void {
    $post = Post::factory()->create();
    $resourceSlug = PostResource::getSlug();

    expect(PostResource::getUrl('create'))
        ->toContain($resourceSlug)
        ->toContain('create')
        ->and(PostResource::getUrl('edit', ['record' => $post]))
        ->toContain($resourceSlug)
        ->toContain(strval($post->getRouteKey()))
        ->and(PostResource::getUrl('index'))->toContain($resourceSlug)
        ->and(PostResource::getUrl('view', ['record' => $post]))
        ->toContain($resourceSlug)
        ->toContain(strval($post->getRouteKey()));
});

it("can retrieve a page's URL from its model", function (): void {
    $post = Post::factory()->create();

    expect(Filament::getResourceUrl($post, 'edit'))
        ->toEndWith(sprintf('/posts/%s/edit', $post->getKey()))
        ->and(Filament::getResourceUrl($post, 'view'))
        ->toEndWith('/posts/'.$post->getKey())
        ->and(Filament::getResourceUrl(Post::class, 'view', ['record' => $post]))
        ->toEndWith('/posts/'.$post->getKey())
        ->and(Filament::getResourceUrl(Post::class))
        ->toEndWith('/posts')
        ->and(Filament::getResourceUrl($post))
        ->toEndWith('/posts');
});
