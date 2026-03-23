<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Fillable Model Support', function (): void {
    it('includes custom_fields in getFillable for models with $fillable', function (): void {
        $model = new FillableTestModel;

        expect($model->getFillable())->toContain('custom_fields');
    });

    it('recognizes custom_fields as fillable via isFillable on fillable models', function (): void {
        $model = new FillableTestModel;

        expect($model->isFillable('custom_fields'))->toBeTrue();
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

    it('allows custom_fields to be filled on a guarded model', function (): void {
        $model = new GuardedTestModel;
        $model->fill(['custom_fields' => ['test_field' => 'test_value']]);

        expect($model->custom_fields)->toBe(['test_field' => 'test_value']);
    });
});

class FillableTestModel extends Model implements HasCustomFields
{
    use UsesCustomFields;

    protected $table = 'posts';

    protected $fillable = ['title'];
}

class GuardedTestModel extends Model implements HasCustomFields
{
    use UsesCustomFields;

    protected $table = 'posts';

    protected $guarded = ['id'];
}
