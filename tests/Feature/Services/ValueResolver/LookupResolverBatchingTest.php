<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\ValueResolver\LookupCache;
use Relaticle\CustomFields\Services\ValueResolver\LookupResolver;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(LookupCache::class)->flush();

    $section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create(['active' => true]);

    $this->field = CustomField::factory()->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'code' => 'parent_post',
        'name' => 'Parent Post',
        'type' => 'select',
        'lookup_type' => Post::class,
        'settings' => new CustomFieldSettingsData,
    ]);
});

it('fires one query on first call and zero on second call for the same ids', function (): void {
    $posts = Post::factory()->count(3)->create();
    $ids = $posts->pluck('id')->all();

    $resolver = app(LookupResolver::class);

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, '"posts"') || str_contains($query->sql, '`posts`')) {
            $queries++;
        }
    });

    $first = $resolver->resolveLookupValues($ids, $this->field);
    $second = $resolver->resolveLookupValues($ids, $this->field);

    expect($queries)->toBe(1)
        ->and($first->all())->toBe($posts->pluck('title')->all())
        ->and($second->all())->toBe($posts->pluck('title')->all());
});

it('fires one query for missing ids even when some are already cached', function (): void {
    $posts = Post::factory()->count(4)->create();
    $allIds = $posts->pluck('id')->all();

    $resolver = app(LookupResolver::class);
    $resolver->resolveLookupValues([$allIds[0], $allIds[1]], $this->field);

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, '"posts"') || str_contains($query->sql, '`posts`')) {
            $queries++;
        }
    });

    $resolver->resolveLookupValues($allIds, $this->field);

    expect($queries)->toBe(1);
});

it('returns titles in the order of submitted ids', function (): void {
    $a = Post::factory()->create(['title' => 'A']);
    $b = Post::factory()->create(['title' => 'B']);
    $c = Post::factory()->create(['title' => 'C']);

    $result = app(LookupResolver::class)->resolveLookupValues(
        [$c->id, $a->id, $b->id],
        $this->field,
    );

    expect($result->all())->toBe(['C', 'A', 'B']);
});

it('skips non-scalar lookup values without hitting the database', function (): void {
    Post::factory()->create();

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, '"posts"') || str_contains($query->sql, '`posts`')) {
            $queries++;
        }
    });

    $result = app(LookupResolver::class)
        ->resolveLookupValues([['nested' => 'object']], $this->field);

    expect($queries)->toBe(0)
        ->and($result->all())->toBe([]);
});

describe('scopeWithCustomFieldValues preloading', function (): void {
    it('preloads lookup titles so per-record resolver calls fire zero queries to the lookup model', function (): void {
        $targets = Post::factory()->count(3)->create();

        $hosts = Post::factory()->count(3)->create();

        foreach ($hosts as $index => $host) {
            CustomFieldValue::factory()->create([
                'custom_field_id' => $this->field->getKey(),
                'entity_type' => Post::class,
                'entity_id' => $host->getKey(),
                'integer_value' => $targets[$index]->getKey(),
            ]);
        }

        app(LookupCache::class)->flush();

        $loaded = Post::query()
            ->whereIn('id', $hosts->pluck('id'))
            ->withCustomFieldValues()
            ->get();

        $resolver = app(LookupResolver::class);

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, '"posts"') || str_contains($query->sql, '`posts`')) {
                $queries++;
            }
        });

        $titles = $loaded->map(function (Post $host) use ($resolver): string {
            $value = $host->getCustomFieldValue($this->field);

            return $resolver->resolveLookupValues([$value], $this->field)->first() ?? '';
        });

        expect($queries)->toBe(0)
            ->and($titles->all())->toBe($targets->pluck('title')->all());
    });
});
