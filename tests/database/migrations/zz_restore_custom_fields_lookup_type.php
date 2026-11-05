<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The suite runs on the schema a host still has when it upgrades: 4.0 drops lookup_type, and
 * the step that translates it has to be tested against a database that still has it. Adding
 * it back here rather than inside the tests keeps every driver on the same path, because
 * MySQL commits the test transaction on DDL and a rolled-back test stops being one.
 *
 * The name sorts last: the migrator orders every registered path by file name, and this has
 * to run after the package migration that drops the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('custom-fields.database.table_names.custom_fields');

        if (Schema::hasColumn($table, 'lookup_type')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('lookup_type')->nullable();
        });
    }
};
