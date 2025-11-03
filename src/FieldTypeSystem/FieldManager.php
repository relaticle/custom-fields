<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem;

use Closure;
use Filament\Support\Concerns\EvaluatesClosures;
use Relaticle\CustomFields\Collections\FieldTypeCollection;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Data\FieldTypeData;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\CheckboxFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\CheckboxListFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\ColorPickerFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\CurrencyFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\DateFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\DateTimeFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\EmailFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\FileUploadFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\LinkFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\MarkdownEditorFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\MultiSelectFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\NumberFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\PhoneFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RadioFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RichEditorFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\SelectFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\TagsInputFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\TextareaFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\TextFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\ToggleButtonsFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\ToggleFieldType;

final class FieldManager
{
    use EvaluatesClosures;

    const array DEFAULT_FIELD_TYPES = [
        TextFieldType::class,
        NumberFieldType::class,
        EmailFieldType::class,
        PhoneFieldType::class,
        LinkFieldType::class,
        TextareaFieldType::class,
        CheckboxFieldType::class,
        CheckboxListFieldType::class,
        RadioFieldType::class,
        RichEditorFieldType::class,
        MarkdownEditorFieldType::class,
        TagsInputFieldType::class,
        ColorPickerFieldType::class,
        ToggleFieldType::class,
        ToggleButtonsFieldType::class,
        CurrencyFieldType::class,
        DateFieldType::class,
        DateTimeFieldType::class,
        SelectFieldType::class,
        MultiSelectFieldType::class,
        FileUploadFieldType::class,
    ];

    /**
     * @var array<array<string, array<int, string> | string> | Closure>
     */
    private array $fieldTypes = [];

    /**
     * @var array<int, string>
     */
    private array $cachedFieldTypes;

    /**
     * @var array<string, FieldTypeDefinitionInterface>
     */
    private array $cachedInstances = [];

    /**
     * @param  array<string, array<int, string> | string> | Closure  $fieldTypes
     */
    public function register(array|Closure $fieldTypes): static
    {
        $this->fieldTypes[] = $fieldTypes;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getFieldTypes(): array
    {
        if (isset($this->cachedFieldTypes)) {
            return $this->cachedFieldTypes;
        }

        array_unshift($this->fieldTypes, self::DEFAULT_FIELD_TYPES);

        $allFieldTypes = [];
        foreach ($this->fieldTypes as $fieldTypes) {
            $fieldTypes = $this->evaluate($fieldTypes);

            foreach ($fieldTypes as $fieldType) {
                $allFieldTypes[] = $fieldType;
            }
        }

        // Apply field type configuration restrictions
        $fieldTypeConfiguration = config('custom-fields.field_type_configuration');

        if ($fieldTypeConfiguration instanceof FieldTypeConfigurator) {
            // Filter field types based on configuration
            $allFieldTypes = array_filter($allFieldTypes, function (string $class) use ($fieldTypeConfiguration): bool {
                /** @var FieldTypeDefinitionInterface $instance */
                $instance = new $class;
                $config = $instance->configure();
                $data = $config->data();

                return $fieldTypeConfiguration->isFieldTypeAllowed($data->key);
            });
        }

        $this->cachedFieldTypes = $allFieldTypes;

        return $this->cachedFieldTypes;
    }

    public function getFieldType(?string $fieldType): ?FieldTypeData
    {
        if ($fieldType === null) {
            return null;
        }

        return $this->toCollection()->firstWhere('key', $fieldType);
    }

    /**
     * Get a field type instance by key.
     */
    public function getFieldTypeInstance(string $key): ?FieldTypeDefinitionInterface
    {
        if (isset($this->cachedInstances[$key])) {
            return $this->cachedInstances[$key];
        }

        // Build collection if needed (which also caches instances)
        $this->toCollection();

        return $this->cachedInstances[$key] ?? null;
    }

    public function toCollection(): FieldTypeCollection
    {
        $fieldTypes = [];

        foreach ($this->getFieldTypes() as $fieldTypeClass) {
            /** @var FieldTypeDefinitionInterface $fieldType */
            $fieldType = new $fieldTypeClass;
            $config = $fieldType->configure();

            $data = $config->data();

            $fieldTypes[$data->key] = $data;

            // Cache the instance
            $this->cachedInstances[$data->key] = $fieldType;
        }

        return FieldTypeCollection::make($fieldTypes)->sortBy('priority', SORT_NATURAL)->values();
    }
}
