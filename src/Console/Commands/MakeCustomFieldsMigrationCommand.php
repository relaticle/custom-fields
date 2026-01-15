<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

/**
 * ABOUTME: Artisan command to generate custom fields migration files
 * ABOUTME: Creates migration stubs in database/custom-fields directory for preset custom fields
 */
class MakeCustomFieldsMigrationCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:custom-fields-migration {name : The name of the migration}
                            {--path= : The location where the migration file should be created}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new custom fields migration';

    /**
     * The type of class being generated.
     */
    protected $type = 'CustomFieldsMigration';

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     */
    protected function getPath($name): string // @pest-ignore-type
    {
        $path = $this->option('path') ?? config('custom-fields.database.migrations_path', database_path('custom-fields'));

        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

        $filename = $this->getDatePrefix().'_'.Str::snake(trim($name)).'.php';

        return $path.'/'.$filename;
    }

    /**
     * Get the date prefix for the migration.
     */
    protected function getDatePrefix(): string
    {
        return date('Y_m_d_His');
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../../stubs/custom-fields-migration.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string // @pest-ignore-type
    {
        return '';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string // @pest-ignore-type
    {
        $stub = $this->files->get($this->getStub());

        return $this->replaceClass($stub, $name);
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     */
    protected function replaceClass($stub, $name): string // @pest-ignore-type
    {
        return str_replace(['{{ class }}', '{{class}}'], class_basename($name), $stub);
    }

    /**
     * Handle the command execution.
     */
    public function handle(): bool
    {
        $name = $this->getNameInput();
        $path = $this->getPath($name);

        // Check if the migration already exists
        if ($this->files->exists($path)) {
            $this->error(sprintf('Migration %s already exists!', $path));

            return false;
        }

        // Make sure the directory exists
        $this->makeDirectory($path);

        // Generate the migration
        $this->files->put($path, $this->buildClass($name));

        $this->info('Custom fields migration created successfully:');
        $this->line(sprintf('<comment>%s</comment>', $path));

        return true;
    }
}
