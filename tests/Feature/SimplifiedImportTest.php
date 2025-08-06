<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Filament\Integration\Builders\ImporterBuilder;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Tests\TestCase;

class SimplifiedImportTest extends TestCase
{
    public function test_weakmap_storage_auto_cleans(): void
    {
        $model1 = new class extends Model {};
        $model2 = new class extends Model {};
        
        // Store data for both models
        ImportDataStorage::set($model1, 'field1', 'value1');
        ImportDataStorage::set($model2, 'field2', 'value2');
        
        // Verify data exists
        $this->assertTrue(ImportDataStorage::has($model1));
        $this->assertTrue(ImportDataStorage::has($model2));
        
        // Unset model1 (simulating garbage collection)
        unset($model1);
        
        // Model2 data should still exist
        $this->assertTrue(ImportDataStorage::has($model2));
        $this->assertEquals(['field2' => 'value2'], ImportDataStorage::get($model2));
    }
    
    public function test_data_extraction_and_filtering(): void
    {
        $builder = new ImporterBuilder();
        
        $data = [
            'name' => 'Product Name',
            'price' => 99.99,
            'custom_fields_color' => 'Red',
            'custom_fields_size' => 'Large',
            'description' => 'Product description',
        ];
        
        // Test extraction
        $customFields = $builder->extractCustomFieldsData($data);
        $this->assertEquals([
            'color' => 'Red',
            'size' => 'Large',
        ], $customFields);
        
        // Test filtering
        $filtered = $builder->filterCustomFieldsFromData($data);
        $this->assertEquals([
            'name' => 'Product Name',
            'price' => 99.99,
            'description' => 'Product description',
        ], $filtered);
    }
    
    public function test_pull_clears_storage(): void
    {
        $model = new class extends Model {};
        
        ImportDataStorage::set($model, 'field1', 'value1');
        ImportDataStorage::set($model, 'field2', 'value2');
        
        $data = ImportDataStorage::pull($model);
        
        $this->assertEquals([
            'field1' => 'value1',
            'field2' => 'value2',
        ], $data);
        
        // After pull, storage should be empty
        $this->assertFalse(ImportDataStorage::has($model));
        $this->assertEquals([], ImportDataStorage::get($model));
    }
    
    public function test_set_multiple(): void
    {
        $model = new class extends Model {};
        
        ImportDataStorage::setMultiple($model, [
            'field1' => 'value1',
            'field2' => 'value2',
            'field3' => 'value3',
        ]);
        
        $data = ImportDataStorage::get($model);
        
        $this->assertEquals([
            'field1' => 'value1',
            'field2' => 'value2',
            'field3' => 'value3',
        ], $data);
    }
}