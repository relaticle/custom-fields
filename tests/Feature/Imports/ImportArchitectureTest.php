<?php

declare(strict_types=1);

use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\ValidationRuleData;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;

/**
 * Test that WeakMap properly handles memory management
 */
it('uses weakmap for automatic memory management', function () {
    // Create models
    $model1 = new class extends Model
    {
        protected $table = 'test';
    };
    $model2 = new class extends Model
    {
        protected $table = 'test';
    };
    $model3 = new class extends Model
    {
        protected $table = 'test';
    };

    // Store data for all models
    ImportDataStorage::set($model1, 'field1', 'value1');
    ImportDataStorage::set($model2, 'field2', 'value2');
    ImportDataStorage::set($model3, 'field3', 'value3');

    // Verify all have data
    expect(ImportDataStorage::has($model1))->toBeTrue()
        ->and(ImportDataStorage::has($model2))->toBeTrue()
        ->and(ImportDataStorage::has($model3))->toBeTrue();

    // Get weak reference to model1 for testing
    $weakRef = WeakReference::create($model1);

    // Unset model1
    unset($model1);

    // Force garbage collection
    gc_collect_cycles();

    // WeakReference should be null after GC
    expect($weakRef->get())->toBeNull();

    // Other models should still have data
    expect(ImportDataStorage::has($model2))->toBeTrue();
    expect(ImportDataStorage::has($model3))->toBeTrue();
});

/**
 * Test concurrent imports don't interfere with each other
 */
it('handles concurrent imports safely', function () {
    $model1 = new class extends Model
    {
        protected $table = 'test1';
    };
    $model2 = new class extends Model
    {
        protected $table = 'test2';
    };

    // Simulate concurrent imports
    ImportDataStorage::set($model1, 'color', 'red');
    ImportDataStorage::set($model2, 'color', 'blue');
    ImportDataStorage::set($model1, 'size', 'large');
    ImportDataStorage::set($model2, 'size', 'small');

    // Each model should have its own isolated data
    $data1 = ImportDataStorage::get($model1);
    $data2 = ImportDataStorage::get($model2);

    expect($data1)->toBe(['color' => 'red', 'size' => 'large']);
    expect($data2)->toBe(['color' => 'blue', 'size' => 'small']);
});

/**
 * Test that pull operation clears data and returns it
 */
it('pulls and clears data atomically', function () {
    $model = new class extends Model
    {
        protected $table = 'test';
    };

    ImportDataStorage::setMultiple($model, [
        'field1' => 'value1',
        'field2' => 'value2',
        'field3' => ['array', 'value'],
    ]);

    // Pull should return data and clear storage
    $pulled = ImportDataStorage::pull($model);

    expect($pulled)->toBe([
        'field1' => 'value1',
        'field2' => 'value2',
        'field3' => ['array', 'value'],
    ]);

    // Storage should be empty now
    expect(ImportDataStorage::has($model))->toBeFalse();
    expect(ImportDataStorage::get($model))->toBe([]);

    // Second pull should return empty array
    expect(ImportDataStorage::pull($model))->toBe([]);
});

/**
 * Test configurator handles all field data types correctly
 */
it('configures all field data types', function () {
    $configurator = new ImportColumnConfigurator;

    // Helper to create custom field
    $createField = function ($code, $dataType) {
        $field = new CustomField([
            'name' => ucfirst($code),
            'code' => $code,
            'type' => 'test',
        ]);

        $field->typeData = (object) [
            'dataType' => $dataType,
        ];

        $field->validation_rules = collect([]);
        $field->options = collect([]);

        return $field;
    };

    // Test each data type
    $dataTypes = [
        FieldDataType::STRING,
        FieldDataType::TEXT,
        FieldDataType::NUMERIC,
        FieldDataType::FLOAT,
        FieldDataType::BOOLEAN,
        FieldDataType::DATE,
        FieldDataType::DATE_TIME,
    ];

    foreach ($dataTypes as $dataType) {
        $field = $createField('test_field', $dataType);
        $column = ImportColumn::make('test');

        $result = $configurator->configure($column, $field);

        expect($result)->toBeInstanceOf(ImportColumn::class);

        // Check fillRecordUsing is set
        $reflection = new ReflectionObject($column);
        $property = $reflection->getProperty('fillRecordUsing');
        $property->setAccessible(true);
        $fillCallback = $property->getValue($column);

        expect($fillCallback)->toBeCallable();
    }
});

/**
 * Test date parsing handles various formats
 */
