<?php

declare(strict_types=1);

use Relaticle\CustomFields\Services\ValueResolver\LookupCache;

it('returns null when the id is not cached', function (): void {
    $cache = new LookupCache;

    expect($cache->titleFor('App\Models\User', 1))->toBeNull();
});

it('returns stored titles after remember()', function (): void {
    $cache = new LookupCache;
    $cache->remember('App\Models\User', [1 => 'Alice', 2 => 'Bob']);

    expect($cache->titleFor('App\Models\User', 1))->toBe('Alice')
        ->and($cache->titleFor('App\Models\User', 2))->toBe('Bob')
        ->and($cache->titleFor('App\Models\User', 3))->toBeNull();
});

it('merges subsequent remember() calls instead of overwriting', function (): void {
    $cache = new LookupCache;
    $cache->remember('App\Models\User', [1 => 'Alice']);
    $cache->remember('App\Models\User', [2 => 'Bob']);

    expect($cache->titleFor('App\Models\User', 1))->toBe('Alice')
        ->and($cache->titleFor('App\Models\User', 2))->toBe('Bob');
});

it('scopes cache entries by lookup type', function (): void {
    $cache = new LookupCache;
    $cache->remember('App\Models\User', [1 => 'Alice']);
    $cache->remember('App\Models\Post', [1 => 'Post One']);

    expect($cache->titleFor('App\Models\User', 1))->toBe('Alice')
        ->and($cache->titleFor('App\Models\Post', 1))->toBe('Post One');
});

it('returns only uncached ids from missing()', function (): void {
    $cache = new LookupCache;
    $cache->remember('App\Models\User', [1 => 'Alice', 2 => 'Bob']);

    expect($cache->missing('App\Models\User', [1, 2, 3, 4]))->toBe([3, 4])
        ->and($cache->missing('App\Models\User', [1, 2]))->toBe([])
        ->and($cache->missing('App\Models\Post', [1, 2]))->toBe([1, 2]);
});

it('deduplicates ids passed to missing()', function (): void {
    $cache = new LookupCache;

    expect($cache->missing('App\Models\User', [1, 1, 2, 2, 3]))->toBe([1, 2, 3]);
});

it('flushes all cached entries', function (): void {
    $cache = new LookupCache;
    $cache->remember('App\Models\User', [1 => 'Alice']);
    $cache->flush();

    expect($cache->titleFor('App\Models\User', 1))->toBeNull();
});
