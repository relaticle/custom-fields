<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Services\ModelAttributeDiscoveryService;
use Relaticle\CustomFields\Tests\Fixtures\Models\JsonbColumnModel;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\SecondConnectionModel;

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

it('excludes jsonb columns on postgres', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('jsonb is a Postgres-only column type.');
    }

    Schema::create('jsonb_column_models', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->jsonb('payload');
    });

    $attributes = $this->service->getAttributes(JsonbColumnModel::class);

    expect($attributes->has('title'))->toBeTrue()
        ->and($attributes->has('payload'))->toBeFalse();

    Schema::dropIfExists('jsonb_column_models');
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

it("discovers columns from the model's own connection instead of the default connection", function (): void {
    config()->set('database.connections.second', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    app('db')->connection('second')->getSchemaBuilder()->create(
        'second_connection_models',
        function (Blueprint $table): void {
            $table->id();
            $table->string('only_on_second_connection');
        }
    );

    $attributes = $this->service->getAttributes(SecondConnectionModel::class);

    expect($attributes->has('only_on_second_connection'))->toBeTrue();
});