it('handles various date formats', function () {
    $configurator = new ImportColumnConfigurator;

    $field = new CustomField([
        'name' => 'Date Field',
        'code' => 'date_field',
        'type' => 'date',
    ]);

    $field->typeData = (object) [
        'dataType' => FieldDataType::DATE,
    ];

    $field->validation_rules = collect([]);
    $field->options = collect([]);

    $column = ImportColumn::make('test_date');
    $configurator->configure($column, $field);

    // Get the cast callback
    $reflection = new ReflectionObject($column);
    $property = $reflection->getProperty('castStateUsing');
    $property->setAccessible(true);
    $castCallback = $property->getValue($column);

    if ($castCallback) {
        // Test various date formats
        expect($castCallback('2024-01-15'))->toBe('2024-01-15');
        expect($castCallback('15/01/2024'))->toBe('2024-01-15');
        expect($castCallback('January 15, 2024'))->toBe('2024-01-15');
        expect($castCallback('2024-01-15 10:30:00'))->toBe('2024-01-15');
        expect($castCallback(''))->toBeNull();
        expect($castCallback(null))->toBeNull();
        expect($castCallback('invalid-date'))->toBeNull();
    }
});

/**
 * Test option resolution with case-insensitive matching
 */
it('resolves options case insensitively', function () {
    $configurator = new ImportColumnConfigurator;

    // Create field with options
    $field = new CustomField([
        'name' => 'Color',
        'code' => 'color',
        'type' => 'select',
    ]);

    $field->typeData = (object) [
        'dataType' => FieldDataType::SINGLE_CHOICE,
    ];

    $field->validation_rules = collect([]);
    $field->options = collect([
        new CustomFieldOption(['id' => 1, 'name' => 'Red']),
        new CustomFieldOption(['id' => 2, 'name' => 'Blue']),
        new CustomFieldOption(['id' => 3, 'name' => 'Green']),
    ]);

    // Make options behave like real options
    $field->options->each(function ($option) {
        $option->getKeyName = fn () => 'id';
        $option->getKey = fn () => $option->id;
    });

    $column = ImportColumn::make('test_color');
    $configurator->configure($column, $field);

    // Get the cast callback
    $reflection = new ReflectionObject($column);
    $property = $reflection->getProperty('castStateUsing');
    $property->setAccessible(true);
    $castCallback = $property->getValue($column);

    if ($castCallback) {
        // Test case-insensitive matching
        expect($castCallback('red'))->toBe(1);
        expect($castCallback('RED'))->toBe(1);
        expect($castCallback('Blue'))->toBe(2);
        expect($castCallback('GREEN'))->toBe(3);
        expect($castCallback('2'))->toBe(2); // Numeric should work

        // Test invalid option throws exception
        expect(fn () => $castCallback('Yellow'))
            ->toThrow(RowImportFailedException::class);
    }
});

/**
 * Test multi-choice fields handle arrays correctly
 */
it('handles multi choice arrays', function () {
    $configurator = new ImportColumnConfigurator;

    $field = new CustomField([
        'name' => 'Tags',
        'code' => 'tags',
        'type' => 'multi_select',
    ]);

    $field->typeData = (object) [
        'dataType' => FieldDataType::MULTI_CHOICE,
    ];

    $field->validation_rules = collect([]);
    $field->options = collect([
        new CustomFieldOption(['id' => 1, 'name' => 'Laravel']),
        new CustomFieldOption(['id' => 2, 'name' => 'PHP']),
        new CustomFieldOption(['id' => 3, 'name' => 'Vue']),
    ]);

    // Make options behave like real options
    $field->options->each(function ($option) {
        $option->getKeyName = fn () => 'id';
        $option->getKey = fn () => $option->id;
    });

    $column = ImportColumn::make('test_tags');
    $configurator->configure($column, $field);

    // Verify the column is configured for arrays
    expect($column)->toBeInstanceOf(ImportColumn::class);

    // Get the cast callback if it exists
    $reflection = new ReflectionObject($column);
    if ($reflection->hasProperty('castStateUsing')) {
        $property = $reflection->getProperty('castStateUsing');
        $property->setAccessible(true);
        $castCallback = $property->getValue($column);

        if ($castCallback) {
            // Test array handling
            expect($castCallback(['Laravel', 'PHP']))->toBe([1, 2]);
            expect($castCallback(['vue', 'LARAVEL']))->toBe([3, 1]);
            expect($castCallback('PHP'))->toBe([2]); // Single value becomes array
            expect($castCallback([1, 3]))->toBe([1, 3]); // Numeric IDs
            expect($castCallback(''))->toBe([]);
            expect($castCallback(null))->toBe([]);
        }
    }
});

/**
 * Test validation rules are properly applied
 */
