<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Enums\FieldDataType;

use function Laravel\Prompts\select;

class MakeFieldTypeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:field-type {name : The name of the field type}
                            {--type= : The data type for the field}
                            {--force : Overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new custom field type';

    /**
     * The type of class being generated.
     */
    protected $type = 'FieldType';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../../stubs/field-type.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string // @pest-ignore-type
    {
        return $rootNamespace.'\Filament\FieldTypes';
    }

    /**
     * Get the destination class path.
     */
    protected function getPath($name): string // @pest-ignore-type
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        // Get the directory path and base name
        $nameWithoutRoot = ltrim($name, '\\/');
        $pathParts = explode('\\', $nameWithoutRoot);
        $baseName = array_pop($pathParts);

        // Ensure the filename matches the class name (with FieldType suffix)
        if (! Str::endsWith($baseName, 'FieldType')) {
            $baseName .= 'FieldType';
        }

        // Rebuild the path with proper filename
        $directory = implode('/', $pathParts);
        $fullPath = $this->laravel['path'];

        if ($directory !== '' && $directory !== '0') {
            $fullPath .= '/'.$directory;
        }

        return $fullPath.'/'.$baseName.'.php';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string // @pest-ignore-type
    {
        $stub = $this->files->get($this->getStub());

        $fieldTypeName = $this->getFieldTypeName($name);
        $fieldKey = Str::kebab($fieldTypeName);
        $fieldLabel = Str::title(Str::replace('-', ' ', $fieldKey));
        $dataType = $this->getDataType();

        $replacements = [
            '{{ namespace }}' => $this->getNamespace($name),
            '{{ class }}' => $this->getClassName($name),
            '{{ fieldKey }}' => $fieldKey,
            '{{ fieldLabel }}' => $fieldLabel,
            '{{ fieldTypeName }}' => $fieldTypeName,
            '{{ configurator }}' => $this->getConfiguratorForDataType($dataType),
            '{{ dataType }}' => $dataType->value,
            '{{ formComponentImport }}' => $this->getFormComponentImport($dataType),
            '{{ formComponent }}' => $this->getFormComponent($dataType),
            '{{ withoutUserOptions }}' => $this->shouldUseWithoutUserOptions($dataType) ? "\n            ->withoutUserOptions()" : '',
            '{{ choiceFieldComment }}' => $this->getChoiceFieldComment($dataType),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get the class name from the full name.
     */
    protected function getClassName(string $name): string
    {
        $className = class_basename($name);

        // Only add FieldType suffix if it doesn't already have it
        if (! Str::endsWith($className, 'FieldType')) {
            $className .= 'FieldType';
        }

        return $className;
    }

    /**
     * Get the field type name (without "FieldType" suffix).
     */
    protected function getFieldTypeName(string $name): string
    {
        $className = class_basename($name);

        return Str::replaceLast('FieldType', '', $className);
    }

    /**
     * Get the data type for the field type.
     */
    protected function getDataType(): FieldDataType
    {
        $typeOption = $this->option('type');

        if ($typeOption) {
            $dataType = FieldDataType::tryFrom($typeOption);
            if ($dataType) {
                return $dataType;
            }

            $this->warn(sprintf("Invalid data type '%s'. Showing available options...", $typeOption));
        }

        $options = [
            'string' => 'String - Short text, identifiers, URLs (max 255 chars)',
            'text' => 'Text - Long text, rich content, markdown (unlimited)',
            'numeric' => 'Numeric - Whole numbers, counts, IDs',
            'float' => 'Float - Decimal numbers, currency, measurements',
            'date' => 'Date - Date picker (YYYY-MM-DD)',
            'date_time' => 'DateTime - Date and time picker',
            'boolean' => 'Boolean - True/false, checkboxes, toggles',
            'single_choice' => 'Single Choice - Select dropdown, radio buttons',
            'multi_choice' => 'Multi Choice - Multiple selections, checkboxes, tags',
        ];

        $selected = function_exists('Laravel\Prompts\select')
            ? select('What data type should this field use?', $options, 'string')
            : $this->choice('What data type should this field use?', $options, 'string');

        return FieldDataType::from($selected);
    }

    /**
     * Get the appropriate configurator method for the given data type.
     */
    protected function getConfiguratorForDataType(FieldDataType $dataType): string
    {
        return match ($dataType) {
            FieldDataType::STRING => 'text()',
            FieldDataType::TEXT => 'text()',
            FieldDataType::NUMERIC => 'numeric()',
            FieldDataType::FLOAT => 'float()',
            FieldDataType::DATE => 'date()',
            FieldDataType::DATE_TIME => 'dateTime()',
            FieldDataType::BOOLEAN => 'boolean()',
            FieldDataType::SINGLE_CHOICE => 'singleChoice()',
            FieldDataType::MULTI_CHOICE => 'multiChoice()',
            FieldDataType::FILE => 'file()',
        };
    }

    /**
     * Get the appropriate form component import for the given data type.
     */
    protected function getFormComponentImport(FieldDataType $dataType): string
    {
        return match ($dataType) {
            FieldDataType::STRING => 'use Filament\Forms\Components\TextInput;',
            FieldDataType::TEXT => 'use Filament\Forms\Components\Textarea;',
            FieldDataType::NUMERIC => 'use Filament\Forms\Components\TextInput;',
            FieldDataType::FLOAT => 'use Filament\Forms\Components\TextInput;',
            FieldDataType::DATE => 'use Filament\Forms\Components\DatePicker;',
            FieldDataType::DATE_TIME => 'use Filament\Forms\Components\DateTimePicker;',
            FieldDataType::BOOLEAN => 'use Filament\Forms\Components\Toggle;',
            FieldDataType::SINGLE_CHOICE => 'use Filament\Forms\Components\Select;',
            FieldDataType::MULTI_CHOICE => 'use Filament\Forms\Components\CheckboxList;',
            FieldDataType::FILE => 'use Filament\Forms\Components\FileUpload;',
        };
    }

    /**
     * Get the appropriate form component code for the given data type.
     */
    protected function getFormComponent(FieldDataType $dataType): string
    {
        return match ($dataType) {
            FieldDataType::STRING => 'return TextInput::make($customField->getFieldName())
                ->label($customField->name)
                ->columnSpanFull();',
            FieldDataType::TEXT => 'return Textarea::make($customField->getFieldName())
                ->label($customField->name)
                ->rows(3)
                ->columnSpanFull();',
            FieldDataType::NUMERIC => 'return TextInput::make($customField->getFieldName())
                ->label($customField->name)
                ->numeric()
                ->columnSpanFull();',
            FieldDataType::FLOAT => 'return TextInput::make($customField->getFieldName())
                ->label($customField->name)
                ->numeric()
                ->step(0.01)
                ->columnSpanFull();',
            FieldDataType::DATE => 'return DatePicker::make($customField->getFieldName())
                ->label($customField->name)
                ->columnSpanFull();',
            FieldDataType::DATE_TIME => 'return DateTimePicker::make($customField->getFieldName())
                ->label($customField->name)
                ->columnSpanFull();',
            FieldDataType::BOOLEAN => 'return Toggle::make($customField->getFieldName())
                ->label($customField->name)
                ->columnSpanFull();',
            FieldDataType::SINGLE_CHOICE => 'return Select::make($customField->getFieldName())
                ->label($customField->name)
                ->options([
                    1 => \'Low Priority\',
                    2 => \'Medium Priority\',
                    3 => \'High Priority\',
                ])
                ->columnSpanFull();',
            FieldDataType::MULTI_CHOICE => 'return CheckboxList::make($customField->getFieldName())
                ->label($customField->name)
                ->columnSpanFull();',
            FieldDataType::FILE => 'return FileUpload::make($customField->getFieldName())
                ->label($customField->name)
                ->columnSpanFull();',
        };
    }

    /**
     * Check if the field type should use withoutUserOptions().
     */
    protected function shouldUseWithoutUserOptions(FieldDataType $dataType): bool
    {
        return $dataType === FieldDataType::SINGLE_CHOICE;
    }

    /**
     * Get comment for choice field types explaining the behavior.
     */
    protected function getChoiceFieldComment(FieldDataType $dataType): string
    {
        return match ($dataType) {
            FieldDataType::SINGLE_CHOICE => '// withoutUserOptions() showcases built-in options - can be used with both single and multi choice',
            FieldDataType::MULTI_CHOICE => '// No withoutUserOptions() showcases user-defined options - withoutUserOptions() can be used here too',
            default => '',
        };
    }

    /**
     * Handle the command execution.
     */
    public function handle(): bool
    {
        $name = $this->getNameInput();

        // Resolve the full class name with namespace
        $name = $this->qualifyClass($name);

        $path = $this->getPath($name);

        // Check if the field type already exists
        if ((! $this->hasOption('force') || ! $this->option('force')) && $this->alreadyExists($name)) {
            $this->error(sprintf('%s already exists!', $this->type));

            return false;
        }

        // Make sure the directory exists
        $this->makeDirectory($path);

        // Generate the field type class
        $this->files->put($path, $this->sortImports($this->buildClass($name)));

        $this->info(sprintf('%s created successfully.', $this->type));
        $this->line(sprintf('<comment>%s</comment>', $path));

        // Show next steps
        $this->newLine();
        $this->line('<comment>Next steps:</comment>');
        $this->line('1. Customize the generated field type:');
        $this->line('   - Update the icon from Heroicons');
        $this->line('   - Modify form component configuration');
        $this->line('   - Add validation rules and field capabilities');
        $this->line('2. Customize table column and infolist entry as needed');
        $this->line('3. Register your field type in a service provider');
        $this->line('4. Test the field type in your application');

        return true;
    }
}
