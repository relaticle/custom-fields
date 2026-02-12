<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function deleteEntityWithoutEvents(string $table, mixed $id): void
{
    DB::table($table)->where('id', $id)->delete();
}

it('reports no orphaned values when none exist', function (): void {
    $this->artisan('custom-fields:cleanup-orphaned-values', ['--force' => true])
        ->expectsOutput('No custom field values found.')
        ->assertSuccessful();
});

it('reports no orphaned values when all entities exist', function (): void {
    $post = Post::factory()->create();
    $customField = CustomField::factory()->create(['entity_type' => Post::class]);

    CustomFieldValue::factory()->create([
        'entity_id' => $post->getKey(),
        'entity_type' => Post::class,
        'custom_field_id' => $customField->getKey(),
    ]);

    $this->artisan('custom-fields:cleanup-orphaned-values', ['--force' => true])
        ->expectsOutput('No orphaned custom field values found.')
        ->assertSuccessful();
});

it('detects orphaned values in dry-run mode without deleting', function (): void {
    $post = Post::factory()->create();
    $customField = CustomField::factory()->create(['entity_type' => Post::class]);

    CustomFieldValue::factory()->create([
        'entity_id' => $post->getKey(),
        'entity_type' => Post::class,
        'custom_field_id' => $customField->getKey(),
    ]);

    deleteEntityWithoutEvents('posts', $post->getKey());

    $this->artisan('custom-fields:cleanup-orphaned-values', ['--dry-run' => true])
        ->assertSuccessful();

    expect(CustomFieldValue::withoutGlobalScopes()->count())->toBe(1);
});

it('deletes orphaned values when entity is hard-deleted', function (): void {
    $post = Post::factory()->create();
    $customField = CustomField::factory()->create(['entity_type' => Post::class]);

    CustomFieldValue::factory()->create([
        'entity_id' => $post->getKey(),
        'entity_type' => Post::class,
        'custom_field_id' => $customField->getKey(),
    ]);

    deleteEntityWithoutEvents('posts', $post->getKey());

    $this->artisan('custom-fields:cleanup-orphaned-values', ['--force' => true])
        ->assertSuccessful();

    expect(CustomFieldValue::withoutGlobalScopes()->count())->toBe(0);
});

it('preserves values for soft-deleted entities', function (): void {
    $post = Post::factory()->create();
    $customField = CustomField::factory()->create(['entity_type' => Post::class]);

    CustomFieldValue::factory()->create([
        'entity_id' => $post->getKey(),
        'entity_type' => Post::class,
        'custom_field_id' => $customField->getKey(),
    ]);

    $post->delete();

    $this->artisan('custom-fields:cleanup-orphaned-values', ['--force' => true])
        ->expectsOutput('No orphaned custom field values found.')
        ->assertSuccessful();

    expect(CustomFieldValue::withoutGlobalScopes()->count())->toBe(1);
});

it('deletes orphaned values for entities without soft deletes', function (): void {
    $comment = Comment::factory()->create();
    $customField = CustomField::factory()->create(['entity_type' => Comment::class]);

    CustomFieldValue::factory()->create([
        'entity_id' => $comment->getKey(),
        'entity_type' => Comment::class,
        'custom_field_id' => $customField->getKey(),
    ]);

    deleteEntityWithoutEvents('comments', $comment->getKey());

    $this->artisan('custom-fields:cleanup-orphaned-values', ['--force' => true])
        ->assertSuccessful();

    expect(CustomFieldValue::withoutGlobalScopes()->count())->toBe(0);
});

it('only deletes orphaned values and preserves valid ones', function (): void {
    $activePost = Post::factory()->create();
    $deletedPost = Post::factory()->create();
    $customField = CustomField::factory()->create(['entity_type' => Post::class]);

    CustomFieldValue::factory()->create([
        'entity_id' => $activePost->getKey(),
        'entity_type' => Post::class,
        'custom_field_id' => $customField->getKey(),
    ]);

    CustomFieldValue::factory()->create([
        'entity_id' => $deletedPost->getKey(),
        'entity_type' => Post::class,
        'custom_field_id' => $customField->getKey(),
    ]);

    deleteEntityWithoutEvents('posts', $deletedPost->getKey());

    $this->artisan('custom-fields:cleanup-orphaned-values', ['--force' => true])
        ->assertSuccessful();

    expect(CustomFieldValue::withoutGlobalScopes()->count())->toBe(1);
    expect(
        CustomFieldValue::withoutGlobalScopes()
            ->where('entity_id', $activePost->getKey())
            ->exists()
    )->toBeTrue();
});