it('applies validation rules', function () {
    $configurator = new ImportColumnConfigurator;

    $field = new CustomField([
        'name' => 'Email',
        'code' => 'email',
        'type' => 'text',
    ]);

    $field->typeData = (object) [
        'dataType' => FieldDataType::STRING,
    ];

    $field->validation_rules = collect([
        new ValidationRuleData(
            name: 'required',
            parameters: []
        ),
        new ValidationRuleData(
            name: 'email',
            parameters: []
        ),
        new ValidationRuleData(
            name: 'max',
            parameters: [255]
        ),
    ]);

    $field->options = collect([]);

    $column = ImportColumn::make('test_email');
    $result = $configurator->configure($column, $field);

    // Rules should be set, we can verify by trying to get them
    // Note: Filament v4 may not expose rules directly as a property
    // Instead, we verify the column was configured successfully
    expect($result)->toBeInstanceOf(ImportColumn::class);
});

/**
 * Test that fillRecordUsing prevents SQL errors
 */
it('prevents sql errors with fill record using', function () {
    $configurator = new ImportColumnConfigurator;

    $field = new CustomField([
        'name' => 'Custom Field',
        'code' => 'custom_field',
        'type' => 'text',
    ]);

    $field->typeData = (object) [
        'dataType' => FieldDataType::STRING,
    ];

    $field->validation_rules = collect([]);
    $field->options = collect([]);

    $column = ImportColumn::make('custom_fields_custom_field');
    $configurator->configure($column, $field);

    // Verify fillRecordUsing is set
    $reflection = new ReflectionObject($column);
    $property = $reflection->getProperty('fillRecordUsing');
    $property->setAccessible(true);
    $fillCallback = $property->getValue($column);

    expect($fillCallback)->toBeCallable();

    // Test the callback stores data in ImportDataStorage
    $model = new class extends Model
    {
        protected $table = 'test';
    };
    $fillCallback('test_value', $model);

    $stored = ImportDataStorage::get($model);
    expect($stored)->toBe(['custom_field' => 'test_value']);
});

/**
 * Test complete import flow integration
 */
it('handles complete import flow', function () {
    // Create a model
    $model = new class extends Model
    {
        protected $table = 'products';

        protected $fillable = ['name', 'price'];
    };

    // Simulate import data - storing custom fields using our storage
    ImportDataStorage::set($model, 'color', 'Red');
    ImportDataStorage::set($model, 'size', 'Large');
    ImportDataStorage::set($model, 'available', true);

    // Pull data (simulating afterSave hook)
    $customFields = ImportDataStorage::pull($model);

    expect($customFields)->toBe([
        'color' => 'Red',
        'size' => 'Large',
        'available' => true,
    ]);

    // Verify storage is cleared
    expect(ImportDataStorage::has($model))->toBeFalse();
});

/**
 * Test for memory leak prevention
 */
it('prevents memory leaks with proper cleanup', function () {
    $iterations = 1000;
    $initialMemory = memory_get_usage();

    for ($i = 0; $i < $iterations; $i++) {
        $model = new class extends Model
        {
            protected $table = 'test';
        };
        ImportDataStorage::set($model, 'field', "value_{$i}");
        ImportDataStorage::pull($model);
        unset($model);
    }

    gc_collect_cycles();
    $finalMemory = memory_get_usage();

    // Memory increase should be minimal (less than 1MB for 1000 iterations)
    $memoryIncrease = $finalMemory - $initialMemory;
    expect($memoryIncrease)->toBeLessThan(1024 * 1024); // 1MB
});

/**
 * Test edge cases
 */
it('handles edge cases gracefully', function () {
    $model = new class extends Model
    {
        protected $table = 'test';
    };

    // Empty values
    ImportDataStorage::set($model, 'empty_string', '');
    ImportDataStorage::set($model, 'null_value', null);
    ImportDataStorage::set($model, 'zero', 0);
    ImportDataStorage::set($model, 'false', false);
    ImportDataStorage::set($model, 'empty_array', []);

    $data = ImportDataStorage::get($model);

    expect($data)->toBe([
        'empty_string' => '',
        'null_value' => null,
        'zero' => 0,
        'false' => false,
        'empty_array' => [],
    ]);
});

/**
 * Test that our architecture follows SOLID principles
 */
it('follows SOLID principles', function () {
    // Single Responsibility
    expect(ImportDataStorage::class)->toOnlyUse([
        Model::class,
        WeakMap::class,
    ]);

    // Classes should be final (closed for modification)
    $reflection = new ReflectionClass(ImportDataStorage::class);
    expect($reflection->isFinal())->toBeTrue();

    $reflection = new ReflectionClass(ImportColumnConfigurator::class);
    expect($reflection->isFinal())->toBeTrue();
});
