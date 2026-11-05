<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $values = config('custom-fields.database.table_names.custom_field_values');

        Schema::table($values, function (Blueprint $table) use ($values): void {
            if (! Schema::hasColumn($values, 'source')) {
                $table->string('source', 32)->nullable();
            }

            if (! Schema::hasColumn($values, 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }
};
