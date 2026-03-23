<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Custom Field Value Cleanup on Delete', function (): void {
    it('deletes custom field values when entity is force-deleted', function (): void {
        $post = Post::factory()->create();
        $customField = CustomField::factory()->create([
            'entity_type' => Post::class,
        ]);

        CustomFieldValue::factory()->create([
            'entity_id' => $post->getKey(),
            'entity_type' => Post::class,
            'custom_field_id' => $customField->getKey(),
        ]);

        expect(CustomFieldValue::withoutGlobalScopes()->where('entity_id', $post->getKey())->count())->toBe(1);

        $post->forceDelete();

        expect(CustomFieldValue::withoutGlobalScopes()->where('entity_id', $post->getKey())->count())->toBe(0);
    });

    it('preserves custom field values when entity is soft-deleted', function (): void {
        $post = Post::factory()->create();
        $customField = CustomField::factory()->create([
            'entity_type' => Post::class,
        ]);

        CustomFieldValue::factory()->create([
            'entity_id' => $post->getKey(),
            'entity_type' => Post::class,
            'custom_field_id' => $customField->getKey(),
        ]);

        $post->delete();

        expect($post->trashed())->toBeTrue();
        expect(CustomFieldValue::withoutGlobalScopes()->where('entity_id', $post->getKey())->count())->toBe(1);
    });

    it('deletes custom field values when entity without soft deletes is deleted', function (): void {
        $comment = Comment::factory()->create();
        $customField = CustomField::factory()->create([
            'entity_type' => Comment::class,
        ]);

        CustomFieldValue::factory()->create([
            'entity_id' => $comment->getKey(),
            'entity_type' => Comment::class,
            'custom_field_id' => $customField->getKey(),
        ]);

        expect(CustomFieldValue::withoutGlobalScopes()->where('entity_id', $comment->getKey())->count())->toBe(1);

        $comment->delete();

        expect(CustomFieldValue::withoutGlobalScopes()->where('entity_id', $comment->getKey())->count())->toBe(0);
    });

    it('deletes multiple custom field values when entity is force-deleted', function (): void {
        $post = Post::factory()->create();
        $customFields = CustomField::factory()->count(3)->create([
            'entity_type' => Post::class,
        ]);

        foreach ($customFields as $customField) {
            CustomFieldValue::factory()->create([
                'entity_id' => $post->getKey(),
                'entity_type' => Post::class,
                'custom_field_id' => $customField->getKey(),
            ]);
        }

        expect(CustomFieldValue::withoutGlobalScopes()->where('entity_id', $post->getKey())->count())->toBe(3);

        $post->forceDelete();

        expect(CustomFieldValue::withoutGlobalScopes()->where('entity_id', $post->getKey())->count())->toBe(0);
    });
});

describe('Guarded Model Support', function (): void {
    it('recognizes custom_fields as fillable via isFillable override', function (): void {
        $model = new GuardedTestModel;

        expect($model->isFillable('custom_fields'))->toBeTrue()
            ->and($model->getGuarded())->toBe(['id']);
    });

    it('allows mass assignment of custom_fields on guarded models', function (): void {
        $model = new GuardedTestModel;
        $model->fill([
            'title' => 'Test Title',
            'custom_fields' => ['field_one' => 'value_one'],
        ]);

        expect($model->title)->toBe('Test Title');
    });

    it('stores custom_fields in temp storage when filling a guarded model', function (): void {
        $model = new GuardedTestModel;
        $model->fill(['custom_fields' => ['test_field' => 'test_value']]);

        expect($model->custom_fields)->toBe(['test_field' => 'test_value']);
    });
});

class GuardedTestModel extends Model implements HasCustomFields
{
    use UsesCustomFields;

    protected $table = 'posts';

    protected $guarded = ['id'];
}
