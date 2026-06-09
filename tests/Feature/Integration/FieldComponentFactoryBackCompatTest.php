<?php

declare(strict_types=1);

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\FormComponentInterface;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldComponentFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

/**
 * Regression test: FieldComponentFactory must accept third-party form components
 * that implement FormComponentInterface directly without extending AbstractFormComponent.
 * Previously the factory validated against AbstractFormComponent::class, which is a
 * backward-incompatible restriction on a public extension point.
 */

// Inline bare-interface implementation — does NOT extend AbstractFormComponent.
class BareInterfaceFormComponent implements FormComponentInterface
{
    public function make(CustomField $customField, array $dependentFieldCodes = [], ?Collection $allFields = null): Field
    {
        return TextInput::make($customField->getFieldName());
    }
}

// Field type that wires up the bare-interface component.
class BareInterfaceFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('bare-interface-type')
            ->label('Bare Interface Type')
            ->icon('heroicon-o-pencil')
            ->formComponent(BareInterfaceFormComponent::class);
    }
}

describe('FieldComponentFactory backward-compat: bare FormComponentInterface implementer', function (): void {
    it('accepts a form component that implements FormComponentInterface without extending AbstractFormComponent', function (): void {
        CustomFieldsType::register([
            'bare-interface-type' => BareInterfaceFieldType::class,
        ]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'BC Test Section',
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'BC Field',
            'code' => 'bc_field',
            'type' => 'bare-interface-type',
        ]);

        $factory = app(FieldComponentFactory::class);

        $result = $factory->create($field);

        expect($result)->toBeInstanceOf(Field::class);
    });

    it('does NOT throw RuntimeException for bare FormComponentInterface implementers', function (): void {
        CustomFieldsType::register([
            'bare-interface-type' => BareInterfaceFieldType::class,
        ]);

        $section = CustomFieldSection::factory()->create([
            'name' => 'BC Test Section 2',
            'entity_type' => Post::class,
            'active' => true,
        ]);

        $field = CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'name' => 'BC Field 2',
            'code' => 'bc_field_2',
            'type' => 'bare-interface-type',
        ]);

        $factory = app(FieldComponentFactory::class);

        expect(fn () => $factory->create($field))->not->toThrow(RuntimeException::class);
    });
});
