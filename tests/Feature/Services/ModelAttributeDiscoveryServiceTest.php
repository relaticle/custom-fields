<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Services\ModelAttributeDiscoveryService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

beforeEach(function (): void {
    ModelAttributeDiscoveryService::clearCache();
    $this->service = app(ModelAttributeDiscoveryService::class);
});

it('discovers attributes from Post model', function (): void {
    $attributes = $this->service->getAttributes(Post::class);

    expect($attributes)->not->toBeEmpty()
        ->and($attributes->has('title'))->toBeTrue()
        ->and($attributes->has('content'))->toBeTrue()
        ->and($attributes->has('rating'))->toBeTrue()
        ->and($attributes->has('is_published'))->toBeTrue()
        ->and($attributes->has('author_id'))->toBeTrue();
});

it('excludes sensitive and internal columns', function (): void {
    $attributes = $this->service->getAttributes(Post::class);

    expect($attributes->has('id'))->toBeFalse()
        ->and($attributes->has('created_at'))->toBeFalse()
        ->and($attributes->has('updated_at'))->toBeFalse()
        ->and($attributes->has('deleted_at'))->toBeFalse();
});

it('excludes columns cast to array or json', function (): void {
    $attributes = $this->service->getAttributes(Post::class);

    // Post model casts 'tags' and 'json_array_of_objects' to array
    expect($attributes->has('tags'))->toBeFalse()
        ->and($attributes->has('json_array_of_objects'))->toBeFalse();
});

it('maps column types to correct FieldDataType', function (): void {
    $attributes = $this->service->getAttributes(Post::class);

    expect($attributes->get('title')['data_type'])->toBe(FieldDataType::STRING)
        ->and($attributes->get('is_published')['data_type'])->toBe(FieldDataType::BOOLEAN);
});

it('returns attribute options formatted for select dropdowns', function (): void {
    $options = $this->service->getAttributeOptions(Post::class);

    expect($options)->toBeArray()
        ->and($options)->toHaveKey('title')
        ->and($options['title'])->toBe('Title');
});

it('returns correct data type for specific attribute', function (): void {
    expect($this->service->getAttributeDataType(Post::class, 'title'))->toBe(FieldDataType::STRING)
        ->and($this->service->getAttributeDataType(Post::class, 'is_published'))->toBe(FieldDataType::BOOLEAN)
        ->and($this->service->getAttributeDataType(Post::class, 'nonexistent'))->toBeNull();
});

it('generates human-readable labels from column names', function (): void {
    $attributes = $this->service->getAttributes(Post::class);

    expect($attributes->get('author_id')['label'])->toBe('Author Id')
        ->and($attributes->get('is_published')['label'])->toBe('Is Published');
});

it('caches results for repeated calls', function (): void {
    $first = $this->service->getAttributes(Post::class);
    $second = $this->service->getAttributes(Post::class);

    expect($first)->toBe($second);
});

it('clears cache correctly', function (): void {
    $this->service->getAttributes(Post::class);

    ModelAttributeDiscoveryService::clearCache();

    // Should not throw or error after clearing
    $attributes = $this->service->getAttributes(Post::class);
    expect($attributes)->not->toBeEmpty();
});

it('returns empty collection for non-existent entity type', function (): void {
    $attributes = $this->service->getAttributes('NonExistent\\Model\\Class');

    expect($attributes)->toBeEmpty();
});
