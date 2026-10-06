<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Migrations;

use Illuminate\Database\Migrations\Migration;

abstract class CustomFieldsMigration extends Migration
{
    protected CustomFieldsMigrator $migrator;

    abstract public function up(): void;

    public function __construct()
    {
        $this->migrator = app(CustomFieldsMigrator::class);
    }
}
