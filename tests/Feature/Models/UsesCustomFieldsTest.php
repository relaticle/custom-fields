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

    // Test that custom_fields is included in fillable
    expect($testModel->getFillable())->toContain('custom_fields');
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

it('does not override guarded when model uses guarded instead of fillable', function () {
    $model = new GuardedTestModel;

    expect($model->getFillable())->toBe([])
        ->and($model->getGuarded())->toBe(['id']);
});

class GuardedTestModel extends Model
{
    use UsesCustomFields;

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
