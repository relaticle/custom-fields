<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\CustomFieldType;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\CustomField;

function createTestModelTable()
{
    app()['db']->connection()->getSchemaBuilder()->create('test_models', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
}

it('handles custom fields from fillable array', function () {
    $testModel = new TestModel;

    // Test that custom_fields is recognized as fillable via isFillable override
    expect($testModel->isFillable('custom_fields'))->toBeTrue();
});

it('returns empty value when custom field has no value', function () {
    createTestModelTable();

    $testModel = TestModel::create(['name' => 'Test Model']);

    $customField = CustomField::create([
        'name' => 'Test Field',
        'code' => 'test_field',
        'type' => CustomFieldType::TEXT,
        'entity_type' => 'TestModel',
        'active' => true,
        'settings' => json_encode(['encrypted' => false]),
    ]);
    $customField = $customField->fresh(); // Refresh to apply casts

    $value = $testModel->getCustomFieldValue($customField);

    expect($value)->toBeNull();
});

it('adds custom_fields to fillable on guarded models', function () {
    $model = new GuardedTestModel;

    expect($model->isFillable('custom_fields'))->toBeTrue()
        ->and($model->getGuarded())->toBe(['id']);
});

it('does not break mass assignment of other attributes on guarded models', function (): void {
    // A model with $guarded = ['id'] should still allow mass assignment of other columns
    $model = new GuardedTestModel;
    $model->fill([
        'title' => 'Test Title',
        'custom_fields' => ['field_one' => 'value_one'],
    ]);

    expect($model->title)->toBe('Test Title');
});

it('stores custom_fields in temp storage when filling a guarded model', function () {
    createTestModelTable();

    $model = new GuardedTestModel;
    $model->fill(['custom_fields' => ['test_field' => 'test_value']]);

    expect($model->custom_fields)->toBe(['test_field' => 'test_value']);
});

class GuardedTestModel extends Model
{
    use UsesCustomFields;

    protected $table = 'test_models';

    protected $guarded = ['id'];

    public function getMorphClass()
    {
        return 'guarded_test_model';
    }
}

class TestModel extends Model
{
    use UsesCustomFields;

    protected $fillable = ['name'];

    public function getMorphClass()
    {
        return 'test_model';
    }
}
