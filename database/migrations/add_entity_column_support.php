<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add uses_entity_column flag to custom_fields table
        Schema::table(config('custom-fields.database.table_names.custom_fields'), function (Blueprint $table): void {
            $table->boolean('uses_entity_column')->default(false)->after('system_defined');
        });

        // Add value column to custom_field_options table
        Schema::table(config('custom-fields.database.table_names.custom_field_options'), function (Blueprint $table): void {
            $table->string('value')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table(config('custom-fields.database.table_names.custom_fields'), function (Blueprint $table): void {
            $table->dropColumn('uses_entity_column');
        });

        Schema::table(config('custom-fields.database.table_names.custom_field_options'), function (Blueprint $table): void {
            $table->dropColumn('value');
        });
    }
};
