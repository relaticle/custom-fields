<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Support\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $sectionsEnabled = FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS);

        /**
         * Custom Field Sections (only created when SYSTEM_SECTIONS)
         */
        if ($sectionsEnabled) {
            Schema::create(config('custom-fields.database.table_names.custom_field_sections'), function (Blueprint $table): void {
                $uniqueColumns = ['entity_type', 'code'];

                KeyType::primary($table);

                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    KeyType::foreign($table, config('custom-fields.database.column_names.tenant_foreign_key'))->nullable()->index();
                    $uniqueColumns[] = config('custom-fields.database.column_names.tenant_foreign_key');
                }

                $table->string('width')->nullable();

                $table->string('code');
                $table->string('name');
                $table->string('type');
                $table->string('entity_type');
                $table->unsignedBigInteger('sort_order')->nullable();

                $table->string('description')->nullable();

                $table->boolean('active')->default(true);
                $table->boolean('system_defined')->default(false);

                $table->json('settings')->nullable();

                $table->unique($uniqueColumns);

                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $table->index([config('custom-fields.database.column_names.tenant_foreign_key'), 'entity_type', 'active'], 'custom_field_sections_tenant_entity_active_idx');
                } else {
                    $table->index(['entity_type', 'active'], 'custom_field_sections_entity_active_idx');
                }

                $table->timestamps();
            });
        }

        /**
         * Custom Fields
         */
        Schema::create(config('custom-fields.database.table_names.custom_fields'), function (Blueprint $table) use ($sectionsEnabled): void {
            $uniqueColumns = ['code', 'entity_type'];

            KeyType::primary($table);

            if ($sectionsEnabled) {
                KeyType::foreign($table, 'custom_field_section_id')->nullable();
            }
            $table->string('width')->nullable();

            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                KeyType::foreign($table, config('custom-fields.database.column_names.tenant_foreign_key'))->nullable()->index();
                $uniqueColumns[] = config('custom-fields.database.column_names.tenant_foreign_key');
            }

            $table->string('code');
            $table->string('name');
            $table->string('type');
            $table->string('entity_type');
            $table->unsignedBigInteger('sort_order')->nullable();
            $table->json('validation_rules')->nullable();

            $table->boolean('active')->default(true);
            $table->boolean('system_defined')->default(false);

            $table->json('settings')->nullable();

            $table->unique($uniqueColumns);

            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                $table->index([config('custom-fields.database.column_names.tenant_foreign_key'), 'entity_type', 'active'], 'custom_fields_tenant_entity_active_idx');
            } else {
                $table->index(['entity_type', 'active'], 'custom_fields_entity_active_idx');
            }

            $table->timestamps();
        });

        /**
         * Custom Field Options
         */
        Schema::create(config('custom-fields.database.table_names.custom_field_options'), function (Blueprint $table): void {
            $uniqueColumns = ['custom_field_id', 'name'];

            KeyType::primary($table);

            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                KeyType::foreign($table, config('custom-fields.database.column_names.tenant_foreign_key'))->nullable()->index();
                $uniqueColumns[] = config('custom-fields.database.column_names.tenant_foreign_key');
            }

            KeyType::foreign($table, 'custom_field_id')
                ->constrained(config('custom-fields.database.table_names.custom_fields'))
                ->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->unsignedBigInteger('sort_order')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->unique($uniqueColumns);
        });

        /**
         * Custom Field Values
         */
        Schema::create(config('custom-fields.database.table_names.custom_field_values'), function (Blueprint $table): void {
            $uniqueColumns = ['entity_type', 'entity_id', 'custom_field_id'];

            KeyType::primary($table);

            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                KeyType::foreign($table, config('custom-fields.database.column_names.tenant_foreign_key'))->nullable()->index();
                $uniqueColumns[] = config('custom-fields.database.column_names.tenant_foreign_key');
            }

            KeyType::morphs($table, 'entity');
            KeyType::foreign($table, 'custom_field_id')
                ->constrained(config('custom-fields.database.table_names.custom_fields'))
                ->cascadeOnDelete();

            $table->text('string_value')->nullable();
            $table->longText('text_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->bigInteger('integer_value')->nullable();
            $table->double('float_value')->nullable();
            $table->date('date_value')->nullable();
            $table->dateTime('datetime_value')->nullable();
            $table->json('json_value')->nullable();

            $table->string('source', 32)->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique($uniqueColumns, 'custom_field_values_entity_type_unique');

            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                $table->index([config('custom-fields.database.column_names.tenant_foreign_key'), 'entity_type', 'entity_id'], 'custom_field_values_tenant_entity_idx');
            } else {
                $table->index(['entity_type', 'entity_id'], 'custom_field_values_entity_idx');
            }

            $table->index(['entity_id', 'custom_field_id'], 'custom_field_values_entity_id_custom_field_id_index');
        });
    }
};
